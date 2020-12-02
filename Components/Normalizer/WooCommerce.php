<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagMigration\Components\Normalizer;

/**
 * Helper to normalize all WooCommerce data from DB
 */
class WooCommerce
{
    /**
     * @var array
     */
    private $productMapping = [
        '_price' => 'net_price',
        '_weight' => 'weight',
    ];

    /**
     * @var array
     */
    private $ratingMapping = [
        'rating' => 'rating',
        'verified' => 'active',
    ];

    /**
     * @var array
     */
    private $customerMapping = [
        'first_name' => 'firstname',
        'last_name' => 'lastname',
        'billing_first_name' => 'billing_firstname',
        'billing_last_name' => 'billing_lastname',
        'billing_city' => 'billing_city',
        'billing_postcode' => 'billing_zipcode',
        'billing_country' => 'billing_countryiso',
        'billing_address_1' => 'billing_street',
        'shipping_first_name' => 'shipping_firstname',
        'shipping_last_name' => 'shipping_lastname',
        'shipping_city' => 'shipping_city',
        'shipping_postcode' => 'shipping_zipcode',
        'shipping_country' => 'shipping_countryiso',
        'shipping_address_1' => 'shipping_street',
    ];

    /**
     * @var array
     */
    private $orderMapping = [
        '_customer_user' => 'customerID',
        '_order_key' => 'ordernumber',
        '_payment_method' => 'paymentID',
        '_billing_first_name' => 'billing_firstname',
        '_billing_last_name' => 'billing_lastname',
        '_billing_city' => 'billing_city',
        '_billing_postcode' => 'billing_zipcode',
        '_billing_country' => 'billing_countryiso',
        '_billing_address_1' => 'billing_street',
        '_shipping_first_name' => 'shipping_firstname',
        '_shipping_last_name' => 'shipping_lastname',
        '_shipping_city' => 'shipping_city',
        '_shipping_postcode' => 'shipping_zipcode',
        '_shipping_country' => 'shipping_countryiso',
        '_shipping_address_1' => 'shipping_street',
        '_customer_user_agent' => 'referer',
        '_customer_ip_address' => 'remote_addr',
        'cost' => 'invoice_shipping_net',
        'shipping_tax_amount' => 'shippingTax',
        'tax_amount' => 'orderTaxRate',
        'method_id' => 'dispatchID',
    ];

    /**
     * @var array
     */
    private $orderDetailMapping = [
        '_qty' => 'quantity',
        '_product_id' => 'productID',
        '_line_tax' => 'taxID',
        '_line_total' => 'price',
    ];

    /**
     * @param array $products
     *
     * @return array
     */
    public function normalizeProducts($products)
    {
        $normalizedProducts = [];

        foreach ($products as $product) {
            if (!\array_key_exists($product['productID'], $normalizedProducts)) {
                $normalizedProducts[$product['productID']] = $product;
            } else {
                $normalizedProducts[$product['productID']][$this->mapArrayKey($product['meta_key'], $this->productMapping)] = $product['meta_value'];
            }
        }

        return $normalizedProducts;
    }

    /**
     * @param array $results
     *
     * @return array
     */
    public function normalizeShops($results)
    {
        $shops = [];

        foreach ($results as $result) {
            $shops[$result['id']] = $result['name'];
        }

        return $shops;
    }

    /**
     * @param array $results
     *
     * @return array
     */
    public function normalizeLanguages($results)
    {
        $languages = [];

        foreach ($results as $result) {
            $languages[] = $result['name'];
        }

        return $languages;
    }

    /**
     * @param string $key
     * @param array  $array
     *
     * @return string|mixed
     */
    public function mapArrayKey($key, $array)
    {
        if (\array_key_exists($key, $array)) {
            return $array[$key];
        }

        return $key;
    }

