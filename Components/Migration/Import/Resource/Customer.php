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
 * Shopware SwagMigration Components - Customer
 *
 * Customer import adapter
 *
 * @category  Shopware
 * @package Shopware\Plugins\SwagMigration\Components\Migration\Import\Resource
 * @copyright Copyright (c) 2012, shopware AG (http://www.shopware.de)
 */
class Shopware_Components_Migration_Import_Resource_Customer extends Shopware_Components_Migration_Import_Resource_Abstract
{

    /**
     * Returns the default error message for this import class
     * @return mixed
     */
    public function getDefaultErrorMessage()
    {
        return $this->getNameSpace()->get('errorImportingCustomers', "An error occurred while importing customers");
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
            $this->getNameSpace()->get('progressCustomers', "%s out of %s customers imported"),
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
        return $this->getNameSpace()->get('importedCustomers', "Customers successfully imported!");
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

        $salt = $this->Request()->salt;

        $result = $this->Source()->queryCustomers($offset);
        $count = $result->rowCount()+$offset;
        $this->getProgress()->setCount($count);

        $this->initTaskTimer();

        while ($customer = $result->fetch()) {
            if(isset($customer['customergroupID']) && isset($this->Request()->customer_group[$customer['customergroupID']])) {
                $customer['customergroup'] = $this->Request()->customer_group[$customer['customergroupID']];
            }
            unset($customer['customergroupID']);
            if(isset($customer['subshopID']) && isset($this->Request()->shop[$customer['subshopID']])) {
                $customer['subshopID'] = $this->Request()->shop[$customer['subshopID']];
            } else {
                unset($customer['subshopID']);
            }
            if(isset($customer['language']) && isset($this->Request()->language[$customer['language']])) {
                $customer['language'] = $this->Request()->language[$customer['language']];
            } else {
                unset($customer['language']);
            }
            if(!empty($customer['billing_countryiso'])) {
                $sql = 'SELECT `id` FROM `s_core_countries` WHERE `countryiso` = ?';
                $customer['billing_countryID'] = (int) Shopware()->Db()->fetchOne($sql , array($customer['billing_countryiso']));
            }
            if(isset($customer['shipping_countryiso'])) {
                $sql = 'SELECT `id` FROM `s_core_countries` WHERE `countryiso` = ?';
                $customer['shipping_countryID'] = (int) Shopware()->Db()->fetchOne($sql , array($customer['shipping_countryiso']));
            }

            if(!isset($customer['paymentID'])) {
                $customer['paymentID'] = Shopware()->Config()->Paymentdefault;
            }

            if(!empty($customer['md5_password']) && !empty($salt)) {
                $customer['md5_password'] = $customer['md5_password'] . ":" . $salt;
            }

            // If language is not set, read language from subshop
            if (empty($customer['language']) && !empty($customer['subshopID'])) {
                $sql = 'SELECT `locale_id` FROM s_core_shops WHERE id=?';
                $languageId = (int) Shopware()->Db()->fetchOne($sql, array($customer['subshopID']));
                if (!empty($languageId)) {
                    $customer['language'] = $languageId;
                }
            }


            if(!empty($customer['shipping_company'])||!empty($customer['shipping_firstname'])||!empty($customer['shipping_lastname'])) {
                $customer_shipping = array(
                    'company' => !empty($customer['shipping_company']) ? $customer['shipping_company'] : '',
                    'department' => !empty($customer['shipping_department']) ? $customer['shipping_department'] : '',
                    'salutation' => !empty($customer['shipping_salutation']) ? $customer['shipping_salutation'] : '',
                    'firstname' => !empty($customer['shipping_firstname']) ? $customer['shipping_firstname'] : '',
                    'lastname' => !empty($customer['shipping_lastname']) ? $customer['shipping_lastname'] : '',
                    'street' => !empty($customer['shipping_street']) ? $customer['shipping_street'] : '',
                    'streetnumber' => !empty($customer['shipping_streetnumber']) ? $customer['shipping_streetnumber'] : '',
                    'zipcode' => !empty($customer['shipping_zipcode']) ? $customer['shipping_zipcode'] : '',
                    'city' => !empty($customer['shipping_city']) ? $customer['shipping_city'] : '',
                    'countryID' => !empty($customer['shipping_countryID']) ? $customer['shipping_countryID'] : 0,
                );
                $customer['shipping_company'] = $customer['shipping_firstname'] = $customer['shipping_lastname'] = '';
            } else {
                $customer_shipping = array();
            }

            $customer_result = Shopware()->Api()->Import()->sCustomer($customer);

            if(!empty($customer_result)) {
                $customer = array_merge($customer, $customer_result);

                if(!empty($customer_shipping)) {
                    $customer_shipping['userID'] = $customer['userID'];
                    Shopware()->Db()->insert('s_user_shippingaddress', $customer_shipping);
                }

                if(!empty($customer['account'])) {
                    $this->importCustomerDebit($customer);
                }


                $sql = '
                    INSERT INTO `s_plugin_migrations` (`typeID`, `sourceID`, `targetID`)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE `targetID`=VALUES(`targetID`);
                ';
                Shopware()->Db()->query($sql , array(Shopware_Components_Migration::MAPPING_CUSTOMER, $customer['customerID'], $customer['userID']));
            }
            $this->increaseProgress();

            if ($this->newRequestNeeded()) {
                return $this->getProgress();
            }
        }

        return $this->getProgress()->done();

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