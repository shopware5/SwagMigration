<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagMigration\Components\DbServices\Import;

use Enlight_Components_Db_Adapter_Pdo_Mysql as PDOConnection;
use Shopware\Components\Model\ModelManager;
use Shopware_Components_Config as Config;

class CustomerImporter
{
    /** @var array $customerFields */
    private $customerFields = [
        'email',
        'active',
        'accountmode',
        'paymentID',
        'firstlogin',
        'lastlogin',
        'newsletter',
        'firstname',
        'lastname',
        'salutation',
        'validation',
        'customergroup',
        'paymentpreset',
        'birthday' => 'birthday',
        'customernumber' => 'customernumber',
        'language',
        'subshopID',
        'referer',
        'encoder'
    ];

    /** @var array $billingFields */
    private $billingFields = [
        'userID' => 'userID',
        'company' => 'billing_company',
        'department' => 'billing_department',
        'salutation' => 'billing_salutation',
        'firstname' => 'billing_firstname',
        'lastname' => 'billing_lastname',
        'street' => 'billing_street',
        'zipcode' => 'billing_zipcode',
        'city' => 'billing_city',
        'countryID' => 'billing_countryID',
        'ustid' => 'ustid'
    ];

    /** @var array $billingFields */
    private $addressFields = [
        'user_id' => 'user_id',
        'company' => 'company',
        'department' => 'department',
        'salutation' => 'salutation',
        'firstname' => 'firstname',
        'lastname' => 'lastname',
        'street' => 'street',
        'zipcode' => 'zipcode',
        'city' => 'city',
        'phone' => 'phone',
        'country_id' => 'country_id',
        'ustid' => 'ustid'
    ];

    /** @var array $shippingAttributeFields */
    private $addressAttributeFields = [
        'address_id' => 'address_id',
        'text1' => 'text1',
        'text2' => 'text2',
        'text3' => 'text3',
        'text4' => 'text4',
        'text5' => 'text5',
        'text6' => 'text6'
    ];

    /** @var array $billingAttributeFields */
    private $billingAttributeFields = [
        'billingID' => 'billingaddressID',
        'text1' => 'billing_text1',
        'text2' => 'billing_text2',
        'text3' => 'billing_text3',
        'text4' => 'billing_text4',
        'text5' => 'billing_text5',
        'text6' => 'billing_text6'
    ];

    /** @var array $shippingFields */
    private $shippingFields = [
        'userID' => 'userID',
        'company' => 'shipping_company',
        'department' => 'shipping_department',
        'salutation' => 'shipping_salutation',
        'firstname' => 'shipping_firstname',
        'lastname' => 'shipping_lastname',
        'street' => 'shipping_street',
        'zipcode' => 'shipping_zipcode',
        'city' => 'shipping_city',
        'countryID' => 'shipping_countryID'
    ];

    /** @var array $shippingAttributeFields */
    private $shippingAttributeFields = [
        'shippingID' => 'shippingaddressID',
        'text1' => 'shipping_text1',
        'text2' => 'shipping_text2',
        'text3' => 'shipping_text3',
        'text4' => 'shipping_text4',
        'text5' => 'shipping_text5',
        'text6' => 'shipping_text6'
    ];

    /** @var PDOConnection $db */
    private $db = null;

    /** @var ModelManager $em */
    private $em = null;

    /** @var Config $config */
    private $config = null;

    /**
     * CustomerImporter constructor.
     *
     * @param PDOConnection $db
     * @param ModelManager $em
     * @param Config $config
     */
    public function __construct(PDOConnection $db, ModelManager $em, Config $config)
    {
        $this->db = $db;
        $this->em = $em;
        $this->config = $config;
    }

