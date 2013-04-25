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
 * Shopware SwagMigration Components - Shopware350
 *
 * @category  Shopware
 * @package Shopware\Plugins\SwagMigration\Components
 * @copyright Copyright (c) 2012, shopware AG (http://www.shopware.de)
 */
class Shopware_Components_Migration_Profile_Shopware35 extends Shopware_Components_Migration_Profile
{
    /**
     * Prefix of each shopware 3.5.7 database table.
     * @var string
     */
    protected $db_prefix = 's_';


    /**
   	 * Returns the directory of the article images.
   	 * @return string {String} | image path
   	 */
   	public function getProductImagePath()
   	{
        return 'images/articles/';
   	}

    /**
   	 * Returns the sql statement to select the config base path
   	 * @return string {String} | sql for the config base path
   	 */
   	public function getConfigSelect()
   	{
        return "SELECT * FROM {$this->quoteTable('core_config')}";
   	}

    /**
   	 * Returns the sql statement to select the shop system article translations
   	 * @return string {String} | sql for the article translations
   	 */
   	public function getProductTranslationSelect()
   	{
       $attributes = array('description', 'name', 'short_description', 'meta_keyword',);

       $custom_select = '';
       foreach($this->getAttributes() as $attributeID => $attribute) {
           $custom_select .= ",
                    att.$attributeID ";

           $attributes[] = $attributeID;
       }

       return "
            SELECT  att.articledetailsID as productID,
                    trans.languageID,
                    trans.name,
                    trans.description,
                    trans.description_long,
                    '' as additionaltext,
                    trans.keywords
                    $custom_select
            FROM {$this->quoteTable('articles_translations')} trans

            LEFT JOIN {$this->quoteTable('articles_attributes')} att
            ON (trans.articleID = att.articleID)
       ";

       }

    /**
   	 * Returns the sql statement to select the shop system article category allocation
   	 * @return string {String} | sql for the article category allocation
   	 */
   	public function getProductCategorySelect()
   	{
       return "
                SELECT ad.id as productID, categoryID

                FROM {$this->quoteTable('articles_categories', 'ac')}

                INNER JOIN {$this->quoteTable('articles_details', 'ad')}
                ON ad.articleID = ac.articleID
                AND kind = 1

                WHERE ac.categoryID NOT IN (SELECT parentID FROM {$this->quoteTable('core_multilanguage')})
            ";
   	}

    /**
   	 * Returns the sql statement to select all shop system order details
   	 * @return string {String} | sql for order details
   	 */
	public function getOrderDetailSelect()
	{
        return "
            SELECT
                od.orderID,
                od.articleID as productID,
                od.ordernumber as article_ordernumber,
                od.name,
                od.price,
                od.quantity,
                od.taxID as tax,
                od.modus,

				od.od_attr1                                  as attr1,
				od.od_attr2                                  as attr2,
				od.od_attr3                                  as attr3,
				od.od_attr4                                  as attr4,
				od.od_attr5                                  as attr5,
				od.od_attr6                                  as attr6

            FROM {$this->quoteTable('order_details')} od

        ";

	}


    /**
   	 * Returns the sql statement to select default shopware language
   	 * @return string {String} | sql for default language
   	 */
    public function getDefaultLanguageSelect()
    {
        return 'SELECT `isocode` FROM `s_core_multilanguage` WHERE `default` =1';
    }

    /**
   	 * Returns the sql statement to select the shop system sub shops
   	 * @return string {String} | sql for sub shops
   	 */
    public function getShopSelect()
    {
        return "
			SELECT `id`, `name`, `domainaliase` as domain
			FROM {$this->quoteTable('core_multilanguage')}
		";
    }

    /**
   	 * Returns the sql statement to select the shop system languages
   	 * @return string {String} | sql for languages
   	 */
    public function getLanguageSelect()
    {
        return "
			SELECT `isocode` as `id`, `name`
			FROM {$this->quoteTable('core_multilanguage')}
			WHERE `default`=1
			
			UNION ALL
			
			SELECT `isocode` as `id`, `name`
			FROM {$this->quoteTable('core_multilanguage')}
			WHERE `skipbackend`=0
		";
    }

    /**
   	 * Returns the sql statement to select the shop system customer groups
   	 * @return string {String} | sql for customer groups
   	 */
    public function getCustomerGroupSelect()
    {
        return "
			SELECT `groupkey`, `description` as `name`
			FROM {$this->quoteTable('core_customergroups')}
		";
    }

    /**
   	 * Returns the sql statement to select the shop system price groups
   	 * @return string {String} | sql for price groups
   	 */
    public function getPriceGroupSelect()
    {
        return "
			SELECT `groupkey` as id, `description` as `name`
			FROM {$this->quoteTable('core_customergroups')}
		";
    }

    /**
   	 * Returns the sql statement to select the shop system payments
   	 * @return string {String} | sql for the payments
   	 */
    public function getPaymentMeanSelect()
    {
        return "
			SELECT `id`, `description` as `name`
			FROM {$this->quoteTable('core_paymentmeans')}
			ORDER BY `description` ASC
		";
    }

    /**
   	 * Returns the sql statement to select the shop system order states
   	 * @return string {String} | sql for the order states
   	 */
    public function getOrderStatusSelect()
    {
        return "
			SELECT `id` , `description` as name
			FROM {$this->quoteTable('core_states', 's')}
			WHERE `group`='state'
			ORDER BY `position` ASC
		";
    }

    /**
   	 * Returns the sql statement to select the shop system tax rates
   	 * @return string {String} | sql for the tax rates
   	 */
    public function getTaxRateSelect()
    {
        return "
			SELECT `id`, `description` as name
			FROM {$this->quoteTable('core_tax')}
		";
    }

    /**
   	 * Returns the sql statement to select the shop system article attributes
   	 * @return string {String} | sql for the article attributes
   	 */
    public function getAttributeSelect()
    {
        return "
			SELECT `databasefield` as id, `domdescription` as name, required, domtype
			FROM {$this->quoteTable('core_engine_elements')}
			WHERE `databasefield` LIKE '%attr%%'
		";
    }


    /**
   	 * Returns the sql statement to select the shop system categories
   	 * @return string {String} | sql for the categories
   	 */
    public function getCategorySelect()
    {
        
        $sql = "
            SELECT id, isocode as parentID
            FROM {$this->quoteTable('core_multilanguage')}
        ";

        $shops = $this->Db()->fetchAssoc($sql);

        $parent_template = "IF(cat_parent.parent=%s, '%s', %s) as parentID";
        $language_template = "IF(cat_parent.parent=%s, '%s', %s) as languageID";

        $parent_select = "%s";
        $language_select = "%s";
        foreach ($shops as $row) {
            $current_parent = sprintf($parent_template, $row['id'], '', '%s');
            $parent_select = sprintf($parent_select, $current_parent);

            $current_language = sprintf($language_template, $row['id'], $row['parentID'], '%s');
            $language_select = sprintf($language_select, $current_language);
        }
        $parent_select = sprintf($parent_select, 'cat.parent');
        $language_select = sprintf($language_select, 0);

        $where = 'WHERE cat.parent NOT IN ('. implode(', ', array_keys($shops)) . ')';

        return "
            SELECT
                cat.id as categoryID,
                $parent_select,
                $language_select,
                cat.description,
                cat.position,
                cat.metakeywords,
                cat.metadescription,
                cat.cmsheadline,
                cat.cmstext,
                cat.active
			FROM {$this->quoteTable('categories')} cat

			LEFT JOIN {$this->quoteTable('categories', 'cat_parent')}
			ON cat_parent.id = cat.parent

            LEFT JOIN {$this->quoteTable('core_multilanguage', 'm')}
            ON cat_parent.id = m.parentID

			{$where}

            -- Sort out languages which have no shop associated
            AND (cat_parent.parent != 1 || m.parentID IS NOT NULL)

            ORDER BY parentID ASC, position ASC
		";
    }




    /**
     * Get productIds for all products with configurators
     * @return string
     */
    public function getAttributedProductsSelect()
    {
        return "
            SELECT
            DISTINCT ad.id as productID

            FROM  {$this->quoteTable('articles_groups_option', 'p')}

            INNER JOIN {$this->quoteTable('articles_details', 'ad')}
            ON ad.articleID = p.articleID

        ";
    }

    /**
     * Select attributes for a given article
     * @param $id
     * @return string
     */
    public function getProductAttributesSelect($id)
    {
        return "
            SELECT
                ad.id                           as productId,
                gp.price                        as price,
                g.groupname                     as group_name,
                gs.type                         as configurator_type,
                go.optionname                   as option_name,
                go.optionposition               as option_position,
                g.groupposition                 as group_position


            FROM {$this->quoteTable('articles_details')} ad

            LEFT JOIN {$this->quoteTable('articles_groups')} g
            ON g.articleID = ad.articleID

            LEFT JOIN {$this->quoteTable('articles_groups_option')} go
            ON go.articleID = g.articleID
            AND go.groupID = g.groupID

            LEFT JOIN {$this->quoteTable('articles_groups_prices')} gp
            ON gp.optionID = go.optionID
            AND gp.articleID = go.articleID
            AND gp.groupkey = 'EK'

            LEFT JOIN {$this->quoteTable('articles_groups_settings')} gs
            ON gs.articleID = go.articleID

            WHERE ad.id = '{$id}'

        ";
    }

    /**
   	 * Returns the sql statement to select articles with
     * @param $id
   	 * @return string
   	 */
   	public function getProductPropertiesSelect($id)
   	{

        /**
         * Intentionally not using the articleID field of the s_filter_values
         * table as there is no usable index on that.
         * This path should work better for large tables
         */
        return "
   			SELECT
   				ad.id               as productID,
   				fg.name				as 'group',
   				fo.name			    as 'option',
   				fv.value            as 'value'

   			FROM {$this->quoteTable('articles_details', 'ad')}

   			INNER JOIN {$this->quoteTable('articles', 'a')}
   			ON a.id = ad.articleID

   			INNER JOIN {$this->quoteTable('filter_values', 'fv')}
   			ON fv.groupID = a.filtergroupID

   			INNER JOIN {$this->quoteTable('filter_options', 'fo')}
   			ON fo.id = fv.optionID

   			INNER JOIN {$this->quoteTable('filter', 'fg')}
   			ON fg.id = a.filtergroupID

   			WHERE ad.id = '{$id}'
   		";
   	}


   	public function getProductsWithPropertiesSelect()
   	{
   		return "
   			SELECT ad.id as productID

   			FROM {$this->quoteTable('articles', a)}

   			INNER JOIN {$this->quoteTable('articles_details', ad)}
   			ON ad.articleID = a.id

   			WHERE a.filtergroupID > 0
   		";
   	}

    /**
   	 * Returns the sql statement to select the shop system articles
   	 * @return string {String} | sql for the articles
   	 */
    public function getProductSelect()
    {


        return "
            SELECT
                ad.id as productID,
                IF(ad_main.id = ad.id, NULL, ad_main.id) as parentID,
                IF(ad_main.id = ad.id, 1, 0) as masterWithAttributes,
                ad_main.id as parentID,
                a.active,

                ad.ordernumber,
                ad.additionaltext,

                a.datum as added,
                a.name,

                a.description_long,
                a.description,
                a.keywords,
                a.laststock,
                a.minpurchase,
                a.maxpurchase,
                a.taxID,
                a.releasedate         				as releasedate,
				a.purchaseunit,
				a.packunit,
				a.unitID,
				a.referenceunit,

                ad.additionaltext,
                ad.suppliernumber as supplier,
                ad.weight,
                ad.instock,
                ad.stockmin


            -- Get article
            FROM {$this->quoteTable('articles')} a

            -- Join all details
            LEFT JOIN {$this->quoteTable('articles_details')} ad
            ON a.id = ad.articleID

            -- Join main detail as parent article
            INNER JOIN {$this->quoteTable('articles_details')} ad_main
            ON a.id = ad_main.articleID
            AND ad_main.kind = 1

            -- Need to make sure, that the parent details come first
            ORDER BY ad.kind ASC
        ";
    }

    /**
   	 * Returns the sql statement to select the shop system customer
   	 * @return string {String} | sql for the customer data
   	 */
    public function getOrderSelect()
    {
        return "
			SELECT
                od.id                                       as orderID,
                od.ordernumber,
                od.subshopID,
                od.userID                                   as customerID,
                od.paymentID,
                od.dispatchID,
                od.status                                   as status,
                od.customercomment,
                od.currency,
                od.currencyFactor,
                od.remote_addr,
                od.ordertime                                as date,
                ob.ustid,
                ob.phone,
                ob.fax,
                od.`language`								        as languageID,

				ob.`company`								as billing_company,
				ob.`firstname`								as billing_firstname,
				ob.`lastname`								as billing_lastname,
				ob.`street`									as billing_street,
				ob.`city` 									as billing_city,
				bc.countryiso								as billing_countryiso,
				ob.`zipcode`								as billing_zipcode,
				ob.salutation                               as billing_salutation,

				os.`company`								as shipping_company,
				os.`firstname`								as shipping_firstname,
				os.`lastname` 								as shipping_lastname,
				os.`street` 								as shipping_street,
				os.`city`									as shipping_city,
				sc.countryiso								as shipping_countryiso,
				os.`zipcode`								as shipping_zipcode,
				os.salutation	                        	as shipping_salutation,

				od.invoice_amount_net       				as invoice_amount_net,
				od.invoice_amount							as invoice_amount,
				od.invoice_shipping			    			as invoice_shipping,
				od.invoice_shipping_net	    				as invoice_shipping_net,

				od.o_attr1                                  as attr1,
				od.o_attr2                                  as attr2,
				od.o_attr3                                  as attr3,
				od.o_attr4                                  as attr4,
				od.o_attr5                                  as attr5,
				od.o_attr6                                  as attr6

			FROM {$this->quoteTable('order')} od
            INNER JOIN {$this->quoteTable('order_billingaddress')}ob
            ON (ob.orderID = od.id)

            INNER JOIN {$this->quoteTable('order_shippingaddress')} os
            ON (os.orderID = od.id)

            LEFT JOIN {$this->quoteTable('core_countries')} bc
            ON (bc.id = ob.countryID)

            LEFT JOIN {$this->quoteTable('core_countries')} sc
            ON (sc.id = os.countryID)

		";
    }

    /**
   	 * Returns the sql statement to select the shop system customer
   	 * @return string {String} | sql for the customer data
   	 */
    public function getCustomerSelect()
    {
        return "
			SELECT
                us.id as customerID,
                bill.customernumber,
                us.email,
                us.subshopID,
                us.firstlogin,
                us.lastlogin,
                us.active,
                us.customergroup as customergroupID,
                us.password 							    as md5_password,

				bill.salutation                             as billing_salutation,
				bill.`company`								as billing_company,
				bill.`firstname`							as billing_firstname,
				bill.`lastname`								as billing_lastname,
				bill.`street`								as billing_street,
				bill.`city` 								as billing_city,
				bc.countryiso							    as billing_countryiso,
				bill.`zipcode`								as billing_zipcode,
                bill.`streetnumber`							as billing_streetnumber,

				ship.`company`								as shipping_company,
				ship.`firstname`							as shipping_firstname,
				ship.`lastname` 							as shipping_lastname,
				ship.`street` 								as shipping_street,
				ship.`city`									as shipping_city,
				sc.countryiso								as shipping_countryiso,
				ship.`zipcode`								as shipping_zipcode,
				ship.salutation	                        	as shipping_salutation,
				ship.`streetnumber`							as shipping_streetnumber,

                bill.phone,
                bill.fax,
                bill.birthday,
                bill.ustid,
                us.newsletter

            FROM {$this->quoteTable('user')} us

            LEFT JOIN {$this->quoteTable('user_billingaddress')} bill
            ON (bill.userID = us.id)

            LEFT JOIN {$this->quoteTable('user_shippingaddress')} ship
            ON (ship.userID = us.id)

            LEFT JOIN {$this->quoteTable('core_countries')} bc
            ON (bc.id = bill.countryID)

            LEFT JOIN {$this->quoteTable('core_countries')} sc
            ON (sc.id = ship.countryID)
		";
    }

    /**
   	 * Returns the sql statement to select the shop system article image allocation
   	 * @return string {String} | sql for the article image allocation
   	 */
    public function getProductImageSelect()
    {
        return "
            SELECT
                COALESCE(ad_relation.id, ad.id) as productID,
                CONCAT(ai.img, '.jpg') as image,
                ai.description,
                ai.position,
                ai.main
            FROM {$this->quoteTable('articles_img')} ai

            LEFT JOIN {$this->quoteTable('articles_details')} ad
            ON ad.articleID = ai.articleID
            AND ad.kind = 1

            LEFT JOIN {$this->quoteTable('articles_details')} ad_relation
            ON ad_relation.ordernumber = ai.relations
        ";
    }

    /**
   	 * Returns the sql statement to select the shop system article ratings
   	 * @return string {String} | sql for the article ratings
   	 */
    public function getProductRatingSelect()
    {
        return "
            SELECT
                at.articleID as productID,
                (SELECT id FROM {$this->quoteTable('user')} us WHERE us.email = at.email) as customerID,
                at.name,
                at.points as `rating`,
                at.datum as `date`,
                at.active,
                at.comment,
                at.headline as `title`
            FROM {$this->quoteTable('articles_vote')} at
        ";
    }


    /**
   	 * Returns the sql statement to select the shop system article prices
   	 * @return string {String} | sql for the article prices
   	 */
    public function getProductPriceSelect()
    {
        return "
                SELECT
                    prices.articledetailsID   as productID,
                    prices.from               as `from`,
                    prices.price              as net_price,
                    prices.percent,
                    prices.pseudoprice        as net_pseudoprice,
                    prices.pricegroup,
                    prices.baseprice

                FROM {$this->quoteTable('articles_prices')} prices

                ORDER BY articledetailsID, pricegroup, `from`
            ";
    }
}