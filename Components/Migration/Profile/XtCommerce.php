<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagMigration\Components\Migration\Profile;

use Exception;
use Shopware\SwagMigration\Components\Migration\Profile;

class XtCommerce extends Profile
{
    /**
     * Returns the directory of the article images.
     *
     * @return string {String} | image path
     */
    public function getProductImagePath()
    {
        return 'images/product_images/original_images/';
    }

    /**
     * Returns the sql statement to select default shop system language
     *
     * @return string {String} | sql for default language
     */
    public function getDefaultLanguageSelect()
    {
        return "SELECT `languages_id` FROM {$this->quoteTable('languages')} ORDER BY `sort_order` ASC";
    }

    /**
     * Returns the sql statement to select the shop system languages
     *
     * @return string {String} | sql for languages
     */
    public function getLanguageSelect()
    {
        return "
			SELECT `languages_id` as id, `name`
			FROM {$this->quoteTable('languages')}
		";
    }

    /**
     * Returns the property options of the shop
     */
    public function getPropertyOptionSelect()
    {
        return "
   			SELECT DISTINCT
   			  o.products_options_name as name,
   			  o.products_options_name as id

   			FROM {$this->quoteTable('products_attributes', 'p')}

   			INNER JOIN {$this->quoteTable('products_options', 'o')}
   			ON p.options_id = o.products_options_id
            AND o.language_id = {$this->Db()->quote($this->getDefaultLanguage())}
   		";
    }

    /**
     * Returns a dummy SQL statement with a default shop
     *
     * @return string {String} | sql for sub shops
     */
    public function getShopSelect()
    {
        return "
			SELECT 1 as id, 'Default' as name
		";
    }

    /**
     * Returns the sql statement to select the shop system customer groups
     *
     * @return string {String} | sql for customer groups
     */
    public function getCustomerGroupSelect()
    {
        return "
			SELECT `customers_status_id` as id, `customers_status_name` as name
			FROM {$this->quoteTable('customers_status')}
			WHERE language_id={$this->Db()->quote($this->getDefaultLanguage())}
		";
    }

    /**
     * Returns the sql statement to select the shop system payments
     *
     * @return string {String} | sql for the payments
     */
    public function getPaymentMeanSelect()
    {
        return "
			SELECT `payment_method` as id, `payment_class` as name
			FROM {$this->quoteTable('orders')}
			GROUP BY `payment_class`
		";
    }

    /**
     * Returns the sql statement to select the shop system order states
     *
     * @return string {String} | sql for the order states
     */
    public function getOrderStatusSelect()
    {
        return "
			SELECT `orders_status_id` as id, `orders_status_name` as name
			FROM {$this->quoteTable('orders_status')}
			WHERE `language_id`={$this->Db()->quote($this->getDefaultLanguage())}
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
			SELECT `tax_class_id` as id, `tax_class_title` as name
			FROM {$this->quoteTable('tax_class')}
		";
    }

    /**
     * Returns the sql statement to select the shop system article attributes
     *
     * @return string {String} | sql for the article attributes
     */
    public function getAttributeSelect()
    {
        return "
				SELECT 'asin' as id, 'ASIN' as name
		";
    }

    /**
     * Returns the sql statement to select articles with
     *
     * @param $id
     *
     * @return string
     */
    public function getProductPropertiesSelect($id)
    {
        return "
            SELECT
                ''                                      as 'group',
                po.products_options_name                as 'option',
                p.products_id                           as productId,
                pv.products_options_values_name         as 'value'

            FROM {$this->quoteTable('products', 'p')}

            INNER JOIN {$this->quoteTable('products_attributes', 'a')}
            ON p.products_id=a.products_id

            INNER JOIN {$this->quoteTable('products_options', 'po')}
            ON po.products_options_id = a.options_id

            INNER JOIN {$this->quoteTable('products_options_values', 'pv')}
            ON pv.products_options_values_id = a.options_values_id
            AND pv.language_id = po.language_id

            WHERE po.language_id = {$this->Db()->quote($this->getDefaultLanguage())}
            AND a.products_id = {$id}
        ";
    }

    /**
     * Get ids of all products with properties
     *
     * @return string
     */
    public function getProductsWithPropertiesSelect()
    {
        return $this->getAttributedProductsSelect();
    }