    /**
     * @param array $customer
     * @return bool|array
     */
    public function import(array $customer)
    {
        $isCustomer = $this->doesEntryExist('`s_user`', "`email` LIKE '{$customer['email']}'");

        $customer["addresses"] = $this->getCustomerAddresses($customer);

        if (!$isCustomer) {
            $customer = $this->prepareCustomerData($customer);
            if (empty($customer['userID']) && empty($customer['email'])) {
                return false;
            }

            if (empty($customer['userID']) && !empty($customer['email'])) {
                $customer['userID'] = $this->findExistingEntry('`s_user`', "`email` LIKE {$customer['email']}");
            }
            $customer = $this->createOrUpdateCustomer($customer);
            if ($customer === false) {
                return false;
            }

            $customer = $this->prepareBillingData($customer);
            $billingAddressId = $this->findExistingEntry('s_user_billingaddress', "userID = {$customer['userID']}");
            $customer['billingaddressID'] = $billingAddressId;
            $customer = $this->createOrUpdate($customer, 's_user_billingaddress', 'billingaddressID', $this->billingFields);
            if ($customer === false) {
                return false;
            }

            $billingAttributeId = $this->findExistingEntry(
                's_user_billingaddress_attributes',
                "billingID = {$customer['billingaddressID']}"
            );
            $customer = $this->createOrUpdate(
                $customer,
                's_user_billingaddress_attributes',
                $billingAttributeId,
                $this->billingAttributeFields
            );
            if ($customer === false) {
                return false;
            }

            if (!empty($customer['shipping_company'])
                || !empty($customer['shipping_firstname'])
                || !empty($customer['shipping_lastname'])
            ) {
                $customer = $this->prepareShippingData($customer);
                $shippingAddressId = $this->findExistingEntry('s_user_shippingaddress', "userID = {$customer['userID']}");
                $customer['shippingaddressID'] = $shippingAddressId;
                $customer = $this->createOrUpdate(
                    $customer,
                    's_user_shippingaddress',
                    'shippingaddressID',
                    $this->shippingFields
                );
                if ($customer === false) {
                    return false;
                }

                $shippingAttributeId = $this->findExistingEntry(
                    's_user_shippingaddress_attributes',
                    "shippingID = {$customer['shippingaddressID']}"
                );
                $customer = $this->createOrUpdate(
                    $customer,
                    's_user_shippingaddress_attributes',
                    $shippingAttributeId,
                    $this->shippingAttributeFields
                );
                if ($customer === false) {
                    return false;
                }
            } elseif (isset($customer['shipping_company'])
                || isset($customer['shipping_firstname'])
                || isset($customer['shipping_lastname'])
            ) {
                $customer = $this->prepareShippingData($customer);
                $shippingAddressId = $this->doesEntryExist('s_user_shippingaddress', "userID = {$customer['userID']}");

                if ($shippingAddressId) {
                    $customer['shippingaddressID'] = $shippingAddressId;

                    $billingFields = $this->billingFields;
                    unset($billingFields["ustid"]);

                    $customer = $this->createOrUpdate(
                        $customer,
                        's_user_shippingaddress',
                        'shippingaddressID',
                        $billingFields
                    );
                    if ($customer === false) {
                        return false;
                    }

                    $shippingAttributeId = $this->findExistingEntry(
                        's_user_shippingaddress_attributes',
                        "shippingID = {$customer['shippingaddressID']}"
                    );
                    $customer = $this->createOrUpdate(
                        $customer,
                        's_user_shippingaddress_attributes',
                        $shippingAttributeId,
                        $this->shippingAttributeFields
                    );
                    if ($customer === false) {
                        return false;
                    }
                } else {
                    $shippingAddressId = $this->findExistingEntry('s_user_shippingaddress', "userID = {$customer['userID']}");
                    $customer['shippingaddressID'] = $shippingAddressId;

                    $billingFields = $this->billingFields;
                    unset($billingFields["ustid"]);
                    $customer = $this->createOrUpdate(
                        $customer,
                        's_user_shippingaddress',
                        'shippingaddressID',
                        $billingFields
                    );
                    if ($customer === false) {
                        return false;
                    }

                    $shippingAttributeId = $this->findExistingEntry(
                        's_user_shippingaddress_attributes',
                        "shippingID = {$customer['shippingaddressID']}"
                    );
                    $customer['shippingAttributesID'] = $shippingAttributeId;
                    $customer = $this->createOrUpdate(
                        $customer,
                        's_user_shippingaddress_attributes',
                        'shippingAttributesID',
                        $this->shippingAttributeFields
                    );
                    if ($customer === false) {
                        return false;
                    }
                }
            }

            $customer['customernumber'] = $this->getCustomerNumber($customer['userID']);

            $customer = $this->newsletterSubscribe($customer);
        } else {
            $customer['userID'] = $isCustomer;
            $customer['customernumber'] = $this->getCustomerNumber($customer['userID']);

            $customer = $this->newsletterSubscribe($customer);
        }

        foreach ($customer["addresses"] as $address) {
            $address = $this->prepareAddressData($address);
            $address["user_id"] = $customer['userID'];
            if ($address["salutation"] === null) {
                $address["salutation"] = $customer["billing_salutation"];
            }

            $address = $this->createOrUpdate($address, 's_user_addresses', 'address_id', $this->addressFields);

            if ($address === false) {
                continue;
            }

            $addressAttributeId = $this->findExistingEntry(
                's_user_addresses_attributes',
                "address_id = {$address['address_id']}"
            );
            $this->createOrUpdate(
                $address,
                's_user_addresses_attributes',
                $addressAttributeId,
                $this->addressAttributeFields
            );
        }

        $this->setDefaultBillingAddress($customer['userID']);
        $this->setDefaultShippingAddress($customer['userID']);

        return [
            'userID' => $customer['userID'],
            'customernumber' => $customer['customernumber'],
            'password' => $customer['password'],
            'billingaddressID' => $customer['billingaddressID'],
            'shippingaddressID' => $customer['shippingaddressID'],
        ];
    }

