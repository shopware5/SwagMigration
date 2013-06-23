<?php
/**
 * Shopware 4.0
 * Copyright © 2012 shopware AG
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
 * Shopware SwagMigration Components - Helpers
 *
 * This class contains some general helper methods used by the SwagMigration controller
 *
 * @category  Shopware
 * @package Shopware\Plugins\SwagMigration\Components
 * @copyright Copyright (c) 2012, shopware AG (http://www.shopware.de)
 */
class Shopware_Components_Migration_Helpers extends Enlight_Class
{
    /**
     * Default constants for the mappings from the foreign IDs to Shopware IDs
     */
    const MAPPING_ARTICLE = 1;
    const MAPPING_CATEGORY = 2;
    const MAPPING_CUSTOMER = 3;
    const MAPPING_ORDER = 4;
    const MAPPING_VALID_NUMBER = 23;
    const MAPPING_CATEGORY_TARGET = 99;

    const CATEGORY_LANGUAGE_SEPARATOR = '_LANG_';


    /**
     * Set filter groups/options/values for a given article
     *
     * Example Array:
     *
     *	array(
     *		'productID' => 12,
     * 		'group' =>	array (
     *		     'id' => 33,
     *			'name' => 'Edelbrände',
     *			'position' => 0,
     *			'comparable' => true,
     *			'sortmode' => 3,
     *			'options' => array(
     *				'name' => 'test',
     *				'id' => 3,
     *				'filterable' => true,
     *				'default' => ''
     *				'values' => array(
     *					array('position' => 3, 'value' => 'Mein Wert'),
     *					array('position' => 3, 'value' => 'Mein Wert2')
     *				)
     *			)
     *		)
     *	)
     *
     * @param $data
     * @return bool
     */
	public function importProductProperty($data)
    {
        $productId = $data['productID'];
        $group = $data['group'];

        $group['position'] = isset($group['position']) ? (int) $group['position'] : 0;
        $group['comparable'] = isset($group['comparable']) ? $group['comparable'] : false;
        $group['sortmode'] = isset($group['sortmode']) ? (int) $group['sortmode'] : 0;

        /**
         * Get existing group or create new one
         */
        if (isset($group['id'])) {
            $sql = "SELECT `id` FROM `s_filter` WHERE `id` = ?";
            $groupId = Shopware()->Db()->fetchOne($sql, array($group['id']));
        }elseif (isset($group['name'])) {
            $sql = "SELECT `id` FROM `s_filter` WHERE `name` = ?";
            $groupId = Shopware()->Db()->fetchOne($sql, array($group['name']));
        }else{
            error_log("no group info passed");
            return;
        }

        if(false == $groupId && isset($group['name'])) {
            $sql = 'INSERT INTO `s_filter` (`name`, `position`, `comparable`, `sortmode`) VALUES(?, ?, ?, ?)';
            Shopware()->Db()->query($sql, array($group['name'], $group['position'], $group['comparable'], $group['sortmode']));
            $groupId = Shopware()->Db()->lastInsertId();
        }

        if(false === $groupId) {
            error_log("no group not found");
            return false;
        }

        /**
         * Get existing options or create new ones
         */
        foreach($group['options'] as &$option) {
            $option['filterable'] = isset($group['filterable']) ? (int) $group['filterable'] : 1;
            $option['default'] = isset($group['default']) ? $group['default'] : '';

            if (isset($option['id'])) {
                $sql = "
					SELECT o.`id`
					FROM `s_filter_options` o
					WHERE o.`id` = ?
				";
                $optionId = Shopware()->Db()->fetchOne($sql, array($option['id']));
            }elseif (isset($option['name'])) {
                // First try to get option by name with associated group
                $sql = "
					SELECT o.`id`
					FROM `s_filter_options` o
					INNER JOIN `s_filter_relations` r
					ON r.groupID = ?
					AND r.optionID = o.id
					WHERE o.`name` = ?
					";
                $optionId = Shopware()->Db()->fetchOne($sql, array($groupId, $option['name']));

                // Then try to find option by name ignoring associated groups
                if (false === $optionId) {
                    $sql = "
						SELECT o.`id`
						FROM `s_filter_options` o
						WHERE o.`name` = ?
						LIMIT 1
						";
                    $optionId = Shopware()->Db()->fetchOne($sql, array($option['name']));
                }
            }

            // Create option
            if (false == $optionId && isset($option['name'])) {
                $sql = 'INSERT INTO `s_filter_options` (`name`, `filterable`, `default`) VALUES(?, ?, ?)';
                Shopware()->Db()->query($sql, array($option['name'], $option['filterable'], $option['default']));
                $optionId = Shopware()->Db()->lastInsertId();
            }

            if(false === $optionId) {
                error_log("option not found");
                return false;
            }

            // Make sure that the group-option relations are set
            $sql = 'INSERT IGNORE INTO `s_filter_relations` (`groupID`, `optionID`) VALUES (?, ?)';
            Shopware()->Db()->query($sql, array($groupId, $optionId));


            foreach($option['values'] as &$value) {
                $value['position'] = isset($value['position']) ? $value['position'] : '';


                if (isset($value['id'])) {
                    $sql = "
						SELECT v.`id`
						FROM `s_filter_values` v
						WHERE v.`id` = ?
					";
                    $valueId = Shopware()->Db()->fetchOne($sql, array($value['id']));
                }elseif (isset($value['value'])) {
                    // Try to get value by value with associated option
                    $sql = "
						SELECT v.`id`
						FROM `s_filter_values` v
						WHERE v.`value` = ?
						AND v.`optionID` = ?
						";
                    $valueId = Shopware()->Db()->fetchOne($sql, array($value['value'], $optionId));
                }

                // Create option
                if (false == $valueId && isset($value['value'])) {
                    $sql = 'INSERT INTO `s_filter_values` (`value`, `optionId`, `position`) VALUES(?, ?, ?)';
                    Shopware()->Db()->query($sql, array($value['value'], $optionId, $value['position']));
                    $valueId = Shopware()->Db()->lastInsertId();
                }

                if(false === $valueId) {
                    error_log("value not found");
                    return false;
                }

                // Finally assign filter values to article
                $sql = 'INSERT IGNORE INTO `s_filter_articles` (`articleID`, `valueID`) VALUES (?, ?)';
                Shopware()->Db()->query($sql, array($productId, $valueId));

            }

        }

        // Set filter group for the given article
        Shopware()->Db()->query('UPDATE s_articles SET filtergroupID = ? WHERE id = ?', array($groupId, $productId));



    }

