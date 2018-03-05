<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagMigration\Components\Migration\Profile;

use Shopware\SwagMigration\Components\Migration\Profile;

class Woocommerce extends Profile
{
    /**
     * Database prefix
     *
     * @var string
     */
    protected $db_prefix = 'wp_';

    /**
     * Returns a select for a rough estimation for the total number of entities
     *
     * @param $for
     *
     * @return string
     */
    public function getEstimationSelect($for)
    {
    }

    /**
     * Returns the directory of the article images.
     *
     * @return string {String} | image path
     */
    public function getProductImagePath()
    {
        return '/wp-content/uploads';
    }

    /**
     * Returns the shop system languages
     *
     * @return array {Array} | languages
     */
    public function getLanguages()
    {
        return $this->Config()->aLanguages;
    }

    /**
     * Returns the keys of the shop system languages
     *
     * @return array
     */
    public function getLanguageKeys()
    {
        $keys = [];
        $params = $this->Config()->aLanguageParams;
        foreach ($params as $id => $param) {
            $keys[$param['baseId']] = $id;
        }

        return $keys;
    }

    /**
     * @return string
     */
    public function getCustomerGroupSelect()
    {
        return "
			SELECT
			  `option_value` as 'name',
			  `option_value` as 'id'

			FROM {$this->quoteTable('options')}

            WHERE `option_name` = 'wp_user_roles'
		";
    }

    /**
     * Returns the sql statement to select the shop system sub shops
     *
     * @return string {String} | sql for sub shops
     */
    public function getShopSelect()
    {
        return "
			SELECT `option_id` as id, `option_name` as opt, `option_value` as name
			FROM {$this->quoteTable('options')}
            WHERE `option_name` = 'blogname'
		";
    }

    /**
     * Returns the sql statement to select the shop system languages
     *
     * @return string {String} | sql for languages
     */
    public function getLanguageSelect()
    {
        return "
			SELECT `option_id` as id, `option_name` as opt, `option_value` as name
			FROM {$this->quoteTable('options')}
            WHERE `option_name` = 'WPLANG'
		";
    }

    /**
     * Returns a query to select all available property options (for mapping)
     *
     * @return string
     */
    public function getPropertyOptionSelect()
    {
        return "
			SELECT DISTINCT

				meta.meta_key as 'name',
				meta.meta_key  as 'id'

			FROM {$this->quoteTable('posts')} post

            INNER JOIN {$this->quoteTable('postmeta')} meta
            ON meta.post_id = post.ID

			WHERE  post.post_type = 'product'
			ORDER BY meta.meta_key
		";
    }

    /**
     * Returns the sql statement to select the shop system articles
     *
     * @return string {String} | sql for the articles
     */
    public function getProductSelect()
    {
        return "
			SELECT
				a.ID        							as productID,

				a.post_date         					as added,
				a.post_status   						as active,

				a.post_title     						as name,
				a.post_content       					as description_long,
				a.post_excerpt               			as description,
				a.guid      							as link,

				m.meta_key                              as meta_key,
                m.meta_value                            as meta_value

			FROM {$this->quoteTable('posts', 'a')}

			LEFT JOIN {$this->quoteTable('postmeta', 'm')}
			ON a.ID = m.post_id
            WHERE  a.post_type = 'product'
		";
    }

    /**
     * Returns the sql statement to select the shop system categories
     *
     * @return string {String} | sql for the categories
     */
    public function getCategorySelect()
    {
        return "
			SELECT
				t.term_id as categoryID,
				tax.parent as parentID,
				t.name as description,

				t.name as meta_title,
				t.name as metaKeywords,
				tax.description as metaDescription,
				t.name as cmsheadline,
				tax.description as cmstext

			FROM
				{$this->quoteTable('terms', 't')}
            JOIN
				{$this->quoteTable('term_taxonomy', 'tax')}
			ON tax.term_id = t.term_id
			WHERE tax.taxonomy = 'product_cat'

			ORDER BY tax.parent
		";
    }

