<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagMigration\Components\Migration\Import\Resource;

use Shopware\SwagMigration\Components\Migration;
use Shopware\SwagMigration\Components\Migration\Import\Progress;
use Zend_Db_Expr;

class Rating extends AbstractResource
{
    /**
     * @inheritdoc
     */
    public function getDefaultErrorMessage()
    {
        return $this->getNameSpace()->get('errorImportingRatings', "An error occurred while importing ratings");
    }

    /**
     * @inheritdoc
     */
    public function getCurrentProgressMessage(Progress $progress)
    {
        return sprintf(
            $this->getNameSpace()->get('progressRatings', "%s out of %s ratings imported"),
            $this->getProgress()->getOffset(),
            $this->getProgress()->getCount()
        );
    }

    /**
     * @inheritdoc
     */
    public function getDoneMessage()
    {
        return $this->getNameSpace()->get('importedRatings', "Ratings successfully imported!");
    }

    /**
     * @inheritdoc
     */
    public function run()
    {
        $offset = $this->getProgress()->getOffset();

        $result = $this->Source()->queryProductRatings();
        $count = $result->rowCount() + $offset;
        $this->getProgress()->setCount($count);


        while ($rating = $result->fetch()) {
            $sql = '
                SELECT ad.articleID
                FROM s_plugin_migrations pm
                JOIN s_articles_details ad
                ON ad.id=pm.targetID
                WHERE pm.`sourceID`=?
                AND (`typeID`=? OR `typeID`=?)
            ';
            $rating['articleID'] = Shopware()->Db()->fetchOne(
                $sql,
                [
                    $rating['productID'],
                    Migration::MAPPING_ARTICLE,
                    Migration::MAPPING_VALID_NUMBER
                ]
            );

            if (empty($rating['articleID'])) {
                continue;
            }

            $sql = '
                SELECT `id`
                FROM `s_articles_vote`
                WHERE `articleID`=?
                AND `name` LIKE ?
                AND `email`=?
            ';

            $ratingID = Shopware()->Db()->fetchOne(
                $sql,
                [
                    $rating['articleID'],
                    $rating['name'],
                    !empty($rating['date']) ? $rating['date'] : 'NOW()'
                ]
            );

            if (!empty($ratingID)) {
                continue;
            }

            $data = [
                'articleID' => $rating['articleID'],
                'name' => !empty($rating['name']) ? $rating['name'] : '',
                'headline' => !empty($rating['title']) ? $rating['title'] : '',
                'comment' => !empty($rating['comment']) ? $rating['comment'] : '',
                'points' => isset($rating['rating']) ? (float) $rating['rating'] : 5,
                'datum' => isset($rating['date']) ? $rating['date'] : new Zend_Db_Expr('NOW()'),
                'active' => isset($rating['active']) ? $rating['active'] : 1,
                'email' => !empty($rating['email']) ? $rating['email'] : '',
            ];
            Shopware()->Db()->insert('s_articles_vote', $data);
        }

        return $this->getProgress()->done();
    }
}
