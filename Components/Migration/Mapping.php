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

class Shopware_Components_Migration_Mapping
{
    /**
     * The source shop
     *
     * @var
     */
    protected $source;

    /**
     * The targe shop
     *
     * @var
     */
    protected $target;

    public function __construct($source, $target)
    {
        $this->source = $source;
        $this->target = $target;
    }

    /**
     * Legacy getter for the target shop
     *
     * @return mixed
     */
    public function Target()
    {
        return $this->target;
    }

    /**
     * Legacy setter for the source shop
     *
     * @return mixed
     */
    public function Source()
    {
        return $this->source;
    }

    /**
     * Returns mappable values for the left grid
     *
     * @return array
     */
    public function getMappingLeft()
    {
        $rows = array();

        $target = $this->setAliases($this->Target()->getShops());
        $shops = $this->mapArrays($this->Source()->getShops(), $target);
        foreach ($shops as $id=>$name) {
            $rows[] = array('internalId'=>$id, 'name'=>$name["value"], 'group'=>'shop', 'mapping_name'=>$name["mapping"], 'mapping'=>$name["mapping_value"], 'required'=>true);
        }

        $target = $this->setAliases($this->Target()->getLanguages());
        $languages = $this->mapArrays($this->Source()->getLanguages(), $target);
        foreach ($languages as $id=>$name) {
            $rows[] = array('internalId'=>$id, 'name'=>$name["value"], 'group'=>'language', 'mapping_name'=>$name["mapping"], 'mapping'=>$name["mapping_value"], 'required'=>true);
        }

        $target = $this->setAliases($this->Target()->getCustomerGroups());
        $customerGroups = $this->mapArrays($this->Source()->getCustomerGroups(), $target);
        foreach ($customerGroups as $id=>$name) {
            $rows[] = array('internalId'=>$id, 'name'=>$name["value"], 'group'=>'customer_group', 'mapping_name'=>$name["mapping"], 'mapping'=>$name["mapping_value"], 'required'=>true);
        }

        $target = $this->setAliases($this->Target()->getPriceGroups());
        $priceGroups = $this->mapArrays($this->Source()->getPriceGroups(), $target);
        foreach ($priceGroups as $id=>$name) {
            $rows[] = array('internalId'=>$id, 'name'=>$name["value"], 'group'=>'price_group', 'mapping_name'=>$name["mapping"], 'mapping'=>$name["mapping_value"]);
        }

        return $rows;
    }

    /**
     * Returns mappable values for the right grid
     *
     * @return array
     */
    public function getMappingRight()
    {
        $rows = array();

        $target = $this->setAliases($this->Target()->getPaymentMeans());
        $paymentMeans = $this->mapArrays($this->Source()->getPaymentMeans(), $target);
        foreach ($paymentMeans as $id=>$name) {
            $rows[] = array('internalId'=>$id, 'name'=>$name["value"], 'group'=>'payment_mean', 'mapping_name'=>$name["mapping"], 'mapping'=>$name["mapping_value"]);
        }

        $target = $this->setAliases($this->Target()->getOrderStatus());
        $orderStatus = $this->mapArrays($this->Source()->getOrderStatus(), $target);
        foreach ($orderStatus as $id=>$name) {
            $rows[] = array('internalId'=>$id, 'name'=>$name["value"], 'group'=>'order_status', 'mapping_name'=>$name["mapping"], 'mapping'=>$name["mapping_value"]);
        }

        $target = $this->setAliases($this->Target()->getTaxRates());
        $taxRates = $this->mapArrays($this->Source()->getTaxRates(), $target);
        foreach ($taxRates as $id=>$name) {
            $rows[] = array('internalId'=>$id, 'name'=>$name["value"], 'group'=>'tax_rate', 'mapping_name'=>$name["mapping"], 'mapping'=>$name["mapping_value"]);
        }

        $target = $this->setAliases($this->Target()->getAttributes());
        $attributes = $this->mapArrays($this->Source()->getAttributes(), $target);
        foreach ($attributes as $id=>$name) {
            $rows[] = array('internalId'=>$id, 'name'=>$name["value"], 'group'=>'attribute', 'mapping_name'=>$name["mapping"], 'mapping'=>$name["mapping_value"]);
        }

		$target = $this->setAliases($this->Target()->getProperties());
		$attributes = $this->mapArrays($this->Source()->getProperties(), $target);
		foreach ($attributes as $id=>$name) {
			$rows[] = array('internalId'=>$id, 'name'=>$name["value"], 'group'=>'property_options', 'mapping_name'=>$name["mapping"], 'mapping'=>$name["mapping_value"]);
		}

        $target = $this->setAliases(sort($this->Target()->getConfiguratorOptions()));
        $attributes = $this->mapArrays($this->Source()->getConfiguratorOptions(), $target);
        ksort($attributes);
        foreach ($attributes as $id=>$name) {
            $rows[] = array('internalId'=>$id, 'name'=>$name["value"], 'group'=>'configurator_mapping', 'mapping_name'=>$name["mapping"], 'mapping'=>$name["mapping_value"]);
        }

        return $rows;
    }