    /**
     * @param array $customer
     * @return array
     */
    private function prepareCustomerData(array $customer)
    {
        if (isset($customer['firstname'])) {
            $customer['firstname'] = $this->db->quote((string)$customer['firstname']);
        }
        if (isset($customer['lastname'])) {
            $customer['lastname'] = $this->db->quote((string)$customer['lastname']);
        }
        if (isset($customer['salutation'])) {
            $customer['salutation'] = $this->db->quote((string)$customer['salutation']);
        }
        if (isset($customer['password'])) {
            $customer['password'] = trim($customer['password'], '\r\n');
        }
        if (empty($customer['md5_password']) && !empty($customer['password'])) {
            $customer['md5_password'] = md5($customer['password']);
        }
        if (isset($customer['md5_password'])) {
            $customer['md5_password'] = $this->db->quote($customer['md5_password']);
        }
        if (isset($customer['encoder'])) {
            $customer['encoder'] = $this->db->quote($customer['encoder']);
        }
        if (isset($customer['email'])) {
            $customer['email'] = empty($customer['email']) ? $customer['email'] : $this->db->quote(trim($customer['email']));
        }
        if (isset($customer['language'])) {
            $customer['language'] = $this->db->quote((string)$customer['language']);
        }
        if (isset($customer['referer'])) {
            $customer['referer'] = $this->db->quote((string)$customer['referer']);
        }
        if (isset($customer['accountmode'])) {
            $customer['accountmode'] = empty($customer['accountmode']) ? 0 : 1;
        }
        if (isset($customer['newsletter'])) {
            $customer['newsletter'] = empty($customer['newsletter']) ? 0 : 1;
        }
        if (isset($customer['paymentID'])) {
            $customer['paymentID'] = intval($customer['paymentID']);
        }
        if (isset($customer['paymentpreset'])) {
            $customer['paymentpreset'] = intval($customer['paymentpreset']);
        }
        if (isset($customer['subshopID'])) {
            $customer['subshopID '] = intval($customer['subshopID']);
        }
        if (isset($customer['userID'])) {
            $customer['userID'] = intval($customer['userID']);
        }
        if (isset($customer['validation'])) {
            $customer['validation'] = $this->db->quote((string)$customer['validation']);
        } else {
            $customer['validation'] = $this->db->quote('');
        }
        if (isset($customer['active'])) {
            $customer['active'] = empty($customer['active']) ? 0 : 1;
        } else {
            $customer['active'] = 1;
        }
        if (isset($customer['birthday'])) {
            $customer['birthday'] = $this->toDate($customer['birthday']);
        }
        if (isset($customer["billing_countryID"])) {
            $customer["countryID"] = $customer["billing_countryID"];
        }

        $customer['customergroup'] = empty($customer['customergroup']) ? $this->db->quote('EK') : $this->db->quote(
            (string)$customer['customergroup']
        );
        $customer['firstlogin'] = empty($customer['firstlogin']) ? $this->db->quote((string)date('Y-m-d')) : $this->toDate($customer['firstlogin']);
        $customer['lastlogin'] = empty($customer['lastlogin']) ? $this->db->quote((string)date('Y-m-d H:i:s')) : $this->toTimeStamp($customer['lastlogin']);

        return $customer;
    }