    /**
     * Returns the sql statement to select the shop system article category allocation
     *
     * @return string {String} | sql for the article category allocation
     */
    public function getProductCategorySelect()
    {
        return "
			SELECT r.object_id as productID, r.term_taxonomy_id as categoryID
			FROM
			  {$this->quoteTable('term_relationships', 'r')}
			JOIN
			  {$this->quoteTable('term_taxonomy', 'tax')}
            ON tax.term_taxonomy_id = r.term_taxonomy_id
            WHERE tax.taxonomy = 'product_cat'
		";
    }

    /**
     * Returns the sql statement to select the shop system customer
     *
     * @return string {String} | sql for the customer data
     */
    public function getOrderSelect()
    {
        return "
			SELECT
			    p.`ID`                                      as orderID,
			    p.`ID`                                      as sourceID,
				p.`post_password`							as ordernumber,
			  	p.`post_status`								as status,
                1                                           as subshopID,

                pm.`meta_key`                               as postMetaKey,
                pm.`meta_value`                             as postMetaValue,

                o.`order_item_name`                         as productName,
                o.`order_item_type`                         as productType,

                om.`meta_key`                               as orderMetaKey,
                om.`meta_value`                             as orderMetaValue

			FROM
				{$this->quoteTable('posts')} p

			LEFT JOIN {$this->quoteTable('postmeta')} pm
			ON p.ID=pm.post_id

			LEFT JOIN {$this->quoteTable('woocommerce_order_items')} o
			ON p.ID=o.order_id

			LEFT JOIN {$this->quoteTable('woocommerce_order_itemmeta')} om
			ON o.order_item_id=om.order_item_id

			WHERE p.post_type='shop_order'
		    ORDER BY p.ID
		";
    }

    /**
     * Returns the sql statement to select all shop system order details
     *
     * @return string {String} | sql for order details
     */
    public function getOrderDetailSelect()
    {
        return "
			SELECT

                p.ID                            as orderID,

			    oi.order_item_id                as itemId,
			    oi.order_item_name              as itemName,

                oim.meta_key                    as metaKey,
                oim.meta_value                  as metaValue

			FROM {$this->quoteTable('posts')} p

            JOIN {$this->quoteTable('woocommerce_order_items')} oi
            ON p.ID = oi.order_id

            JOIN {$this->quoteTable('woocommerce_order_itemmeta')} oim
            ON oi.order_item_id = oim.order_item_id

		";
    }

    /**
     * @param $productId
     *
     * @return string
     */
    public function getArticleNumberSelect($productId)
    {
        return "
			SELECT

                meta_value

			FROM {$this->quoteTable('postmeta')}

            WHERE meta_key LIKE '_sku' AND post_id = {$productId}

		";
    }

    /**
     * Returns the SUM of all order item totals to get the invoice_amount
     *
     * @param $orderId
     *
     * @return string
     */
    public function getOrderAmounts($orderId)
    {
        return "
			SELECT SUM(oim.meta_value) as invoice_amount

			FROM {$this->quoteTable('woocommerce_order_itemmeta')} oim
			JOIN {$this->quoteTable('woocommerce_order_items')} oi
			ON oi.order_item_id = oim.order_item_id

            WHERE oim.meta_key LIKE '_line_total' AND oi.order_id = {$orderId}
		";
    }

    /**
     * Returns the sql statement to select the shop system customer
     *
     * @return string {String} | sql for the customer data
     */
    public function getCustomerSelect()
    {
        return "
			SELECT

				u.ID            						as customerID,
				u.user_email							as email,
				u.user_registered						as firstlogin,
				u.user_status    						as active,
				u.user_pass          					as md5_password,
				'md5reversed'							as encoder,
                1                                       as subshopID,

                um.meta_key                             as metaKey,
                um.meta_value                           as metaValue

			FROM {$this->quoteTable('users')} u

			LEFT JOIN {$this->quoteTable('usermeta')} um
			ON u.ID=um.user_id
		";
    }