    /**
     * Get productIds for all products with attributes
     *
     * @return string
     */
    public function getAttributedProductsSelect()
    {
        return "
            SELECT
            DISTINCT a.products_id as productID

            FROM  {$this->quoteTable('products_attributes', 'a')}
        ";
    }

    /**
     * Select attributes for a given article
     *
     * @param $id
     *
     * @return string
     */
    public function getProductAttributesSelect($id)
    {
        return "
            SELECT
                po.products_options_name                as group_name,
                p.products_id                           as productId,
                pv.products_options_values_name          as option_name,
                IF(a.price_prefix='+', a.options_values_price, CONCAT('-', a.options_values_price)) as price,
                a.sortorder                             as option_position

            FROM {$this->quoteTable('products', 'p')}

            INNER JOIN {$this->quoteTable('products_attributes', 'a')}
            ON p.products_id=a.products_id

            INNER JOIN {$this->quoteTable('products_options', 'po')}
            ON po.products_options_id = a.options_id

            INNER JOIN {$this->quoteTable('products_options_values', 'pv')}
            ON pv.products_options_values_id = a.options_values_id
            AND pv.language_id = po.language_id

            WHERE po.language_id = {$this->Db()->quote($this->getDefaultLanguage())}
            AND p.products_id = {$id}

--            GROUP BY po.products_options_name
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
				a.products_id							as productID,

				a.products_quantity						as instock,
				-- a.products_average_quantity			as stockmin,
				-- a.products_shippingtime					as shippingtime,
				a.products_model						as ordernumber,
				-- a.products_image						as image,

				a.products_price						as net_price,

				a.products_date_available 				as releasedate,
				a.products_date_added					as added,
				-- a.last_modified 						as changed,
				a.products_weight						as weight,
				a.products_tax_class_id					as taxID,
				s.manufacturers_name					as supplier,
				a.products_status						as active,

				a.products_fsk18						as fsk18,
				a.products_ean							as ean,

				d.products_name 						as name,
				d.products_description 					as description_long,
				d.products_short_description 			as description,
				d.products_keywords 					as tags,
				d.products_meta_title					as meta_title,
				d.products_meta_description 			as meta_description,
				d.products_meta_keywords 				as keywords,
				d.products_url							as link

			FROM {$this->quoteTable('products', 'a')}

			LEFT JOIN {$this->quoteTable('manufacturers', 's')}
			ON s.manufacturers_id=a.manufacturers_id

			LEFT JOIN {$this->quoteTable('products_description', 'd')}
			ON d.products_id=a.products_id
			AND d.language_id={$this->Db()->quote($this->getDefaultLanguage())}

		";
    }

    /**
     * Returns the sql statement to select the shop system article prices
     *
     * @return string {String} | sql for the article prices
     */
    public function getProductPriceSelect()
    {
        $sql = "
			SELECT `customers_status_id`
			FROM {$this->quoteTable('customers_status')}
			WHERE `customers_status_graduated_prices`=1
		";
        $price_groups = $this->db->fetchCol($sql);

        $sql = [];

        if (!empty($price_groups)) {
            foreach ($price_groups as $price_group) {
                $sql[] = "
					SELECT
						`products_id` as productID,
						`quantity` as `from`,
						`personal_offer` as `price`,
						'$price_group' as pricegroup
					FROM {$this->quoteTable('personal_offers_by_customers_status_' . $price_group)}
					WHERE `personal_offer`!=0
					ORDER BY productID, `from`
				";
            }
        }

        return '(' . implode(') UNION ALL (', $sql) . ')';
    }

    /**
     * Returns the sql statement to select the shop system article image allocation
     *
     * @return string {String} | sql for the article image allocation
     */
    public function getProductImageSelect()
    {
        return "
			(
				SELECT `products_id` as productID, `products_image` as image, 1 as main, 0 as position
				FROM {$this->quoteTable('products')}
				WHERE `products_image`!='' AND `products_image` IS NOT NULL
			) UNION ALL (
				SELECT `products_id` as productID, `image_name` as image, 0 as main, image_nr as position
				FROM {$this->quoteTable('products_images')}
			)
		";
    }