    /**
     * @param string $table
     * @param string $where
     * @return int
     */
    private function findExistingEntry($table, $where)
    {
        $sql = "SELECT id FROM $table WHERE $where";
        $id = (int)$this->db->fetchOne($sql);

        return $id;
    }

    /**
     * @param string $table
     * @param string $where
     * @return int
     */
    private function doesEntryExist($table, $where)
    {
        $sql = "SELECT id FROM $table WHERE $where";
        $id = $this->db->fetchOne($sql);

        return $id;
    }

    /**
     * @param array $customer
     * @return bool|array
     */
    private function createOrUpdateCustomer(array $customer)
    {
        if (empty($customer['userID'])) {
            list($customer['password'], $customer['md5_password']) = $this->setNewPassword($customer['password'], $customer['md5_password']);

            $insertFields = [];
            $insertValues = [];
            foreach ($this->customerFields as $field) {
                if (isset($customer[$field])) {
                    $insertFields[] = $field;
                    $insertValues[] = $customer[$field];
                }
            }
            $insertFields[] = 'password';
            $insertValues[] = $customer['md5_password'];

            $sql = 'INSERT INTO s_user (' . implode(', ', $insertFields) . ')
                    VALUES (' . implode(', ', $insertValues) . ')';

            $result = $this->db->query($sql);
            if ($result === false) {
                return false;
            }

            $customer['userID'] = (int)$this->db->lastInsertId();
        } else {
            $updateData = [];
            foreach ($this->customerFields as $field) {
                if (isset($customer[$field])) {
                    $updateData[] = $field . '=' . $customer[$field];
                }
            }

            if (isset($customer['md5_password'])) {
                $updateData[] = 'password=' . $customer['md5_password'];
            }

            if (!empty($updateData)) {
                $updateData = implode(', ', $updateData);
                $sql = "UPDATE s_user
                        SET $updateData
                        WHERE id = {$customer['userID']}";
                $this->db->query($sql);
            }
        }

        return $customer;
    }

    /**
     * @param string $password
     * @param string $md5Password
     * @return array
     */
    private function setNewPassword($password, $md5Password)
    {
        if (empty($password) && empty($md5Password)) {
            $newPassword = '';
            for ($i = 0; $i < 10; $i++) {
                $randomNumber = mt_rand(0, 35);
                $newPassword .= ($randomNumber < 10) ? $randomNumber : chr($randomNumber + 87);
            }

            $password = $newPassword;
            $md5Password = $this->db->quote(md5($newPassword));
        }

        return [$password, $md5Password];
    }

