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
 * Shopware SwagMigration Components - Property
 *
 * Property import adapter
 *
 * @category  Shopware
 * @package Shopware\Plugins\SwagMigration\Components\Migration\Import\Resource
 * @copyright Copyright (c) 2012, shopware AG (http://www.shopware.de)
 */
class Shopware_Components_Migration_Import_Resource_Property extends Shopware_Components_Migration_Import_Resource_Abstract
{

    /**
     * Returns the default error message for this import class
     * @return mixed
     */
    public function getDefaultErrorMessage()
    {
        return $this->getNameSpace()->get(
            'errorImportingProductProperties',
            "An error occurred while importing product properties"
        );
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
        return sprintf(
            $this->getNameSpace()->get('progressProductProperties', "%s out of %s product properties imported"),
            $this->getProgress()->getOffset(),
            $this->getProgress()->getCount()
        );
    }

    /**
     * Returns the default 'all done' message
     * @return mixed
     */
    public function getDoneMessage()
    {
        return $this->getNameSpace()->get('importProductProperties', "Properties imported");
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

        // Get ids of products with properties
        $result = $this->Source()->queryProductsWithProperties($offset);
        if (!$result || $result->rowCount() === 0) {
            return $this->getProgress()->done();
        }

        $count = $result->rowCount()+$offset;
        $this->getProgress()->setCount($count);

        $this->initTaskTimer();

        // Iterate those products, get properties, import them
        while ($product = $result->fetch()) {
            // Skip products which have not been imported before
            $productId = $this->getBaseArticleInfo($product['productID']);
            if (false === $productId) {
                $this->increaseProgress();
                continue;
            }

            // Get product's properties
            $property_result = $this->Source()->queryProductProperties($product['productID']);
            $options = array();
            $groupName = '';

            // Build nested array of properties
            while ($property = $property_result->fetch()) {
                // Skip empty properties
                if (empty($property['option']) || empty($property['value'])) {
                    continue;
                }

                // In SW a product can only have *ONE* property group associated
                if(empty($property['group'])
                    && isset($this->Request()->property_options[$property['option']])
                    && !empty($this->Request()->property_options[$property['option']])
                ) {
                    $property['group'] = $this->Request()->property_options[$property['option']];
                } elseif(empty($property['group'])) {
                    $property['group'] = 'Properties';
                }
                $groupName = $property['group'];

                // Create new element or extend existing
                if (!array_key_exists($property['option'], $options)) {
                    $options[$property['option']] = array('name' => $property['option'], values => array(array('value' => $property['value'])));
                } else {
                    array_push($options[$property['option']]['values'], array('value' => $property['value']));
                }
            }

            if (!empty($groupName)) {
                $data = array(
                    'productID' => $productId,
                    'group' => array(
                        'name' => $groupName,
                        'options' => $options
                    )
                );

                // Actually import the properties
                $this->importProductProperty($data);
            }

            $this->increaseProgress();
            if ($this->newRequestNeeded()) {
                return $this->getProgress();
            }
        }

        return $this->getProgress()->done();
    }

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
}