    /**
     * Returns the selectable values for a given entity-mapping
     *
     * @param $entity
     * @return array
     */
    public function getMappingForEntity($entity)
    {
        switch ($entity) {
            case 'shop':
                $values = $this->Target()->getShops();
                break;
            case 'language':
                $values = $this->Target()->getLanguages();
                break;
            case 'customer_group':
                $values = $this->Target()->getCustomerGroups();
                break;
            case 'price_group':
                $values = $this->Target()->getPriceGroups();
                break;
            case 'payment_mean':
                $values = $this->Target()->getPaymentMeans();
                break;
            case 'order_status':
                $values = $this->Target()->getOrderStatus();
                break;
            case 'tax_rate':
                $values = $this->Target()->getTaxRates();
                break;
            case 'attribute':
                $values = $this->Target()->getAttributes();
                break;
	        case 'property_options':
		        $values = $this->Target()->getProperties();
		        break;
            case 'configurator_mapping':
   		        $values = $this->Target()->getConfiguratorOptions();
   		        break;
            default:
                break;
        }

	    // The id is not needed later - it just may not collide with any other id
	    $rows = array(array('id'=>'Bitte wählen', 'name'=>'Bitte wählen'));


        if(!empty($values)) {
            foreach ($values as $key=>$value) {
                $rows[] = array('id'=>$key, 'name'=>$value);
            }
        }

        return $rows;
    }

    /**
     * Helper function to set an automatic mapping when the user open the mapping panel.
     * @param $array
     * @return mixed
     */
    public function setAliases($array) {
        $aliasList = array(
            //Languages - Shops
            array("deutsch", "german", "main store", "main", "mainstore", "hauptshop deutsch"),
            array("englisch", "english", "default english"),
            array("französisch", "french"),

            //Payments
            array("vorkasse", "vorauskasse", "prepayment", "in advance"),

            //order states
            array("in bearbeitung(wartet)", "in bearbeitung", "wird bearbeitet", "bearbeitung", "in progress", "in process", "processing"),
            array("offen", "open", "opened"),
            array("komplett abgeschlossen", "abgeschlossen", "completed", "fully completed", "finish", "finished"),
            array("teilweise abgeschlossen", "partially completed", "partially finished"),
            array("storniert / abgelehnt", "storniert", "abgelehnt", "canceled", "declined", "rejected", "denied"),
            array("zur lieferung bereit", "lieferbereit", "ready for delivery", "ready for deliver", "ready to ship"),
            array("klärung notwendig", "klärung", "mehr informationen notwendig", "clarification needed", "declaration needed", "more information needed"),
            array("abgebrochen", "canceled", "aborted"),

            //taxes
            array("Standardsatz", "standard tax rate", "19%", "19 %"),
            array("ermäßigter Steuersatz", "reduced tax rate", "7%", "7 %")
        );

        foreach($array as &$element) {
            $temp = $element;
            foreach($aliasList as $alias) {
                if(in_array(strtolower($temp), $alias)) {
                    array_unshift($alias, $temp);
                    $element = $alias;
                    break;
                }
            }
        }
        return $array;
    }

    /**
     * Internal helper function for the automatic mapping
     * @param $sourceArray
     * @param $targetArray
     * @return mixed
     */
    private function mapArrays($sourceArray, $targetArray) {
        foreach($sourceArray as &$source) {
            $source = array("value"=> $source, "mapping"=>'', "mapping_value"=>'');
            foreach($targetArray as $key => $target) {
                if(is_array($target)){
                    foreach($target as $alias) {
                        if(strtolower($source["value"]) == strtolower($alias)
                            || (strtolower(substr($source["value"],0,6)) == strtolower(substr($alias,0,6))))
                        {
                            $source["mapping"] = $target[0];
                            $source["mapping_value"] = $key;
                            break;
                        }
                    }
                } else {
                    if(strtolower($source["value"])==strtolower($target)
                        || (strtolower(substr($source["value"],0,6)) == strtolower(substr($target,0,6))))
                    {
                        $source["mapping"] = $target;
                        $source["mapping_value"] = $key;
                        break;
                    }
                }
            }

            if ($source['mapping'] === '' && $source['mapping_value'] === '') {
                $source["mapping"] = 'Bitte wählen';
                $source["mapping_value"] = 'Bitte wählen';
            }

        }
        return $sourceArray;
    }
}