    /**
     * @param array $customer
     * @return array
     */
    private function prepareBillingData(array $customer)
    {
        if (isset($customer['billing_company'])) {
            $customer['billing_company'] = $this->db->quote((string)$customer['billing_company']);
        }
        if (isset($customer['billing_department'])) {
            $customer['billing_department'] = $this->db->quote((string)$customer['billing_department']);
        }
        if (isset($customer['billing_salutation'])) {
            $customer['billing_salutation'] = $this->db->quote((string)$customer['billing_salutation']);
        }
        if (isset($customer['billing_firstname'])) {
            $customer['billing_firstname'] = $this->db->quote((string)$customer['billing_firstname']);
        }
        if (isset($customer['billing_lastname'])) {
            $customer['billing_lastname'] = $this->db->quote((string)$customer['billing_lastname']);
        }
        if (isset($customer['billing_street'])) {
            $customer['billing_street'] = $this->db->quote((string)$customer['billing_street']);
        }
        if (isset($customer['billing_zipcode'])) {
            $customer['billing_zipcode'] = $this->db->quote((string)$customer['billing_zipcode']);
        }
        if (isset($customer['billing_city'])) {
            $customer['billing_city'] = $this->db->quote((string)$customer['billing_city']);
        }
        if (isset($customer['phone'])) {
            $customer['phone'] = $this->db->quote((string)$customer['phone']);
        }
        if (isset($customer['ustid'])) {
            $customer['ustid'] = $this->db->quote((string)$customer['ustid']);
        }
        if (isset($customer['billing_countryID'])) {
            $customer['billing_countryID'] = intval($customer['billing_countryID']);
        }
        if (isset($customer['customernumber'])) {
            $customer['customernumber'] = $this->db->quote((string)$customer['customernumber']);
        }
        if (isset($customer['birthday'])) {
            $customer['birthday'] = $this->toDate($customer['birthday']);
        }
        if (empty($customer['billing_countryID']) && !empty($customer['billing_countryiso'])) {
            $customer['billing_countryID'] = (int)$this->getCountryID(['iso' => $customer['billing_countryiso']]);
        }

        // billing address attributes
        for ($i = 1; $i < 7; $i++) {
            if (isset($customer["billing_text$i"])) {
                $customer["billing_text$i"] = $this->db->quote((string)$customer["billing_text$i"]);
            }
        }

        return $customer;
    }

    /**
     * Returns country id
     *
     * @param string $countryIso
     * @return int|bool
     */
    private function getCountryID($countryIso)
    {
        if (empty($countryIso)) {
            return false;
        }

        $countryIso = $this->db->quote(trim((string)$countryIso));

        $sql = "SELECT id
                FROM s_core_countries
                WHERE countryiso = $countryIso";
        $result = $this->db->fetchOne($sql);

        return $result;
    }

    /**
     * @param array $customer
     * @param string $table
     * @param string $key
     * @param array $dbFields
     * @return bool|array
     */
    private function createOrUpdate(array $customer, $table, $key, array $dbFields)
    {
        $id = is_numeric($key) ? $key : $customer[$key];
        if (empty($id)) {
            $insertFields = [];
            $insertValues = [];
            foreach ($dbFields as $dbField => $field) {
                if (isset($customer[$field])) {
                    $insertFields[] = $dbField;
                    $insertValues[] = $customer[$field];
                }
            }

            $sql = "INSERT INTO $table (" . implode(', ', $insertFields) . ")
                    VALUES (" . implode(', ', $insertValues) . ")";

            $result = $this->db->query($sql);
            if ($result === false) {
                return false;
            }

            $customer[$key] = (int)$this->db->lastInsertId();
        } else {
            $updateData = [];
            foreach ($dbFields as $dbField => $field) {
                if (isset($customer[$field])) {
                    $updateData[] = $dbField . '=' . $customer[$field];
                }
            }

            if (count($updateData) > 1) {
                $updateData = implode(', ', $updateData);
                $sql = "
                    UPDATE $table SET $updateData
                    WHERE id = $id
                ";

                $this->db->query($sql);
            }
        }

        return $customer;
    }

    /**
     * @param array $customer
     * @return array
     */
    private function prepareShippingData(array $customer)
    {
        if (isset($customer['shipping_company'])) {
            $customer['shipping_company'] = $this->db->quote((string)$customer['shipping_company']);
        }
        if (isset($customer['shipping_department'])) {
            $customer['shipping_department'] = $this->db->quote((string)$customer['shipping_department']);
        }
        if (isset($customer['shipping_salutation'])) {
            $customer['shipping_salutation'] = $this->db->quote((string)$customer['shipping_salutation']);
        }
        if (isset($customer['shipping_firstname'])) {
            $customer['shipping_firstname'] = $this->db->quote((string)$customer['shipping_firstname']);
        }
        if (isset($customer['shipping_lastname'])) {
            $customer['shipping_lastname'] = $this->db->quote((string)$customer['shipping_lastname']);
        }
        if (isset($customer['shipping_street'])) {
            $customer['shipping_street'] = $this->db->quote((string)$customer['shipping_street']);
        }
        if (isset($customer['shipping_zipcode'])) {
            $customer['shipping_zipcode'] = $this->db->quote((string)$customer['shipping_zipcode']);
        }
        if (isset($customer['shipping_city'])) {
            $customer['shipping_city'] = $this->db->quote((string)$customer['shipping_city']);
        }
        if (isset($customer['shipping_countryID'])) {
            $customer['shipping_countryID'] = intval($customer['shipping_countryID']);
        }
        if (empty($customer['shipping_countryID']) && !empty($customer['shipping_countryiso'])) {
            $customer['shipping_countryID'] = (int)$this->getCountryID($customer['shipping_countryiso']);
        }

        // shipping address attributes
        for ($i = 1; $i < 7; $i++) {
            if (isset($customer["shipping_text$i"])) {
                $customer["shipping_text$i"] = $this->db->quote((string)$customer["shipping_text$i"]);
            }
        }

        return $customer;
    }