    /**
     * Returns the sql statement to select the shop system article translations
     *
     * @return string {String} | sql for the article translations
     */
    public function getProductTranslationSelect()
    {
        return "
			SELECT
				d.products_id 					as productID,
				d.language_id 					as languageID,
				d.products_name 				as name,
				d.products_description 			as description_long,
				d.products_keywords 			as tags,
				d.products_meta_title			as meta_title,
				d.products_meta_description 	as description,
				d.products_meta_keywords		as keywords
			FROM {$this->quoteTable('products_description', 'd')}
			WHERE `language_id`!={$this->Db()->quote($this->getDefaultLanguage())}
		";
    }

    /**
     * Returns the sql statement to select the shop system article relations
     *
     * @return string {String} | sql for the article relations
     */
    public function getProductRelationSelect()
    {
        return "
			SELECT `products_id` as productID, `xsell_id` as relatedID, `products_xsell_grp_name_id` as groupID
			FROM {$this->quoteTable('products_xsell')}
		";
    }

    /**
     * This function creates an database index on the orders table
     *
     * @param int $offset
     *
     * @return \Zend_Db_Statement_Interface
     */
    public function queryCustomers($offset = 0)
    {
        if ($offset === 0) {
            try {
                $sql = 'DROP INDEX customers_id ON orders;';
                $this->Db()->exec($sql);
            } catch (Exception $e) {
            }
            try {
                $sql = 'ADD INDEX customers_id ON orders;';
                $this->Db()->exec($sql);
            } catch (Exception $e) {
            }
        }

        return parent::queryCustomers($offset);
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
				u.customers_id 										as customerID,
				u.customers_id 										as customernumber,
				IF(a.entry_gender IN ('m', 'Herr'), 'mr', 'ms')		as salutation,
				a.entry_firstname									as firstname,
				a.entry_lastname 	 								as lastname,

				IF(a.entry_gender IN ('m', 'Herr'), 'mr', 'ms')		as billing_salutation,
				a.entry_firstname									as billing_firstname,
				a.entry_lastname 	 								as billing_lastname,
				a.entry_company		 								as billing_company,
				'' 													as billing_department,
				a.entry_street_address	 							as billing_street,
				'' 													as billing_streetnumber,
				a.entry_postcode 									as billing_zipcode,
				a.entry_city	 									as billing_city,
				c.countries_iso_code_2 								as billing_countryiso,

				''                          			as shipping_salutation,
				''							            as shipping_company,
				''							            as shipping_firstname,
				'' 							            as shipping_lastname,
				'' 							            as shipping_street,
				''  									as shipping_streetnumber,
				''								        as shipping_city,
				''							            as shipping_countryiso,
				''							            as shipping_zipcode,

				u.customers_telephone 								as phone,
				u.customers_email_address 							as email,
				DATE(u.customers_dob)								as birthday,
				u.customers_vat_id 									as ustid,
				u.customers_newsletter								as newsletter,

				u.customers_password 								as md5_password,

				u.customers_status									as customergroupID,

				u.customers_date_added 								as firstlogin,
				u.customers_date_added								as lastlogin,
				1													as active,
				1               									as subshopID


			FROM {$this->quoteTable('customers', 'u')}

			JOIN {$this->quoteTable('address_book', 'a')}
			ON a.customers_id=u.customers_id
			AND a.address_book_id=u.customers_default_address_id

			LEFT JOIN {$this->quoteTable('countries', 'c')}
			ON c.countries_id=a.entry_country_id
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
			SELECT `products_id` as productID, `categories_id` as categoryID
			FROM {$this->quoteTable('products_to_categories')}
			ORDER BY `products_id`
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
				co.categories_id as categoryID,
				parent_id as parentID,
				language_id as languageID,
				categories_name as description,
				sort_order as position,
				categories_meta_keywords as meta_title,
				categories_meta_keywords as metaKeywords,
				categories_meta_description as metaDescription,
				categories_heading_title as cmsheadline,
				categories_description as cmstext,
				categories_status as active
			FROM
				{$this->quoteTable('categories', 'co')}
            JOIN
				{$this->quoteTable('categories_description', 'cd')}
			ON co.categories_id = cd.categories_id

			ORDER BY parent_id
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
				`products_id` as `productID`,
				r.`customers_id` as `customerID`,
				r.`customers_name` as `name`,
				c.`customers_email_address` as `email`,
				`reviews_rating` as `rating`,
				`date_added` as `date`,
				1 as `active`,
				`reviews_text` as `comment`,
				'' as `title`
			FROM {$this->quoteTable('reviews', 'r')}

			LEFT JOIN {$this->quoteTable('reviews_description', 'd')}
			ON d.reviews_id=r.reviews_id

			LEFT JOIN {$this->quoteTable('customers', 'c')}
			ON r.customers_id=c.customers_id
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
				o.`orders_id`									as orderID,
				o.`orders_id`									as ordernumber,
				u.`customers_id`								as customerID,
				o.`customers_vat_id`							as ustid,

				IF(a.entry_gender IN ('m', 'Herr'), 'mr', 'ms')	as billing_salutation,
				`billing_firstname`,
				`billing_lastname`,
				`billing_company`,
				-- `billing_company_2`,
				-- `billing_company_3`,
				`billing_street_address`						as billing_street,
				-- `billing_suburb`,
				`billing_city`,
				`billing_postcode`								as billing_zipcode,
				`billing_country_iso_code_2`					as billing_countryiso,

				IF(a.entry_gender IN ('m', 'Herr'), 'mr', 'ms') as shipping_salutation,
				`delivery_firstname`							as shipping_firstname,
				`delivery_lastname`								as shipping_lastname,
				`delivery_company`								as shipping_company,
				-- `delivery_company_2`,
				-- `delivery_company_3`,
				`delivery_street_address`						as shipping_street,
				-- `delivery_suburb`,
				`delivery_city`									as shipping_city,
				`delivery_postcode`								as shipping_zipcode,
				`delivery_country_iso_code_2`					as shipping_countryiso,

				o.`customers_telephone`							as phone,
				-- `billing_fax`								as fax,
				`payment_method`									as paymentID,
				`shipping_class`								as dispatchID,
				`currency`										as currency,
				`currency_value`								as currency_factor,
				-- `language_code`								as languageID,
				l.`languages_id`									        as languageID,
				`comments`										as customercomment,
				`date_purchased`								as date,
				`orders_status`									as statusID,
				-- `orders_date_finished`,
				-- IF(o.`allow_tax`=1,0,1)						as tax_free,
				o.`customers_ip`								as remote_addr,
				1           									as subshopID,

				(
					SELECT SUM(`value`)
					FROM {$this->quoteTable('orders_total')}
					WHERE `class` = 'ot_shipping'
					AND `orders_id`=o.`orders_id`
				)												as invoice_shipping,
				(
					SELECT SUM(`value`)
					FROM {$this->quoteTable('orders_total')}
					WHERE `class` = 'ot_shipping'
					AND `orders_id`=o.`orders_id`
				)												as invoice_shipping_net,
				(
					SELECT SUM(`value`)
					FROM {$this->quoteTable('orders_total')}
					WHERE `class` = 'ot_total'
					AND `orders_id`=o.`orders_id`
				)												as invoice_amount,
				(
					SELECT SUM(`value`)
					FROM {$this->quoteTable('orders_total')}
					WHERE `class`='ot_total'
					AND `orders_id`=o.`orders_id`
				)-(
					SELECT SUM(`value`)
					FROM {$this->quoteTable('orders_total')}
					WHERE `class`='ot_tax'
					AND `orders_id`=o.`orders_id`
				)												as invoice_amount_net

			FROM {$this->quoteTable('orders', 'o')}

			LEFT JOIN {$this->quoteTable('customers', 'u')}
			ON u.customers_id=o.customers_id

            LEFT JOIN {$this->quoteTable('languages', 'l')}
			ON l.directory=o.language

			LEFT JOIN {$this->quoteTable('address_book', 'a')}
			ON a.customers_id=u.customers_id
			AND a.address_book_id=u.customers_default_address_id
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
                products.`orders_id` AS orderID,
                `products_id` AS productID,
                `products_model` AS article_ordernumber,

                IFNULL(CONCAT(
                    products.products_name,
                    ' ',
                    GROUP_CONCAT(attributes.products_options_values SEPARATOR ', '),
                    ' (',
                    GROUP_CONCAT(attributes.products_options SEPARATOR ', '),
                    ')'
                ), products.products_name) AS name,
                `products_price` AS price,
                `products_quantity` AS quantity

            FROM orders_products products

            -- Join attributes in order to name the article by its attribute
            LEFT JOIN orders_products_attributes attributes
            ON attributes.orders_products_id=products.orders_products_id

            GROUP BY (products.orders_products_id)
		";
    }
}
