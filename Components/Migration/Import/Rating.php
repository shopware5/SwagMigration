<?php
/**
 * Shopware 4.0
 * Copyright © 2013 shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

/**
 * Shopware SwagMigration Components - Rating
 *
 * Rating import adapter
 *
 * @category  Shopware
 * @package Shopware\Plugins\SwagMigration\Components
 * @copyright Copyright (c) 2012, shopware AG (http://www.shopware.de)
 */
class Shopware_Components_Migration_Import_Rating extends Shopware_Components_Migration_Import_Base
{

    /**
     * Returns the default error message for this import class
     * @return mixed
     */
    public function getDefaultErrorMessage()
    {
        return $this->getNameSpace()->get('errorImportingProducts', "An error occurred while importing products");

    }

    /**
     * Returns the progress message for the current import step. A Progress-Object will be passed, so
     * you can get some context info for your snippet
     *
     * @param Shopware_Components_Migration_Import_Progress $progress
     * @return string
     */
    public function getCurrentProgressMessage($progress)
    {
        return sprintf($this->getNameSpace()->get('progressRatings', "%s out of %s ratings imported"), $this->getProgress()->getOffset(), $this->getProgress()->getCount());
    }

    /**
     * Returns the default 'all done' message
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
     * The run method may only return instances of Shopware_Components_Migration_Import_Progress
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
     * @return Shopware_Components_Migration_Import_Progress
     */
    public function run()
    {
        $offset = $this->getProgress()->getOffset();

        $result = $this->Source()->queryProductRatings();
        $count = $result->rowCount()+$offset;
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
            $rating['articleID'] = Shopware()->Db()->fetchOne($sql, array($rating['productID'], Shopware_Components_Migration_Helpers::MAPPING_ARTICLE));

            if(empty($rating['articleID'])) {
                continue;
            }

            $sql = '
                SELECT `id`
                FROM `s_articles_vote`
                WHERE `articleID`=?
                AND `name` LIKE ?
                AND `email`=?
            ';
            $ratingID = Shopware()->Db()->fetchOne($sql, array(
                $rating['articleID'],
                $rating['name'],
                !empty($rating['email']) ? $rating['email'] : 'NOW()'
            ));

            if(!empty($ratingID)) {
                continue;
            }

            $data = array(
                'articleID' => $rating['articleID'],
                'name' => !empty($rating['name']) ? $rating['name'] : '',
                'headline' => !empty($rating['title']) ? $rating['title'] : '',
                'comment' => !empty($rating['comment']) ? $rating['comment'] : '',
                'points' =>  isset($rating['rating']) ? (float) $rating['rating'] : 5,
                'datum' => isset($rating['date']) ? $rating['date'] : new Zend_Db_Expr('NOW()'),
                'active' => isset($rating['active']) ? $rating['active'] : 1,
                'email' => !empty($rating['email']) ? $rating['email'] : '',
            );
            Shopware()->Db()->insert('s_articles_vote', $data);

        }

        return $this->getProgress()->done();
    }
}