    /**
     * @param array $address
     * @return array
     */
    private function prepareAddressData(array $address)
    {
        if (isset($address['company'])) {
            $address['company'] = $this->db->quote((string)$address['company']);
        }
        if (isset($address['department'])) {
            $address['department'] = $this->db->quote((string)$address['department']);
        }
        if (isset($address['salutation'])) {
            $address['salutation'] = $this->db->quote((string)$address['salutation']);
        }
        if (isset($address['firstname'])) {
            $address['firstname'] = $this->db->quote((string)$address['firstname']);
        }
        if (isset($address['lastname'])) {
            $address['lastname'] = $this->db->quote((string)$address['lastname']);
        }
        if (isset($address['street'])) {
            $address['street'] = $this->db->quote((string)$address['street']);
        }
        if (isset($address['zipcode'])) {
            $address['zipcode'] = $this->db->quote((string)$address['zipcode']);
        }
        if (isset($address['city'])) {
            $address['city'] = $this->db->quote((string)$address['city']);
        }
        if (isset($address['countryID'])) {
            $address['country_id'] = (int)$this->getCountryID($address['countryID']);
        } else {
            $address['country_id'] = $this->getDefaultCountryId();
        }

        // shipping address attributes
        for ($i = 1; $i < 7; $i++) {
            if (isset($address["address_text$i"])) {
                $address["address_text$i"] = $this->db->quote((string)$address["address_text$i"]);
            }
        }

        return $address;
    }

    /**
     * @param int $userId
     * @return string
     */
    private function getCustomerNumber($userId)
    {
        $sql = 'SELECT customernumber
                FROM s_user
                WHERE id = ' . $userId;
        $customerNumber = $this->db->fetchOne($sql);
        if ($this->config->get('sSHOPWAREMANAGEDCUSTOMERNUMBERS') && empty($customerNumber)) {
            $sql = "UPDATE s_order_number n, s_user b
                    SET n.number = n.number + 1, b.customernumber = n.number + 1
                    WHERE n.name = 'user'
                      AND b.id = ?";
            $this->db->query($sql, [$userId]);
            $sql = 'SELECT customernumber
                    FROM s_user
                    WHERE id = ' . $userId;
            $customerNumber = $this->db->fetchOne($sql);
        }

        return $customerNumber;
    }

    /**
     * @param array $customer
     * @return array
     */
    private function newsletterSubscribe(array $customer)
    {
        if (!isset($customer['newsletter'])) {
            return $customer;
        }

        if (empty($customer['newsletter'])) {
            $sql = 'DELETE FROM s_campaigns_mailaddresses
                    WHERE email = "' . $customer['email'] . '"';
            $this->db->query($sql);
        } else {
            $customer['newslettergroupID'] = $this->getNewsletterGroupId($customer['newslettergroupID']);

            $sql = 'SELECT id
                    FROM s_campaigns_mailaddresses
                    WHERE email = "' . $customer['email'] . '"';
            $result = $this->db->fetchOne($sql);
            if (empty($result)) {
                $sql = "INSERT INTO s_campaigns_mailaddresses (customer, groupID, email)
                        VALUES (1, {$customer['newslettergroupID']}, {$customer['email']});";
                $this->db->query($sql);
            }
        }

        return $customer;
    }

