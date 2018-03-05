<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagMigration\Components\Migration\Import\Resource;

use Shopware;
use Shopware\SwagMigration\Components\DbServices\Import\Import;
use Shopware\SwagMigration\Components\Migration;
use Shopware\SwagMigration\Components\Migration\Import\Progress;
use Shopware\SwagMigration\Components\Normalizer\WooCommerce;

/**
 * Shopware SwagMigration Components - Customer
 *
 * Customer import adapter
 *
 * @category  Shopware
 *
 * @copyright Copyright (c) 2012, shopware AG (http://www.shopware.de)
 */
class Customer extends AbstractResource
{
    /**
     * {@inheritdoc}
     */
    public function getDefaultErrorMessage()
    {
        return $this->getNameSpace()->get('errorImportingCustomers', 'An error occurred while importing customers');
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentProgressMessage(Progress $progress)
    {
        return sprintf(
            $this->getNameSpace()->get('progressCustomers', '%s out of %s customers imported'),
            $this->getProgress()->getOffset(),
            $this->getProgress()->getCount()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getDoneMessage()
    {
        return $this->getNameSpace()->get('importedCustomers', 'Customers successfully imported!');
    }

    /**
     * {@inheritdoc}
     */
    public function run()
    {
        $call = array_merge($this->Request()->getPost(), $this->Request()->getQuery());
        $offset = $this->getProgress()->getOffset();

        $salt = $this->Request()->salt;

        $result = $this->Source()->queryCustomers($offset);

        if (empty($result)) {
            return $this->getProgress()->done();
        }

        $count = $result->rowCount() + $offset;
        $this->getProgress()->setCount($count);

        $this->initTaskTimer();

        /* @var Import $import */
        $import = Shopware()->Container()->get('swagmigration.import');

        if ($call['profile'] !== 'WooCommerce') {
            while ($customer = $result->fetch()) {
                $this->migrateCustomer($customer, $import, $salt);
            }
        } elseif ($call['profile'] === 'WooCommerce') {
            $normalizer = new WooCommerce();
            $normalizedCustomers = $normalizer->normalizeCustomers($result->fetchAll());

            foreach ($normalizedCustomers as $customer) {
                $this->migrateCustomer($customer, $import, $salt);
            }
        }

        return $this->getProgress()->done();
    }

    /**
     * Import the customer debit
     *
     * @param array $customer
     *
     * @return bool
     */
    public function importCustomerDebit(array $customer)
    {
        $fields = [
            'account' => false,
            'bankcode' => false,
            'bankholder' => false,
            'bankname' => false,
            'userID' => false,
        ];

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

    /**
     * @param $customer
     * @param $import
     * @param $salt
     *
     * @throws \Zend_Db_Adapter_Exception
     *
     * @return Progress
     */
    private function migrateCustomer($customer, $import, $salt)
    {
        if (isset($customer['customergroupID'])
            && isset($this->Request()->customer_group[$customer['customergroupID']])
        ) {
            $customer['customergroup'] = $this->Request()->customer_group[$customer['customergroupID']];
        }
        unset($customer['customergroupID']);

        $customer['subshopID'] = 1;
        if (isset($customer['subshopID']) && isset($this->Request()->shop[$customer['subshopID']])) {
            $customer['subshopID'] = $this->Request()->shop[$customer['subshopID']];
        }
        $customer['language'] = 1;
        if (isset($customer['language']) && isset($this->Request()->language[$customer['language']])) {
            $customer['language'] = $this->Request()->language[$customer['language']];
        }

        if (!empty($customer['billing_countryiso'])) {
            $sql = 'SELECT `id` FROM `s_core_countries` WHERE `countryiso` = ?';
            $customer['billing_countryID'] = (int) Shopware()->Db()->fetchOne($sql, [$customer['billing_countryiso']]);
        }
        if (isset($customer['shipping_countryiso'])) {
            $sql = 'SELECT `id` FROM `s_core_countries` WHERE `countryiso` = ?';
            $customer['shipping_countryID'] = (int) Shopware()->Db()->fetchOne($sql, [$customer['shipping_countryiso']]);
        }

        if (!isset($customer['paymentID'])) {
            $customer['paymentID'] = Shopware()->Config()->Paymentdefault;
        }

        if (!empty($customer['md5_password']) && !empty($salt)) {
            $customer['md5_password'] = $customer['md5_password'] . ':' . $salt;
        }

        // If language is not set, read language from subshop
        if (empty($customer['language']) && !empty($customer['subshopID'])) {
            $sql = 'SELECT `locale_id` FROM s_core_shops WHERE id=?';
            $languageId = (int) Shopware()->Db()->fetchOne($sql, [$customer['subshopID']]);
            if (!empty($languageId)) {
                $customer['language'] = $languageId;
            }
        }

        if (!empty($customer['billing_street']) && !empty($customer['billing_streetnumber'])) {
            $customer['billing_street'] = $customer['billing_street'] . ' ' . $customer['billing_streetnumber'];
        }

        if (!empty($customer['shipping_company']) || !empty($customer['shipping_firstname']) || !empty($customer['shipping_lastname'])) {
            $customer_shipping = [
                'company' => !empty($customer['shipping_company']) ? $customer['shipping_company'] : '',
                'department' => !empty($customer['shipping_department']) ? $customer['shipping_department'] : '',
                'salutation' => !empty($customer['shipping_salutation']) ? $customer['shipping_salutation'] : '',
                'firstname' => !empty($customer['shipping_firstname']) ? $customer['shipping_firstname'] : '',
                'lastname' => !empty($customer['shipping_lastname']) ? $customer['shipping_lastname'] : '',
                'street' => !empty($customer['shipping_street']) ? $customer['shipping_street'] : '',
                'zipcode' => !empty($customer['shipping_zipcode']) ? $customer['shipping_zipcode'] : '',
                'city' => !empty($customer['shipping_city']) ? $customer['shipping_city'] : '',
                'countryID' => !empty($customer['shipping_countryID']) ? $customer['shipping_countryID'] : 0,
            ];
            $customer['shipping_company'] = $customer['shipping_firstname'] = $customer['shipping_lastname'] = '';

            $customer['streetnumber'] = !empty($customer['shipping_streetnumber']) ? $customer['shipping_streetnumber'] : '';
        } else {
            $customer_shipping = [];
        }

        $customer_result = $import->customer($customer);

        if (!empty($customer_result)) {
            $customer = array_merge($customer, $customer_result);

            if (!empty($customer['account'])) {
                $this->importCustomerDebit($customer);
            }

            $sql = '
                    INSERT INTO `s_plugin_migrations` (`typeID`, `sourceID`, `targetID`)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE `targetID`=VALUES(`targetID`);
                ';
            Shopware()->Db()->query(
                $sql,
                [
                    Migration::MAPPING_CUSTOMER,
                    $customer['customerID'],
                    $customer['userID'],
                ]
            );
        }
        $this->increaseProgress();

        if ($this->newRequestNeeded()) {
            return $this->getProgress();
        }
    }
}
