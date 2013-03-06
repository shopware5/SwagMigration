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
 * Shopware SwagMigration Components - Profile
 *
 * @category  Shopware
 * @package Shopware\Plugins\SwagMigration\Components
 * @copyright Copyright (c) 2012, shopware AG (http://www.shopware.de)
 */
abstract class Shopware_Components_Migration_AttributeHelper extends Enlight_Class
{
    public $source;


    public function Source()
    {
        return $this->source;
    }

    /**
     * Class constructor
     * @param $profileSource
     */
	public function __construct($profileSource)
	{
		$this->source = $profileSource;
	}


    /**
     * Helper function which creates a cartesian product
     * @param $arrays
     * @return array
     */
    public function createCartesianProduct($arrays)
    {
        $cartesian = array();
        $groups = array_reverse($arrays);

        foreach ($groups as $group_name => $options) {
            $buf = array();

            foreach ($options as $option) {
                $buf[] = array($group_name => $option);
            }

            if (!count($cartesian)) {
                $cartesian = $buf;
            } else {
                $tmp = array();
                foreach ($buf as $el_buf)
                    foreach ($cartesian as $el_ap)
                        $tmp[] = array_merge($el_buf, $el_ap);
                $cartesian = $tmp;
            }

        }
        return $cartesian;
    }
}