    /**
     * @param int|string $newsletterGroupId
     * @return int
     */
    private function getNewsletterGroupId($newsletterGroupId)
    {
        if (empty($newsletterGroupId)) {
            $defaultNewsletterGroup = $this->config->get('sNEWSLETTERDEFAULTGROUP');
            $newsletterGroupId = empty($defaultNewsletterGroup) ? 1 : (int)$defaultNewsletterGroup;
        } else {
            $newsletterGroupId = intval($newsletterGroupId);
        }

        return $newsletterGroupId;
    }

    /**
     * Returns database timestamp
     *
     * @param string $timestamp
     * @return string
     */
    private function toDate($timestamp)
    {
        if (empty($timestamp) && $timestamp !== 0) {
            return 'null';
        }
        $date = new \Zend_Date($timestamp);

        return $this->db->quote($date->toString('Y-m-d', 'php'));
    }

    /**
     * Returns database timestamp
     *
     * @param string $timestamp
     * @return string
     */
    private function toTimeStamp($timestamp)
    {
        if (empty($timestamp) && $timestamp !== 0) {
            return 'null';
        }
        $date = new \Zend_Date($timestamp);

        return $this->db->quote($date->toString('Y-m-d H:i:s', 'php'));
    }

    /**
     * Helper function to build an array of all addresses, so they can be stored in the database.
     *
     * @param array $customer
     * @return array
     */
    private function getCustomerAddresses(array $customer)
    {
        if (!isset($customer["country_id"])) {
            $customer["country_id"] = $this->getDefaultCountryId();
        }
        $addresses = [];

        $addresses[] = [
            'userID' => $customer['userID'],
            'company' => $customer['billing_company'],
            'department' => $customer['billing_company'],
            'salutation' => $customer['billing_salutation'],
            'firstname' => $customer['billing_firstname'],
            'lastname' => $customer['billing_lastname'],
            'street' => $customer['billing_street'],
            'zipcode' => $customer['billing_zipcode'],
            'city' => $customer['billing_city'],
            'country_id' => $customer["country_id"]
        ];

        if (!empty($customer['shipping_company'])
            || !empty($customer['shipping_firstname'])
            || !empty($customer['shipping_street'])
            || !empty($customer['shipping_lastname'])
        ) {
            if ($customer['shipping_firstname'] == $customer['billing_firstname'] &&
                $customer['shipping_lastname'] == $customer['billing_lastname']) {
                $addresses[] = [
                    'userID' => $customer['userID'],
                    'company' => $customer['shipping_company'],
                    'department' => $customer['shipping_company'],
                    'salutation' => $customer['shipping_salutation'],
                    'firstname' => (!empty($customer['shipping_firstname']) ? $customer['shipping_firstname'] : $customer["firstname"]),
                    'lastname' => (!empty($customer['shipping_lastname']) ? $customer['shipping_lastname'] : $customer["lastname"]),
                    'street' => $customer['shipping_street'],
                    'zipcode' => $customer['shipping_zipcode'],
                    'city' => $customer['shipping_city'],
                    'country_id' => $customer["country_id"]
                ];
            }
        }

        return $addresses;
    }

    /**
     * Helper function to get a default country id in case that none is set.
     *
     * @return int
     */
    private function getDefaultCountryId()
    {
        return 2;
    }

    /**
     * @param integer|string $customerId
     * @return bool
     */
    private function setDefaultBillingAddress($customerId)
    {
        $sql = "SELECT id FROM s_user_addresses WHERE user_id = ? LIMIT 1";
        $billingId = $this->db->query($sql, $customerId)->fetchColumn();

        $sql = "UPDATE s_user SET default_billing_address_id = ? WHERE id = ?;";
        return $this->db->query($sql, [$billingId, $customerId])->execute();
    }

    /**
     * @param string|integer $customerId
     * @return bool
     */
    private function setDefaultShippingAddress($customerId)
    {
        $sql = "SELECT id FROM s_user_addresses WHERE user_id = ? LIMIT 1";
        $shippingId = $this->db->query($sql, $customerId)->fetchColumn();

        $sql = "UPDATE s_user SET default_shipping_address_id = ? WHERE id = ?;";
        return $this->db->query($sql, [$shippingId, $customerId])->execute();
    }
}
