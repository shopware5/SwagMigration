<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagMigration\Components\Migration\Profile;

use Shopware\SwagMigration\Components\Migration\Profile;

class Prestashop14 extends Profile
{
    /**
     * Database prefix
     *
     * @var string
     */
    protected $db_prefix = 'ps_';

    /**
     * Returns the directory of the article images.
     *
     * @return string {String} | image path
     */
    public function getProductImagePath()
    {
        return 'img/p/';
    }

    /**
     * Returns the sql statement to select default shop system language
     *
     * @return string {String} | sql for default language
     */
    public function getDefaultLanguageSelect()
    {
        return "SELECT `id_lang` FROM {$this->quoteTable('lang')} WHERE active=1 ORDER BY id_lang ASC";
    }

    /**
     * Returns the sql statement to select the shop system languages
     *
     * @return string {String} | sql for languages
     */
    public function getLanguageSelect()
    {
        return "SELECT `id_lang` as id, name as name FROM {$this->quoteTable('lang')}";
    }

    /**
     * Returns the sql statement to select the shop system sub shops
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
			SELECT g.id_group as id, gl.name as name
			FROM {$this->quoteTable('group', 'g')}
			LEFT JOIN {$this->quoteTable('group_lang', 'gl')} ON g.id_group=gl.id_group
			WHERE gl.id_lang={$this->Db()->quote($this->getDefaultLanguage())}
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
			SELECT o.module as id, o.module as name
			FROM {$this->quoteTable('orders', 'o')}
			GROUP BY o.module
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
			SELECT `id_order_state` as id, `name` as name
			FROM {$this->quoteTable('order_state_lang')}
			WHERE `id_lang`={$this->Db()->quote($this->getDefaultLanguage())}
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
			SELECT `id_tax` as id, `rate` as name
			FROM {$this->quoteTable('tax')}
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
            SELECT
            id_attribute_group as id, name as name
			FROM {$this->quoteTable('attribute_group_lang')}
            WHERE id_lang={$this->Db()->quote($this->getDefaultLanguage())}
            GROUP BY id_attribute_group
		";
    }

    /**
     * Get productIds for all products with attributes
     *
     * @return string
     */
    public function getAttributedProductsSelect()
    {
        return '
            SELECT
            DISTINCT p.id_product AS productID

            FROM ps_product p

            LEFT JOIN ps_product_attribute a
            ON p.id_product =a.id_product

            WHERE a.id_product IS NOT NULL
        ';
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
            agl.public_name             as group_name,
            p.id_product                as productId,
            al.name                     as option_name,
            pa.price                    as price

            FROM ps_product p

            -- join products attributes
            LEFT JOIN  {$this->quoteTable('product_attribute', 'pa')}
            ON pa.id_product = p.id_product

            -- maps products attributes and attributes
            INNER JOIN {$this->quoteTable('product_attribute_combination', 'c')}
            ON c.id_product_attribute = pa.id_product_attribute

            -- join actual attributes
            INNER JOIN {$this->quoteTable('attribute', 'a')}
            ON a.id_attribute = c.id_attribute

            -- attribute names
            LEFT JOIN {$this->quoteTable('attribute_lang', 'al')}
            ON al.id_attribute = a.id_attribute
            AND al.id_lang = {$this->Db()->quote($this->getDefaultLanguage())}

            -- attribute group names
            LEFT JOIN {$this->quoteTable('attribute_group_lang', 'agl')}
            ON agl.id_attribute_group = a.id_attribute_group
            AND agl.id_lang = al.id_lang

            WHERE p.id_product = {$id}
        ";
    }

