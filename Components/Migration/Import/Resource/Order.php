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
use Shopware\SwagMigration\Components\Normalizer\WooCommerce;

class Order extends AbstractResource
{
    /**
     * {@inheritdoc}
     */
    public function getDefaultErrorMessage()
    {
        if ($this->getInternalName() == 'import_orders') {
            return $this->getNameSpace()->get('errorImportingOrders', 'An error occurred while importing orders');
        }

        if ($this->getInternalName() == 'import_order_details') {
            return $this->getNameSpace()->get(
                'errorImportingOrderDetails',
                'An error occurred while importing order details'
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentProgressMessage(Progress $progress)
    {
        if ($this->getInternalName() == 'import_orders') {
            return \sprintf(
                $this->getNameSpace()->get('progressOrders', '%s out of %s orders imported'),
                $this->getProgress()->getOffset(),
                $this->getProgress()->getCount()
            );
        }

        if ($this->getInternalName() == 'import_order_details') {
            return \sprintf(
                $this->getNameSpace()->get('progressOrderDetails', '%s out of %s order details imported'),
                $this->getProgress()->getOffset(),
                $this->getProgress()->getCount()
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDoneMessage()
    {
        return $this->getNameSpace()->get('importedOrders', 'Orders successfully imported!');
    }

    /**
     * {@inheritdoc}
     */
    public function run()
    {
        if ($this->getInternalName() == 'import_orders') {
            return $this->importOrders();
        }

        if ($this->getInternalName() == 'import_order_details') {
            return $this->importOrderDetails();
        }
    }

    /**
     * This function import all orders from the source profile database into the shopware database.
     *
     * @return $this|Progress
     */
    public function importOrders()
    {
        $call = \array_merge($this->Request()->getPost(), $this->Request()->getQuery());
        $offset = $this->getProgress()->getOffset();

        $result = $this->Source()->queryOrders($offset);

        if (empty($result)) {
            return $this->getProgress()->done();
        }

        $count = $result->rowCount() + $offset;
        $this->getProgress()->setCount($count);

        $this->initTaskTimer();

        if ($call['profile'] !== 'WooCommerce') {
            while ($order = $result->fetch()) {
                $this->migrateOrder($order);
            }
        } elseif ($call['profile'] === 'WooCommerce') {
            $normalizer = new WooCommerce();
            $normalizedOrders = $normalizer->normalizeOrders($result->fetchAll());

            foreach ($normalizedOrders as $order) {
                $orderAmounts = $this->Source()->queryOrderAmounts($order)->fetchAll();
                $order['invoice_amount'] = $orderAmounts[0]['invoice_amount'];

                if (\array_key_exists('orderTaxRate', $order)) {
                    $order['invoice_amount'] += $order['orderTaxRate'];
                }

                if (\array_key_exists('invoice_shipping_net', $order)) {
                    $order['invoice_shipping'] = $order['invoice_shipping_net'] + $order['shippingTax'];
                    $order['invoice_amount'] += $order['invoice_shipping'];
                }

                $this->migrateOrder($order);
            }
        }

        // Force import of order details in the next step
        $this->getProgress()->addRequestParam('import_order_details', true);

        return $this->getProgress()->done();
    }

    /**
     * This function imports all order details from the source profile into the showpare database
     *
     * @return $this|Progress
     */
    public function importOrderDetails()
    {
        $call = \array_merge($this->Request()->getPost(), $this->Request()->getQuery());
        $offset = $this->getProgress()->getOffset();
        $numberValidationMode = $this->Request()->getParam('number_validation_mode', 'complain');

        $numberSnippet = $this->getNameSpace()->get(
            'numberNotValid',
            "The product number '%s' is not valid. A valid product number must:<br>
            * not be longer than 40 chars<br>
            * not contain other chars than: 'a-zA-Z0-9-_.'<br>
            <br>
            You can force the migration to continue. But be aware that this will: <br>
            * Truncate ordernumbers longer than 40 chars and therefore result in 'duplicate keys' exceptions <br>
            * Will not allow you to modify and save articles having an invalid ordernumber <br>
            "
        );

        $result = $this->Source()->queryOrderDetails($offset);
        $count = $result->rowCount() + $offset;
        $this->getProgress()->setCount($count);

        $this->initTaskTimer();

        if ($call['profile'] !== 'WooCommerce') {
            while ($order = $result->fetch()) {
                $this->migrateOrderDetail($order, $numberValidationMode, $numberSnippet);
            }
        } elseif ($call['profile'] === 'WooCommerce') {
            $normalizer = new WooCommerce();
            $normalizedOrderDetails = $normalizer->normalizeOrderDetails($result->fetchAll());

            foreach ($normalizedOrderDetails as $order) {
                $res = $this->Source()->queryOrderDetailArticleNumber($order)->fetch();
                $order['article_ordernumber'] = $res['meta_value'];

                $this->migrateOrderDetail($order, $numberValidationMode, $numberSnippet);
            }
        }

        return $this->getProgress()->done();
    }

    /**
     * This Function does the real migration. It had to be outsourced because of the different
     * format types of some profiles.
     *
     * @return Progress
     */
    private function migrateOrder(array $order = [])
    {
        if (isset($order['languageID']) && isset($this->Request()->language[$order['languageID']])) {
            $order['languageID'] = $this->Request()->language[$order['languageID']];
        }
        if (isset($order['subshopID']) && isset($this->Request()->shop[$order['subshopID']])) {
            $order['subshopID'] = $this->Request()->shop[$order['subshopID']];
        }
        if (isset($order['statusID']) && isset($this->Request()->order_status[$order['statusID']])) {
            $order['statusID'] = $this->Request()->order_status[$order['statusID']];
        }
        if (isset($order['paymentID']) && isset($this->Request()->payment_mean[$order['paymentID']])) {
            $order['paymentID'] = $this->Request()->payment_mean[$order['paymentID']];
        } else {
            $order['paymentID'] = Shopware()->Config()->Paymentdefault;
        }

        $sql = 'SELECT `targetID` FROM `s_plugin_migrations` WHERE `typeID`=? AND `sourceID`=?';
        $order['userID'] = Shopware()->Db()->fetchOne($sql, [Migration::MAPPING_CUSTOMER, $order['customerID']]);

        $order['sourceID'] = $order['orderID'];
        $sql = 'SELECT `targetID` FROM `s_plugin_migrations` WHERE `typeID`=? AND `sourceID`=?';
        $order['orderID'] = Shopware()->Db()->fetchOne($sql, [Migration::MAPPING_ORDER, $order['orderID']]);

        $data = [
            'ordernumber' => $order['ordernumber'],
            'invoice_amount' => !empty($order['invoice_amount']) ? $order['invoice_amount'] : 0,
            'invoice_amount_net' => !empty($order['invoice_amount_net']) ? $order['invoice_amount_net'] : 0,
            'userID' => $order['userID'],
            'invoice_shipping' => !empty($order['invoice_shipping']) ? $order['invoice_shipping'] : 0,
            'invoice_shipping_net' => !empty($order['invoice_shipping_net']) ? $order['invoice_shipping_net'] : 0,
            'ordertime' => isset($order['date']) ? $order['date'] : new \Zend_Db_Expr('NOW()'),
            'status' => !empty($order['statusID']) ? (int) $order['statusID'] : 0,
            'cleared' => !empty($order['clearedID']) ? (int) $order['clearedID'] : 17,
            'paymentID' => (int) $order['paymentID'],
            'transactionID' => isset($order['transactionID']) ? $order['transactionID'] : '',
            'customercomment' => isset($order['customercomment']) ? $order['customercomment'] : '',
            'net' => !empty($order['tax_free']) || !empty($order['net']) ? 1 : 0,
            'taxfree' => !empty($order['tax_free']) ? 1 : 0,
            'referer' => isset($order['referer']) ? $order['referer'] : '',
            'cleareddate' => isset($order['cleared_date']) ? $order['cleared_date'] : null,
            'trackingcode' => isset($order['trackingID']) ? $order['trackingID'] : '',
            'language' => !empty($order['languageID']) ? $order['languageID'] : '1',
            'dispatchID' => !empty($order['dispatchID']) ? (int) $order['dispatchID'] : 0,
            'currency' => !empty($order['currency']) ? $order['currency'] : 'EUR',
            'currencyFactor' => !empty($order['currency_factor']) ? $order['currency_factor'] : 1,
            'subshopID' => isset($order['subshopID']) ? $order['subshopID'] : 0,
            'remote_addr' => isset($order['remote_addr']) ? $order['remote_addr'] : '',
        ];

        if ($data['cleareddate'] === '0000-00-00 00:00:00') {
            $data['cleareddate'] = null;
        }

        if (!empty($order['orderID'])) {
            Shopware()->Db()->update('s_order', $data, ['id=?' => $order['orderID']]);
            $sql = 'DELETE FROM `s_order_details` WHERE `orderID`=?';
            Shopware()->Db()->query($sql, [$order['orderID']]);
        } else {
            $order['insert'] = Shopware()->Db()->insert('s_order', $data);
            $order['orderID'] = Shopware()->Db()->lastInsertId();

            $sql = 'INSERT INTO `s_plugin_migrations` (`typeID`, `sourceID`, `targetID`)
	            VALUES (?, ?, ?)
	            ON DUPLICATE KEY UPDATE `targetID`=VALUES(`targetID`);
	            ';
            Shopware()->Db()->query($sql, [Migration::MAPPING_ORDER, $order['sourceID'], $order['orderID']]);
        }

        if (!empty($order['billing_countryiso'])) {
            $sql = 'SELECT `id` FROM `s_core_countries` WHERE `countryiso` = ?';
            $order['billing_countryID'] = (int) Shopware()->Db()->fetchOne($sql, [$order['billing_countryiso']]);
        }
        if (isset($order['shipping_countryiso'])) {
            $sql = 'SELECT `id` FROM `s_core_countries` WHERE `countryiso` = ?';
            $order['shipping_countryID'] = (int) Shopware()->Db()->fetchOne($sql, [$order['shipping_countryiso']]);
        }

        $data_attributes = [
            'orderID' => $order['orderID'],
            'attribute1' => !empty($order['attr1']) ? $order['attr1'] : null,
            'attribute2' => !empty($order['attr2']) ? $order['attr2'] : null,
            'attribute3' => !empty($order['attr3']) ? $order['attr3'] : null,
            'attribute4' => !empty($order['attr4']) ? $order['attr4'] : null,
            'attribute5' => !empty($order['attr5']) ? $order['attr5'] : null,
            'attribute6' => !empty($order['attr6']) ? $order['attr6'] : null,
        ];

        $data_billing = [
            'userID' => $order['userID'],
            'orderID' => $order['orderID'],
            'company' => !empty($order['billing_company']) ? $order['billing_company'] : '',
            'department' => !empty($order['billing_department']) ? $order['billing_department'] : '',
            'salutation' => !empty($order['billing_salutation']) ? $order['billing_salutation'] : '',
            'customernumber' => !empty($order['billing_customernumber']) ? $order['billing_customernumber'] : '',
            'firstname' => !empty($order['billing_firstname']) ? $order['billing_firstname'] : '',
            'lastname' => !empty($order['billing_lastname']) ? $order['billing_lastname'] : '',
            'street' => !empty($order['billing_street']) ? $order['billing_street'] : '',
            'zipcode' => !empty($order['billing_zipcode']) ? $order['billing_zipcode'] : '',
            'city' => !empty($order['billing_city']) ? $order['billing_city'] : '',
            'phone' => !empty($order['phone']) ? $order['phone'] : '',
            'countryID' => !empty($order['billing_countryID']) ? $order['billing_countryID'] : 0,
            'ustid' => !empty($order['billing_ustid']) ? $order['billing_ustid'] : '',
        ];

        $data_shipping = [
            'orderID' => $order['orderID'],
            'userID' => $order['userID'],
            'company' => !empty($order['shipping_lastname']) ? $order['shipping_company'] : $data_billing['company'],
            'department' => !empty($order['shipping_lastname']) && !empty($order['shipping_department']) ? $order['shipping_department'] : $data_billing['department'],
            'salutation' => !empty($order['shipping_lastname']) && !empty($order['shipping_salutation']) ? $order['shipping_salutation'] : $data_billing['salutation'],
            'firstname' => !empty($order['shipping_lastname']) ? $order['shipping_firstname'] : $data_billing['firstname'],
            'lastname' => !empty($order['shipping_lastname']) ? $order['shipping_lastname'] : $data_billing['lastname'],
            'street' => !empty($order['shipping_lastname']) ? $order['shipping_street'] : $data_billing['street'],
            'zipcode' => !empty($order['shipping_lastname']) ? $order['shipping_zipcode'] : $data_billing['zipcode'],
            'city' => !empty($order['shipping_lastname']) ? $order['shipping_city'] : $data_billing['city'],
            'countryID' => !empty($order['shipping_lastname']) && !empty($order['shipping_countryID']) ? $order['shipping_countryID'] : $data_billing['countryID'],
        ];

        foreach ($data_billing as $key => $attribute) {
            if ($attribute === null) {
                $data_billing[$key] = '';
            }
        }
        foreach ($data_shipping as $key => $attribute) {
            if ($attribute === null) {
                $data_shipping[$key] = '';
            }
        }

        if (empty($order['insert'])) {
            Shopware()->Db()->update(
                's_order_billingaddress',
                $data_billing,
                ['orderID=?' => $order['orderID']]
            );
            Shopware()->Db()->update(
                's_order_shippingaddress',
                $data_shipping,
                ['orderID=?' => $order['orderID']]
            );
            Shopware()->Db()->update(
                's_order_attributes',
                $data_attributes,
                ['orderID=?' => $order['orderID']]
            );
        } else {
            Shopware()->Db()->insert('s_order_billingaddress', $data_billing);
            Shopware()->Db()->insert('s_order_shippingaddress', $data_shipping);
            Shopware()->Db()->insert('s_order_attributes', $data_attributes);
        }

        $this->increaseProgress();
        if ($this->newRequestNeeded()) {
            return $this->getProgress();
        }
    }

    /**
     * This Function does the real migration. It had to be outsourced because of the different
     * format types of some profiles.
     *
     * @param string $numberValidationMode
     * @param string $numberSnippet
     *
     * @return Progress
     */
    private function migrateOrderDetail(array $order, $numberValidationMode, $numberSnippet)
    {
        // Check the ordernumber
        $number = $order['article_ordernumber'];
        if (!isset($number)) {
            $number = '';
        }

        if (empty($number)) {
            Shopware()->Container()->get('pluginlogger')
                ->error("Order '{$order['orderID']}' was not imported because the Article Ordernumber was emtpy.");

            return;
        }

        if (\strpos($number, '#')) {
            $number = \str_replace('#', '', $number);
        }

        if ($numberValidationMode !== 'ignore'
            && (empty($number) || \strlen($number) > 30 || \strlen($number) < 4
                || \preg_match('/[^a-zA-Z0-9-_.]/', $number))
        ) {
            switch ($numberValidationMode) {
                case 'complain':
                    echo \Zend_Json::encode(
                        [
                            'message' => \sprintf($numberSnippet, $number),
                            'success' => false,
                            'import_products' => null,
                            'offset' => 0,
                            'progress' => -1,
                        ]
                    );

                    return;
                    break;
                case 'make_valid':
                    $order['article_ordernumber'] = $this->makeInvalidNumberValid($number, $order['productID']);
                    break;
            }
        }

        $sql = 'SELECT `targetID` FROM `s_plugin_migrations` WHERE `typeID`=? AND `sourceID`=?';
        $order['orderID'] = Shopware()->Db()->fetchOne($sql, [Migration::MAPPING_ORDER, $order['orderID']]);

        $sql = '
                SELECT ad.articleID
                FROM s_plugin_migrations pm
                JOIN s_articles_details ad
                ON ad.id=pm.targetID
                WHERE pm.`sourceID`=?
                AND (`typeID`=? OR `typeID`=?)
            ';
        $order['articleID'] = $this->Target()->Db()->fetchOne(
            $sql,
            [
                $order['productID'],
                Migration::MAPPING_ARTICLE,
                Migration::MAPPING_VALID_NUMBER,
            ]
        );

        // TaxRate
        if (!empty($this->Request()->tax_rate) && isset($order['taxID'])) {
            if (isset($this->Request()->tax_rate[$order['taxID']])) {
                $order['taxID'] = $this->Request()->tax_rate[$order['taxID']];
            } else {
                unset($order['taxID']);
            }
        }
        if (!empty($order['tax']) && empty($order['taxID'])) {
            $sql = 'SELECT `id` FROM `s_core_tax` WHERE `tax`=?';
            $order['taxID'] = Shopware()->Db()->fetchOne($sql, [$order['tax']]);
        }

        if (!empty($order['articleID']) && empty($order['taxID'])) {
            $sql = 'SELECT `taxID` FROM `s_articles` WHERE `id`=?';
            $order['taxID'] = Shopware()->Db()->fetchOne($sql, [$order['articleID']]);
        }

        $data = [
            'orderID' => $order['orderID'],
            'articleID' => isset($order['articleID']) ? (int) $order['articleID'] : 0,
            'articleordernumber' => $order['article_ordernumber'],
            'ordernumber' => !empty($order['ordernumber']) ? $order['ordernumber'] : '',
            'name' => $order['name'],
            'price' => $order['price'],
            'taxID' => !empty($order['taxID']) ? $order['taxID'] : 0,
            'quantity' => !empty($order['quantity']) ? $order['quantity'] : 1,
            'modus' => !empty($order['modus']) ? $order['modus'] : 0,
        ];

        foreach ($data as $key => $attribute) {
            if ($attribute === null) {
                $data[$key] = '';
            }
        }

        try {
            Shopware()->Db()->insert('s_order_details', $data);

            $data_attributes = [
                'detailID' => Shopware()->Db()->lastInsertId(),
                'attribute1' => !empty($order['attr1']) ? $order['attr1'] : null,
                'attribute2' => !empty($order['attr2']) ? $order['attr2'] : null,
                'attribute3' => !empty($order['attr3']) ? $order['attr3'] : null,
                'attribute4' => !empty($order['attr4']) ? $order['attr4'] : null,
                'attribute5' => !empty($order['attr5']) ? $order['attr5'] : null,
                'attribute6' => !empty($order['attr6']) ? $order['attr6'] : null,
            ];
            Shopware()->Db()->insert('s_order_details_attributes', $data_attributes);
        } catch (\Exception $ex) {
            Shopware()->Container()->get('pluginlogger')
                ->error(
                    'Error while importing order with details: '
                        . self::toOneLine(print_r($order, true))
                        . ' with $data array: ' . self::toOneLine(print_r($data, true))
                        . ' with $data_attributes array: ' . self::toOneLine(
                            print_r(isset($data_attributes) ? $data_attributes : [], true)
                        ),
                    [
                        'plugin' => 'SwagMigration',
                        'class' => __CLASS__,
                        'method' => __METHOD__,
                        'exception' => $ex->getMessage(),
                        'exceptionType' => \get_class($ex),
                        'exceptionCode' => $ex->getCode(),
                    ]
                );
        }

        $this->increaseProgress();
        if ($this->newRequestNeeded()) {
            return $this->getProgress();
        }
    }

    private static function toOneLine($str)
    {
        return str_replace(
            "\n",
            '',
            str_replace(
                "\r\n",
                '',
                $str
            )
        );
    }
}