	/**
     * Helper function to remove an old article detail ans set another detail instead of it. Will also update
     * s_plugin_migrations in order to link other child-products to the new detail instead of the old one
     *
     * @param $oldMainDetail
     * @param $newMainDetail
     * @param $articleId
     */
	public function replaceProductDetail($oldMainDetail, $newMainDetail, $articleId)
    {
        // Delete old main detail
        $sql = 'DELETE FROM s_articles_details WHERE id = ?';
        Shopware()->Db()->query(
            $sql,
            array($oldMainDetail)
        );

        // Set the new mainDetail for the article
        $sql = 'UPDATE s_articles SET main_detail_id = ? WHERE id = ?';
        Shopware()->Db()->query(
            $sql,
            array($newMainDetail, $articleId)
        );

        // Update kind of the new main detail
        $sql = 'UPDATE s_articles_details SET kind=1 WHERE id = ?';
        Shopware()->Db()->query(
            $sql,
            array($newMainDetail)
        );

        // Update mapping so that references to the old dummy article point to this article
        $sql = 'UPDATE s_plugin_migrations SET targetID = ? WHERE typeID = ? AND targetID = ?';
        Shopware()->Db()->query(
            $sql,
            array($newMainDetail,self::MAPPING_ARTICLE, $oldMainDetail)
        );
    }


	/**
     * Helper function which gets the configurator groups for
     * a given product
     * @param $productId
     * @return Array
     */
	public function getConfiguratorGroups($productId)
    {
        // get configurator groups for the given product
        $builder = Shopware()->Models()->createQueryBuilder();
        $builder->select(array('PARTIAL article.{id}', 'configuratorSet', 'groups'))
            ->from('Shopware\Models\Article\Article', 'article')
            ->innerJoin('article.configuratorSet', 'configuratorSet')
            ->leftJoin('configuratorSet.groups', 'groups')
            ->where('article.id = ?1')
            ->setParameter(1, $productId);

        $result = array_pop($builder->getQuery()->getArrayResult());

        $configuratorArray = $result['configuratorSet'];
        $groups = $configuratorArray['groups'];

        // Additionally get the options for the given configurator set
        // this relation seems not to be available in the configurator models
        // (the configuratorSet-Model returns all group's options, even those
        // not related to the given set)
        $sql = "SELECT options.group_id, true as active, options.id FROM `s_article_configurator_sets` sets

	     LEFT JOIN s_article_configurator_set_option_relations relations
	     ON relations.set_id = sets.id

	     LEFT JOIN s_article_configurator_options options
	     ON options.id = relations.option_id

	     WHERE sets.id = ?";
        $results = Shopware()->Db()->fetchAll($sql, array($configuratorArray['id']));

        // Sort the options by group
        $optionsByGroups = array();
        foreach($results as $option) {
            $groupId = $option['group_id'];
            if (!isset($optionsByGroups[$groupId])) {
                $optionsByGroups[$groupId] = array();
            }
            $optionsByGroups[$groupId][] = $option;
        }

        // merge the options into the group
        $totalCount = 1;
        foreach ($groups as &$group) {
            $group['options'] = $optionsByGroups[$group['id']];
            if (count($group['options']) > 0 ) {
                $totalCount = $totalCount * count($group['options']);
            }
        }

        return $groups;
    }

	/**
     * Helper function which tells, if a given image was already assigned to a given product
     *
     * @param $articleId
     * @param $image
     * @return boolean
     */
	public function imageAlreadyImported($articleId, $image)
    {
        // Get a proper image name (without path and extension)
        $info = pathinfo($image);
        $extension = $info['extension'];
        $name = basename($image, '.'.$extension);

        // Find images with the same articleId and image name
        $sql = '
			SELECT COUNT(*)
			FROM `s_articles_img`
			WHERE articleID = ?
			AND img = ?
		';
        $numOfImages = Shopware()->Db()->fetchOne(
            $sql,
            array($articleId, $name)
        );

        if ((int) $numOfImages > 0) {
            return true;
        }

        return false;
    }

    /**
     * Import the customer debit
     *
     * @param $customer
     * @return boolean
     */
    public function importCustomerDebit($customer)
    {
        $fields = array(
            'account' => false,
            'bankcode' => false,
            'bankholder' => false,
            'bankname' => false,
            'userID' => false
        );

        // Iterate the array, remove unneeded fields and check if the required fields exist
        foreach ($customer as $key => $value) {
            if (array_key_exists($key, $fields)) {
                $fields[$key] = true;
            } else {
                unset($customer[$key]);
            }
        }
        // Required field not found
        if (in_array(false, $fields)) {
            return false;
        }

        Shopware()->Db()->insert('s_user_debit', $customer);
        return true;
    }

}