    /**
     * @param array $orders
     *
     * @return array
     */
    public function normalizeOrders($orders)
    {
        $normalizedOrders = [];

        foreach ($orders as $order) {
            if (!\array_key_exists($order['orderID'], $normalizedOrders)) {
                $order[$this->mapArrayKey($order['postMetaKey'], $this->orderMapping)] = $order['postMetaValue'];
                $order[$this->mapArrayKey($order['orderMetaKey'], $this->orderMapping)] = $order['orderMetaValue'];
                $normalizedOrders[$order['orderID']] = $order;
            } else {
                $normalizedOrders[$order['orderID']][$this->mapArrayKey($order['postMetaKey'], $this->orderMapping)] = $order['postMetaValue'];
                $normalizedOrders[$order['orderID']][$this->mapArrayKey($order['orderMetaKey'], $this->orderMapping)] = $order['orderMetaValue'];
            }
        }

        return $normalizedOrders;
    }

    /**
     * @param array $orderDetails
     *
     * @return array
     */
    public function normalizeOrderDetails($orderDetails)
    {
        $normalizedOrderDetails = [];
        foreach ($orderDetails as $order) {
            if (!\array_key_exists($order['orderID'], $normalizedOrderDetails)) {
                $order[$this->mapArrayKey($order['metaKey'], $this->orderDetailMapping)] = $order['metaValue'];
                $normalizedOrderDetails[$order['orderID']] = $order;
            } else {
                $normalizedOrderDetails[$order['orderID']][$this->mapArrayKey($order['metaKey'], $this->orderDetailMapping)] = $order['metaValue'];
            }
        }

        return $normalizedOrderDetails;
    }

    /**
     * @param array $customers
     *
     * @return array
     */
    public function normalizeCustomers($customers)
    {
        $normalizedCustomers = [];

        foreach ($customers as $customer) {
            if (!\array_key_exists($customer['customerID'], $normalizedCustomers)) {
                $normalizedCustomers[$customer['customerID']] = $customer;
            } else {
                $normalizedCustomers[$customer['customerID']][$this->mapArrayKey($customer['metaKey'], $this->customerMapping)] = $customer['metaValue'];
            }
        }

        foreach ($normalizedCustomers as $key => $customer) {
            if ($customer['firstname'] === '') {
                $normalizedCustomers[$key]['firstname'] = $customer['billing_firstname'];
            }
            if ($customer['lastname'] === '') {
                $normalizedCustomers[$key]['lastname'] = $customer['billing_lastname'];
            }
            $normalizedCustomers[$key]['salutation'] = 'mr';
            $normalizedCustomers[$key]['shipping_salutation'] = 'mr';
            $normalizedCustomers[$key]['billing_salutation'] = 'mr';
        }

        return $normalizedCustomers;
    }

    /**
     * @param array $ratings
     *
     * @return array
     */
    public function normalizeRatings($ratings)
    {
        $normalizedRatings = [];

        foreach ($ratings as $rating) {
            if (!\array_key_exists($rating['productID'], $normalizedRatings)) {
                $rating[$this->mapArrayKey($rating['metaKey'], $this->ratingMapping)] = $rating['metaValue'];
                $normalizedRatings[$rating['productID']] = $rating;
            } else {
                $normalizedRatings[$rating['productID']][$this->mapArrayKey($rating['metaKey'], $this->ratingMapping)] = $rating['metaValue'];
            }
        }

        return $normalizedRatings;
    }

    /**
     * @param array $variants
     *
     * @return array
     */
    public function normalizeVariants($variants)
    {
        $normalizedVariants = [];

        foreach ($variants as $variant) {
            if (!\array_key_exists($variant['productID'], $normalizedVariants)) {
                $variant[$this->mapArrayKey($variant['metaKey'], $this->ratingMapping)] = $variant['metaValue'];
                $normalizedVariants[$variant['productID']] = $variant;
            } else {
                $normalizedVariants[$variant['productID']][$this->mapArrayKey($variant['metaKey'], $this->ratingMapping)] = $variant['metaValue'];
            }
        }

        return $normalizedVariants;
    }
}
