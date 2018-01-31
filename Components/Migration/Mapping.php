<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagMigration\Components\Migration;

use Enlight_Components_Snippet_Namespace;

class Mapping
{
    /**
     * The source shop
     *
     * @var Profile
     */
    protected $source;

    /**
     * The targe shop
     *
     * @var
     */
    protected $target;

    /**
     * @var Enlight_Components_Snippet_Namespace
     */
    private $namespace;

    /**
     * Constructor. Sets some dependencies.
     *
     * @param Profile                               $source
     * @param Profile                               $target
     * @param \Enlight_Components_Snippet_Namespace $namespace
     */
    public function __construct($source, $target, \Enlight_Components_Snippet_Namespace $namespace)
    {
        $this->source = $source;
        $this->target = $target;
        $this->namespace = $namespace;
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
        $rows = [];

        $target = $this->setAliases($this->Target()->getShops());
        $shops = $this->mapArrays($this->Source()->getShops(), $target);
        foreach ($shops as $id => $name) {
            $rows[] = [
                'internalId' => $id,
                'name' => $name['value'],
                'group' => 'shop',
                'mapping_name' => $name['mapping'],
                'mapping' => $name['mapping_value'],
                'required' => true,
            ];
        }

        $target = $this->setAliases($this->Target()->getLanguages());
        $languages = $this->mapArrays($this->Source()->getLanguages(), $target);
        foreach ($languages as $id => $name) {
            $rows[] = [
                'internalId' => $id,
                'name' => $name['value'],
                'group' => 'language',
                'mapping_name' => $name['mapping'],
                'mapping' => $name['mapping_value'],
                'required' => true,
            ];
        }

        $target = $this->setAliases($this->Target()->getCustomerGroups());
        $customerGroups = $this->mapArrays($this->Source()->getCustomerGroups(), $target);
        $wooCustomerGroups = unserialize(reset($customerGroups)['value']);

        if ($wooCustomerGroups != false) {
            $customerGroups = $this->refactorSerializedArray($wooCustomerGroups);
        }

        foreach ($customerGroups as $id => $name) {
            $rows[] = [
                'internalId' => $id,
                'name' => $name['value'],
                'group' => 'customer_group',
                'mapping_name' => $name['mapping'],
                'mapping' => $name['mapping_value'],
                'required' => true,
            ];
        }

        $target = $this->setAliases($this->Target()->getPriceGroups());
        $priceGroups = $this->mapArrays($this->Source()->getPriceGroups(), $target);
        $wooPriceGroups = unserialize(reset($priceGroups)['value']);

        if ($wooPriceGroups != false) {
            $priceGroups = $this->refactorSerializedArray($wooPriceGroups);
        }

        foreach ($priceGroups as $id => $name) {
            $rows[] = [
                'internalId' => $id,
                'name' => $name['value'],
                'group' => 'price_group',
                'mapping_name' => $name['mapping'],
                'mapping' => $name['mapping_value'],
            ];
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
        $rows = [];

        $target = $this->setAliases($this->Target()->getPaymentMeans());
        $paymentMeans = $this->mapArrays($this->Source()->getPaymentMeans(), $target);
        foreach ($paymentMeans as $id => $name) {
            $rows[] = [
                'internalId' => $id,
                'name' => $name['value'],
                'group' => 'payment_mean',
                'mapping_name' => $name['mapping'],
                'mapping' => $name['mapping_value'],
            ];
        }

        $target = $this->setAliases($this->Target()->getOrderStatus());
        $orderStatus = $this->mapArrays($this->Source()->getOrderStatus(), $target);
        foreach ($orderStatus as $id => $name) {
            $rows[] = [
                'internalId' => $id,
                'name' => $name['value'],
                'group' => 'order_status',
                'mapping_name' => $name['mapping'],
                'mapping' => $name['mapping_value'],
            ];
        }

        $target = $this->setAliases($this->Target()->getTaxRates());
        $taxRates = $this->mapArrays($this->Source()->getTaxRates(), $target);
        foreach ($taxRates as $id => $name) {
            $rows[] = [
                'internalId' => $id,
                'name' => $name['value'],
                'group' => 'tax_rate',
                'mapping_name' => $name['mapping'],
                'mapping' => $name['mapping_value'],
            ];
        }

        $target = $this->setAliases($this->Target()->getAttributes());
        $attributes = $this->mapArrays($this->Source()->getAttributes(), $target);
        foreach ($attributes as $id => $name) {
            $rows[] = [
                'internalId' => $id,
                'name' => $name['value'],
                'group' => 'attribute',
                'mapping_name' => $name['mapping'],
                'mapping' => $name['mapping_value'],
            ];
        }

        $target = $this->setAliases($this->Target()->getProperties());
        $attributes = $this->mapArrays($this->Source()->getProperties(), $target);
        foreach ($attributes as $id => $name) {
            $rows[] = [
                'internalId' => $id,
                'name' => $name['value'],
                'group' => 'property_options',
                'mapping_name' => $name['mapping'],
                'mapping' => $name['mapping_value'],
            ];
        }

        $target = $this->setAliases(sort($this->Target()->getConfiguratorOptions()));
        $attributes = $this->mapArrays($this->Source()->getConfiguratorOptions(), $target);
        ksort($attributes);
        foreach ($attributes as $id => $name) {
            $rows[] = [
                'internalId' => $id,
                'name' => $name['value'],
                'group' => 'configurator_mapping',
                'mapping_name' => $name['mapping'],
                'mapping' => $name['mapping_value'],
            ];
        }

        return $rows;
    }

    /**
     * Returns the selectable values for a given entity-mapping
     *
     * @param $entity
     *
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
        $rows = [
            [
                'id' => $this->namespace->get('pleaseSelect', 'Please select'),
                'name' => $this->namespace->get('pleaseSelect', 'Please select'),
            ],
        ];

        if (!empty($values)) {
            foreach ($values as $key => $value) {
                $rows[] = ['id' => $key, 'name' => $value];
            }
        }

        return $rows;
    }

    /**
     * Helper function to set an automatic mapping when the user open the mapping panel.
     *
     * @param $array
     *
     * @return mixed
     */
    public function setAliases($array)
    {
        $aliasList = [
            //Languages - Shops
            ['deutsch', 'german', 'main store', 'main', 'mainstore', 'hauptshop deutsch'],
            ['englisch', 'english', 'default english'],
            ['französisch', 'french'],
            //Payments
            ['vorkasse', 'vorauskasse', 'prepayment', 'in advance'],
            //order states
            [
                'in bearbeitung(wartet)',
                'in bearbeitung',
                'wird bearbeitet',
                'bearbeitung',
                'in progress',
                'in process',
                'processing',
            ],
            ['offen', 'open', 'opened'],
            ['komplett abgeschlossen', 'abgeschlossen', 'completed', 'fully completed', 'finish', 'finished'],
            ['teilweise abgeschlossen', 'partially completed', 'partially finished'],
            ['storniert / abgelehnt', 'storniert', 'abgelehnt', 'canceled', 'declined', 'rejected', 'denied'],
            ['zur lieferung bereit', 'lieferbereit', 'ready for delivery', 'ready for deliver', 'ready to ship'],
            [
                'klärung notwendig',
                'klärung',
                'mehr informationen notwendig',
                'clarification needed',
                'declaration needed',
                'more information needed',
            ],
            ['abgebrochen', 'canceled', 'aborted'],
            //taxes
            ['Standardsatz', 'standard tax rate', '19%', '19 %'],
            ['ermäßigter Steuersatz', 'reduced tax rate', '7%', '7 %'],
        ];

        foreach ($array as &$element) {
            $temp = $element;
            foreach ($aliasList as $alias) {
                if (in_array(strtolower($temp), $alias)) {
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
     *
     * @param $sourceArray
     * @param $targetArray
     *
     * @return mixed
     */
    private function mapArrays($sourceArray, $targetArray)
    {
        foreach ($sourceArray as &$source) {
            $source = ['value' => $source, 'mapping' => '', 'mapping_value' => ''];
            foreach ($targetArray as $key => $target) {
                if (is_array($target)) {
                    foreach ($target as $alias) {
                        if (strtolower($source['value']) == strtolower($alias)
                            || (strtolower(substr($source['value'], 0, 6)) == strtolower(substr($alias, 0, 6)))
                        ) {
                            $source['mapping'] = $target[0];
                            $source['mapping_value'] = $key;
                            break;
                        }
                    }
                } else {
                    if (strtolower($source['value']) == strtolower($target)
                        || (strtolower(substr($source['value'], 0, 6)) == strtolower(substr($target, 0, 6)))
                    ) {
                        $source['mapping'] = $target;
                        $source['mapping_value'] = $key;
                        break;
                    }
                }
            }

            if ($source['mapping'] === '' && $source['mapping_value'] === '') {
                $source['mapping'] = $this->namespace->get('pleaseSelect', 'Please select');
                $source['mapping_value'] = $this->namespace->get('pleaseSelect', 'Please select');
            }
        }

        return $sourceArray;
    }

    /**
     * This function returns an refactored unserialized array.
     *
     * @param $array
     *
     * @return array
     */
    private function refactorSerializedArray($array)
    {
        $refactoredArray = [];
        foreach ($array as $value) {
            $refactoredArray[] = [
                'value' => $value['name'],
                'mapping' => 'Bitte wählen',
                'mapping_value' => 'Bitte wählen',
            ];
        }

        return $refactoredArray;
    }
}