    /**
     * Returns the sql statement to select the shop system articles
     *
     * @return string {String} | sql for the articles
     */
    public function getProductSelect()
    {
        $taxSelect = "
            IFNULL(
                (SELECT tr.id_tax FROM {$this->quoteTable('tax_rule', 'tr')}  WHERE id_tax_rule=1 LIMIT 1),
                1
            ) as taxID,
        ";

        return "
			SELECT
				a.id_product							as productID,

				a.quantity       						as instock,
				-- a.products_average_quantity			as stockmin,
                -- a.products_shippingtime					as shippingtime,
				if(a.reference='', CONCAT('sw', a.id_product), a.reference)						as ordernumber,
				-- a.products_image						as image,
				a.price    						        as net_price,
				a.wholesale_price                       as baseprice,

				a.date_add          					as added,
				a.date_upd 						        as changed,
				a.weight        						as weight,
				a.depth 			                    as length,
				a.width 			                    as width,
				a.height 			                    as height,

				{$taxSelect}

				s.name              					as supplier,
				a.active        						as active,

				a.ean13					        		as ean,

				d.name                                  as name,
				d.description        					as description_long,
				d.meta_title        					as meta_title,
				d.meta_description           			as description,
				d.meta_keywords          				as keywords

			FROM {$this->quoteTable('product', 'a')}

			LEFT JOIN {$this->quoteTable('manufacturer', 's')}
			ON s.id_manufacturer=a.id_manufacturer

			LEFT JOIN {$this->quoteTable('product_lang', 'd')}
			ON d.id_product=a.id_product
            AND d.id_lang={$this->Db()->quote($this->getDefaultLanguage())}
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
			SELECT `id_group`
			FROM {$this->quoteTable('group')}
		";
        $price_groups = $this->db->fetchCol($sql);

        $sql = [];

        if (!empty($price_groups)) {
            foreach ($price_groups as $price_group) {
                $sql[] = "
					SELECT
                        a.wholesale_price                       as baseprice,
						pr.id_product as productID,
						pr.from_quantity as `from`,
						IF(reduction_type='percentage', a.price*(1-reduction),a.price-reduction) as `net_price`,
						'$price_group' as pricegroup
					FROM {$this->quoteTable('specific_price', 'pr')}

					LEFT JOIN {$this->quoteTable('product', 'a')}
					ON a.id_product=pr.id_product

					WHERE `id_group`=$price_group || `id_group`=0
					ORDER BY pr.id_product, `from`
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
				SELECT
				    `id_product` as productID,
				    CONCAT(id_product, '-', id_image, '.jpg') as image,
				    cover as main,
				    position as position
				FROM {$this->quoteTable('image')}
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
				d.id_product 					as productID,
				d.id_lang 			    		as languageID,
				d.name 		        	    	as name,
				d.description 			        as description_long,
				d.description_short 	        as description,
				d.meta_title			        as meta_title,
				d.meta_description 	            as meta_description,
				d.meta_keywords		            as meta_keywords
			FROM {$this->quoteTable('product_lang', 'd')}
			WHERE `id_lang`!={$this->Db()->quote($this->getDefaultLanguage())}
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
     * Returns the sql statement to select the shop system customer
     *
     * @return string {String} | sql for the customer data
     */
    public function getCustomerSelect()
    {
        return "
			SELECT
				u.id_customer 										as customerID,
				u.id_customer 										as customernumber,
				1                                                   as subshopID,
				IF(u.id_gender=1, 'mr', 'ms')		                as salutation,
				u.firstname                                         as firstname,
				u.lastname       	 								as lastname,

				IF(u.id_gender=1, 'mr', 'ms')		                as billing_salutation,
				u.firstname                                         as billing_firstname,
				u.lastname       	 								as billing_lastname,
				a.company   		 								as billing_company,
				'' 													as billing_department,
				a.address1          	 							as billing_street,
				'' 													as billing_streetnumber,
				a.postcode       									as billing_zipcode,
				a.city	 								        	as billing_city,
				c2.iso_code           								as billing_countryiso,

				IF(u.id_gender=1, 'mr', 'ms')                       as shipping_salutation,
				u.firstname 						        		as shipping_firstname,
				u.lastname 							            	as shipping_lastname,
				a.company 							        		as shipping_company,
				'' 													as shipping_department,
				a.address1  						            	as shipping_street,
				'' 													as shipping_streetnumber,
				a.postcode            								as shipping_zipcode,
				a.city  								        	as shipping_city,
				c2.iso_code                   						as shipping_countryiso,

				a.phone 					            			as phone,
				u.email                 							as email,
				DATE(u.birthday)				    				as birthday,
				a.vat_number     									as ustid,
				u.newsletter        								as newsletter,

				u.passwd 								            as md5_password,
				'md5reversed'	    								as encoder,

				u.id_default_group									as customergroupID,

				u.date_add           								as firstlogin,
				u.date_upd	                                        as lastlogin,
				u.active                							as active

			FROM {$this->quoteTable('customer', 'u')}

			-- Limit the number of joined addresses to one
			LEFT JOIN {$this->quoteTable('address', 'a')}
            ON a.id_customer=u.id_customer

			LEFT JOIN {$this->quoteTable('country_lang', 'c')}
			ON c.id_country=a.id_country

			LEFT JOIN {$this->quoteTable('country', 'c2')}
            ON c2.id_country=a.id_country

			WHERE c.id_lang={$this->Db()->quote($this->getDefaultLanguage())}
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
			SELECT `id_product` as productID, `id_category` as categoryID
			FROM {$this->quoteTable('category_product')}
			ORDER BY `id_product`
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
				c.id_category as categoryID,
				IF(c.id_parent=1, '', c.id_parent) as parentID,
				cl.id_lang as languageID,
				cl.name as description,
				c.position as position,
				cl.meta_title as meta_title,
				cl.meta_keywords as metaKeywords,
				cl.meta_description as metaDescription,
				c.active as active,
				cl.meta_title as cmsheadline,
				cl.description as cmstext

			FROM  {$this->quoteTable('category', 'c')}

            LEFT JOIN {$this->quoteTable('category_lang', 'cl')}
            ON cl.id_category=c.id_category

            WHERE c.id_category>1

            ORDER BY c.id_parent ASC

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
			    r.`id_product` as `productID`,
				r.`id_customer` as `customerID`,
				r.`customer_name` as `name`,
				IFNULL(c.`email`, '') as `email`,
				r.`grade` as `rating`,
				r.`date_add` as `date`,
				1 as `active`,
				`content` as `comment`,
				r.title as `title`
			FROM {$this->quoteTable('product_comment', 'r')}

			LEFT JOIN {$this->quoteTable('customer', 'c')}
			ON r.id_customer=c.id_customer
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
				o.`id_order`									    as orderID,
				o.`id_order`									    as ordernumber,
				u.`id_customer`								        as customerID,
				aBilling.`vat_number`							    as ustid,
				1                                                   as subshopID,

				IF(u.id_gender=1, 'mr', 'ms')   	                as billing_salutation,
				u.firstname                                         as billing_firstname,
				u.lastname       	 								as billing_lastname,
				aBilling.company   		 							as billing_company,
				'' 													as billing_department,
				aBilling.address1          	 						as billing_street,
				'' 													as billing_streetnumber,
				aBilling.postcode       							as billing_zipcode,
				aBilling.city	 								    as billing_city,
				cBilling.iso_code           						as billing_countryiso,

				IF(u.id_gender=1, 'mr', 'ms')		                as shipping_salutation,
				u.firstname                                         as shipping_firstname,
				u.lastname       	 								as shipping_lastname,
				aShipping.company   		 						as shipping_company,
				'' 													as shipping_department,
				aShipping.address1          	 					as shipping_street,
				'' 													as shipping_streetnumber,
				aShipping.postcode       							as shipping_zipcode,
				aShipping.city	 								    as shipping_city,
				cShipping.iso_code           						as shipping_countryiso,

				aBilling.`phone`							        as phone,
				`module`									        as paymentID,
				`id_carrier`								        as dispatchID,
				c.`iso_code`										as currency,
				o.`conversion_rate`								    as currency_factor,
				o.`id_lang`								            as languageID,
				GROUP_CONCAT(cm.`message`)                          as customercomment,
				o.`date_add`								        as date,
				-- Need a subselect to get the current order status
				-- Removing this might have a positive performance impact
				(
				    SELECT id_order_state
				    FROM {$this->quoteTable('order_history', 'history')}
				    WHERE history.id_order = o.id_order
				    ORDER BY id_order_history DESC
				    LIMIT 1
                )									            as statusID,
				-- `orders_date_finished`,
				-- IF(o.`allow_tax`=1,0,1)						as tax_free,
				-- o.`customers_ip`								as remote_addr,

				o.total_shipping                               as invoice_shipping,
				IF(
				    carrier_tax_rate<1,
				    total_shipping ,
				    total_shipping / ((carrier_tax_rate + 100)/100)
                )                                               as invoice_shipping_net,
				o.total_paid               						as invoice_amount
				-- o.total_paid_tax_excl							as invoice_amount_net

			FROM {$this->quoteTable('orders', 'o')}

			LEFT JOIN {$this->quoteTable('customer', 'u')}
			ON u.id_customer=o.id_customer

			LEFT JOIN {$this->quoteTable('address', 'aShipping')}
			ON aShipping.id_address=o.id_address_delivery

			LEFT JOIN {$this->quoteTable('address', 'aBilling')}
			ON aBilling.id_address=o.id_address_invoice

			LEFT JOIN {$this->quoteTable('country', 'cBilling')}
            ON cBilling.id_country=aBilling.id_country

			LEFT JOIN {$this->quoteTable('country', 'cShipping')}
            ON cShipping.id_country=aShipping.id_country

            LEFT JOIN {$this->quoteTable('currency', 'c')}
            ON c.id_currency=o.id_currency


            LEFT JOIN {$this->quoteTable('customer_thread', 'ct')}
            ON ct.id_order=o.id_order

            LEFT JOIN {$this->quoteTable('customer_message', 'cm')}
            ON cm.id_customer_thread=ct.id_customer_thread

            GROUP BY o.id_order

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
				od.`id_order` as orderID,
				od.`product_id` as productID,
				od.`id_order_detail`	as ordernumber,
                IFNULL(
                    if(p.reference='', null, p.reference),
                    CONCAT('sw', od.product_id)
                ) as article_ordernumber,
				od.`product_name` as name,
				od.`product_price` as price,
				od.`product_quantity` as quantity,
				od.tax_rate as tax_rate


			FROM {$this->quoteTable('order_detail', 'od')}

            LEFT JOIN {$this->quoteTable('orders', 'o')}
            ON o.id_order=od.id_order

            LEFT JOIN {$this->quoteTable('product', 'p')}
            ON p.id_product=od.product_id
		";
    }
}
