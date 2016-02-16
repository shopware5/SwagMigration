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
     * Returns the default error message for this import class
     *
     * @return mixed
     */
    public function getDefaultErrorMessage()
    {
        return $this->getNameSpace()->get('errorImportingRatings', "An error occurred while importing ratings");
    }

    /**
     * Returns the progress message for the current import step. A Progress-Object will be passed, so
     * you can get some context info for your snippet
     *
     * @param Progress $progress
     * @return string
     */
    public function getCurrentProgressMessage($progress)
    {
        return sprintf(
            $this->getNameSpace()->get('progressRatings', "%s out of %s ratings imported"),
            $this->getProgress()->getOffset(),
            $this->getProgress()->getCount()
        );
    }

    /**
     * Returns the default 'all done' message
     *
     * @return mixed
     */
    public function getDoneMessage()
    {
        return $this->getNameSpace()->get('importedRatings', "Ratings successfully imported!");
    }

    /**
     * Main run method of each import adapter. The run method will query the source profile, iterate
     * the results and prepare the data for import via the old Shopware API.
     *
     * If you want to import multiple entities with one import-class, you might want to check for
     * $this->getInternalName() in order to distinct which (sub)entity you where called for.
     *
     * The run method may only return instances of Progress
     * The calling instance will use those progress object to communicate with the ExtJS backend.
     * If you want this to work properly, think of calling:
     * - $this->initTaskTimer() at the beginning of your run method
     * - $this->getProgress()->setCount(222) to set the total number of data
     * - $this->increaseProgress() to increase the offset/progress
     * - $this->getProgress()->getOffset() to get the current progress' offset
     * - return $this->getProgress()->error("Message") in order to stop with an error message
     * - return $this->getProgress() in order to be called again with the current offset
     * - return $this->getProgress()->done() in order to mark the import as finished
     *
     *
     * @return Progress
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
                AND `typeID`=?
            ';
            $rating['articleID'] = Shopware()->Db()->fetchOne($sql, [$rating['productID'], Migration::MAPPING_ARTICLE]);

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
                    !empty($rating['email']) ? $rating['email'] : 'NOW()'
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