    /**
     * Returns the sql statement to select the shop system article ratings
     *
     * @return string {String} | sql for the article ratings
     */
    public function getProductRatingSelect()
    {
        return "
			SELECT
				p.`ID` as `productID`,

				co.`user_id` as `customerID`,
				co.`comment_author` as `name`,
				co.`comment_date_gmt` as `date`,
				co.`comment_content` as `comment`,

				com.`meta_key`      as `metaKey`,
				com.`meta_value`      as `metaValue`

			FROM {$this->quoteTable('posts')} p

			JOIN {$this->quoteTable('comments')} co
			ON p.`ID` = co.`comment_post_id`

            JOIN {$this->quoteTable('commentmeta')} com
			ON co.`comment_ID`=com.`comment_id`

			WHERE p.`post_type` = 'product'
			AND p.`comment_count` > 0
		";
    }

    /**
     * Returns the sql statement to select the shop system article prices
     *
     * @return string {String} | sql for the article prices
     */
    public function getProductPriceSelect()
    {
        return "
			SELECT
				p.`ID` as `productID`,

				pm.`meta_key`      as `metaKey`,
				pm.`meta_value`      as `metaValue`,
				''                  as `pricegroup`

			FROM {$this->quoteTable('posts')} p

			JOIN {$this->quoteTable('postmeta')} pm
			ON p.`ID` = pm.`post_id`

			WHERE p.`post_type` = 'product'
			AND pm.`meta_key` = '_price'
		";
    }

    /**
     * @return string {String} | sql for the article variants
     */
    public function getAttributedProductsSelect()
    {
        return "
            SELECT DISTINCT
				a.ID        							as variantID,

				a.post_date         					as added,
				a.post_status   						as active,
				a.post_parent                           as productID,

				a.post_title     						as name,
				a.post_content       					as description_long,
				a.post_excerpt               			as description,
				a.guid      							as link,

				m.meta_key                              as meta_key,
                m.meta_value                            as meta_value

			FROM {$this->quoteTable('posts', 'a')}

			LEFT JOIN {$this->quoteTable('postmeta', 'm')}
			ON a.ID = m.post_id
            WHERE  a.post_type = 'product_variation'
            AND m.meta_value != ''
        ";
    }

    /**
     * Returns a query to select all products with properties assigned
     *
     * @return string
     */
    public function getProductAttributesSelect()
    {
        return "
			SELECT DISTINCT
            p.ID        							as productID,

            pm.meta_value                           as metaValue,
            wca.attribute_label                     as option_name

            FROM {$this->quoteTable('posts', 'p')}

            JOIN {$this->quoteTable('postmeta', 'pm')}
            ON p.ID = pm.post_id

            JOIN {$this->quoteTable('woocommerce_attribute_taxonomies', 'wca')}
            ON pm.meta_value LIKE CONCAT('%', wca.attribute_name, '%')

            WHERE pm.meta_key = '_product_attributes'
		";
    }

    /**
     * Returns the sql statement to select the shop system article image allocation
     *
     * @return string {String} | sql for the article image allocation
     */
    public function getProductImageSelect()
    {
        return "
			SELECT
				p.post_parent  							as productID,
				p.menu_order  							as position,
				p.guid      							as image,
				p.post_name                             as name

			FROM {$this->quoteTable('posts')} p

            WHERE  p.post_type = 'attachment'
		";
    }

    /**
     * Returns the sql statement to select the shop system tax rates
     *
     * @return string {String} | sql for the tax rates
     */
    public function getTaxRateSelect()
    {
        return "
			SELECT
			  `tax_rate_id` as 'id',
			  `tax_rate` as 'name'

			FROM {$this->quoteTable('woocommerce_tax_rates')}
		";
    }

    /**
     * Returns an array of the order states mapping, with keys and descriptions
     *
     * @return array {Array} | order states: key - description
     */
    public function getOrderStatus()
    {
        return [
            'pending' => 'Pending Payment',
            'holded' => 'On Hold',
            'processing' => 'Processing',
            'complete' => 'Completed',
            'canceled' => 'Canceled',
            'refunded' => 'Refunded',
            'failed' => 'Failed',
        ];
    }
}
