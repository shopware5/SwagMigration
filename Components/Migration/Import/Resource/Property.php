<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagMigration\Components\Migration\Import\Resource;

use Shopware\SwagMigration\Components\Migration\Import\Progress;

class Property extends AbstractResource
{
    /**
     * {@inheritdoc}
     */
    public function getDefaultErrorMessage()
    {
        return $this->getNameSpace()->get(
            'errorImportingProductProperties',
            'An error occurred while importing product properties'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentProgressMessage(Progress $progress)
    {
        return sprintf(
            $this->getNameSpace()->get('progressProductProperties', '%s out of %s product properties imported'),
            $this->getProgress()->getOffset(),
            $this->getProgress()->getCount()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getDoneMessage()
    {
        return $this->getNameSpace()->get('importProductProperties', 'Properties imported');
    }

    /**
     * {@inheritdoc}
     */
    public function run()
    {
        $offset = $this->getProgress()->getOffset();

        // Get ids of products with properties
        $result = $this->Source()->queryProductsWithProperties($offset);

        if (!$result || $result->rowCount() === 0) {
            return $this->getProgress()->done();
        }

        $count = $result->rowCount() + $offset;
        $this->getProgress()->setCount($count);

        $this->initTaskTimer();

        // Iterate those products, get properties, import them
        while ($product = $result->fetch()) {
            // Skip products which have not been imported before
            $productId = $this->getBaseArticleInfo($product['productID']);
            if ($productId === false) {
                $this->increaseProgress();
                continue;
            }

            // Get product's properties
            $property_result = $this->Source()->queryProductProperties($product['productID']);
            $options = [];
            $groupName = '';

            // Build nested array of properties
            while ($property = $property_result->fetch()) {
                // Skip empty properties
                if (empty($property['option']) || empty($property['value'])) {
                    continue;
                }

                // In SW a product can only have *ONE* property group associated
                if (empty($property['group'])
                    && isset($this->Request()->property_options[$property['option']])
                    && !empty($this->Request()->property_options[$property['option']])
                ) {
                    $property['group'] = $this->Request()->property_options[$property['option']];
                } elseif (empty($property['group'])) {
                    $property['group'] = 'Properties';
                }
                $groupName = $property['group'];

                // Create new element or extend existing
                if (!array_key_exists($property['option'], $options)) {
                    $options[$property['option']] = ['name' => $property['option'], 'values' => [['value' => $property['value']]]];
                } else {
                    $options[$property['option']]['values'][] = ['value' => $property['value']];
                }
            }

            if (!empty($groupName)) {
                $data = [
                    'productID' => $productId,
                    'group' => [
                        'name' => $groupName,
                        'options' => $options,
                    ],
                ];

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
     *    array(
     *        'productID' => 12,
     *        'group' =>    array (
     *             'id' => 33,
     *            'name' => 'Edelbrände',
     *            'position' => 0,
     *            'comparable' => true,
     *            'sortmode' => 3,
     *            'options' => array(
     *                'name' => 'test',
     *                'id' => 3,
     *                'filterable' => true,
     *                'values' => array(
     *                    array('position' => 3, 'value' => 'Mein Wert'),
     *                    array('position' => 3, 'value' => 'Mein Wert2')
     *                )
     *            )
     *        )
     *    )
     *
     * @param $data
     *
     * @return bool
     */
    public function importProductProperty($data)
    {
        $productId = $data['productID'];
        $group = $data['group'];

        $group['position'] = isset($group['position']) ? (int) $group['position'] : 0;
        $group['comparable'] = isset($group['comparable']) ? $group['comparable'] : false;
        $group['sortmode'] = isset($group['sortmode']) ? (int) $group['sortmode'] : 0;

        /*
         * Get existing group or create new one
         */
        if (isset($group['id'])) {
            $sql = 'SELECT `id` FROM `s_filter` WHERE `id` = ?';
            $groupId = Shopware()->Db()->fetchOne($sql, [$group['id']]);
        } elseif (isset($group['name'])) {
            $sql = 'SELECT `id` FROM `s_filter` WHERE `name` = ?';
            $groupId = Shopware()->Db()->fetchOne($sql, [$group['name']]);
        } else {
            error_log('no group info passed');

            return;
        }

        if ($groupId === false && isset($group['name'])) {
            $sql = 'INSERT INTO `s_filter` (`name`, `position`, `comparable`, `sortmode`) VALUES(?, ?, ?, ?)';
            Shopware()->Db()->query($sql, [$group['name'], $group['position'], $group['comparable'], $group['sortmode']]);
            $groupId = Shopware()->Db()->lastInsertId();
        }

        if ($groupId === false) {
            error_log('no group found');

            return false;
        }

        /*
         * Get existing options or create new ones
         */
        foreach ($group['options'] as &$option) {
            $option['filterable'] = isset($group['filterable']) ? (int) $group['filterable'] : 1;

            if (isset($option['id'])) {
                $sql = '
					SELECT o.`id`
					FROM `s_filter_options` o
					WHERE o.`id` = ?
				';
                $optionId = Shopware()->Db()->fetchOne($sql, [$option['id']]);
            } elseif (isset($option['name'])) {
                // First try to get option by name with associated group
                $sql = 'SELECT o.`id` FROM `s_filter_options` o
                        INNER JOIN `s_filter_relations` r
                        ON r.groupID = ? AND r.optionID = o.id WHERE o.`name` = ?';

                Shopware()->Db()->exec("SET NAMES 'utf8' COLLATE 'utf8_general_ci'");

                $optionId = Shopware()->Db()->fetchOne($sql, [$groupId, $option['name']]);

                // Then try to find option by name ignoring associated groups
                if ($optionId === false) {
                    $sql = '
						SELECT o.`id`
						FROM `s_filter_options` o
						WHERE o.`name` = ?
						LIMIT 1
						';
                    $optionId = Shopware()->Db()->fetchOne($sql, [$option['name']]);
                }
            }

            // Create option
            if ($optionId === false && isset($option['name'])) {
                $sql = 'INSERT INTO `s_filter_options` (`name`, `filterable`) VALUES(?, ?)';
                Shopware()->Db()->query($sql, [$option['name'], $option['filterable']]);
                $optionId = Shopware()->Db()->lastInsertId();
            }

            if ($optionId === false) {
                error_log('option not found');

                return false;
            }

            // Make sure that the group-option relations are set
            $sql = 'INSERT IGNORE INTO `s_filter_relations` (`groupID`, `optionID`) VALUES (?, ?)';
            Shopware()->Db()->query($sql, [$groupId, $optionId]);

            foreach ($option['values'] as &$value) {
                $value['position'] = isset($value['position']) ? $value['position'] : '';

                if (isset($value['id'])) {
                    $sql = '
						SELECT v.`id`
						FROM `s_filter_values` v
						WHERE v.`id` = ?
					';
                    $valueId = Shopware()->Db()->fetchOne($sql, [$value['id']]);
                } elseif (isset($value['value'])) {
                    // Try to get value by value with associated option

                    $sql = '
						SELECT v.`id`
						FROM `s_filter_values` v
						WHERE v.`value` = ?
						AND v.`optionID` = ?
						';
                    $valueId = Shopware()->Db()->fetchOne($sql, [$value['value'], $optionId]);
                }

                // Create option
                if ($valueId === false && isset($value['value'])) {
                    $sql = 'INSERT INTO `s_filter_values` (`value`, `optionId`, `position`) VALUES(?, ?, ?)';
                    Shopware()->Db()->query($sql, [$value['value'], $optionId, $value['position']]);
                    $valueId = Shopware()->Db()->lastInsertId();
                }

                if ($valueId === false) {
                    error_log('value not found');

                    return false;
                }

                // Finally assign filter values to article
                $sql = 'INSERT IGNORE INTO `s_filter_articles` (`articleID`, `valueID`) VALUES (?, ?)';
                Shopware()->Db()->query($sql, [$productId, $valueId]);
            }
            unset($value);
        }
        unset($option);

        // Set filter group for the given article
        Shopware()->Db()->query('UPDATE s_articles SET filtergroupID = ? WHERE id = ?', [$groupId, $productId]);
    }
}
