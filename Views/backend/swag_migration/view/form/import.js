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
 *
 * @category   Shopware
 * @package    Migration
 * @subpackage Form
 * @copyright  Copyright (c) 2012, shopware AG (http://www.shopware.de)
 * @version    $Id$
 * @author shopware AG
 */

/**
 * Shopware UI - Migration import form
 * Lets the user select data to be imported and starts the actual import
 */
//{namespace name=backend/swag_migration/main}
//{block name="backend/swag_migration/view/form/import"}
Ext.define('Shopware.apps.SwagMigration.view.form.Import', {
    /**
     * Define that the billing field set is an extension of the Ext.form.FieldSet
     * @string
     */
    extend: 'Ext.form.Panel',
    bodyStyle: 'padding:10px',
    /**
     * The Ext.container.Container.layout for the fieldset's immediate child items.
     * @object
     */
    layout: 'anchor',

    /**
     * List of short aliases for class names. Most useful for defining xtypes for widgets.
     * @string
     */
    alias: 'widget.migration-form-import',
    /**
     * Set css class for this component
     * @string
     */
    cls: 'shopware-form',

    /**
     * will be set to true if the current profile needs an additional salt to be entered by the user
     */
    saltInputNeeded: false,

    /**
	 * The initComponent template method is an important initialization step for a Component.
     * It is intended to be implemented by each subclass of Ext.Component to provide any needed constructor logic.
     * The initComponent method of the class being created is called first,
     * with each initComponent method up the hierarchy to Ext.Component being called thereafter.
     * This makes it easy to implement and, if needed, override the constructor logic of the Component at any step in the hierarchy.
     * The initComponent method must contain a call to callParent in order to ensure that the parent class' initComponent method is also called.
	 *
	 * @return void
	 */
    initComponent:function () {
        var me = this;

        me.addEvents(
            /**
             * Fired when the supplier combo changes in order to trigger form validation
             */
            'validate'
        );

        me.items = me.createItems();
        me.callParent(arguments);
    },

    /**
     * Returns an array with the fieldsets available in this view
     * @return Array
     */
    createItems: function() {
        var me = this;

        me.fieldSet = {
            xtype:'fieldset',
            layout: 'column',
            title: '{s name=importSettings}Import settings{/s}',
            autoHeight: true,
            defaults: {
                anchor: '100%',
                labelWidth: 500,
                buttonAlign: 'left'
            },
            defaultType: 'textfield',
            items : me.getContainers()
        };

        me.advancedOptionsFieldset = {
            xtype:'fieldset',
            title: '{s name=importSettingsAdvanced}Advanced import settings{/s}',
            autoHeight: true,
            defaults: {
                anchor: '100%',
                labelWidth: 500
            },
            items : me.getAdvancedInputItems()
        };

        var clearShopFieldSet = {
            xtype:'migration-fieldset-clearshop',
            collapsible: true,
            collapsed: true
        };

        return  [ me.fieldSet, me.advancedOptionsFieldset, clearShopFieldSet ];
    },

    getContainers:function () {
        var leftContainer, rightContainer, me = this;

        leftContainer = Ext.create('Ext.container.Container', {
            columnWidth:0.3,
            defaults: {
                labelWidth: 250,
                anchor: '100%',
                buttonAlign: 'left',
                checked: true
            },
            padding: '0 20 0 0',
            layout: 'anchor',
            border:false,
            items:me.getLeftItems()
        });

        rightContainer = Ext.create('Ext.container.Container', {
            columnWidth:0.7,
            layout: 'anchor',
            defaults: {
                labelWidth: 250,
                anchor: '100%',
                buttonAlign: 'left',
                checked: true
            },
            padding: '0 0 0 80',
            border:false,
            items:me.getRightItems()
        });

        return [ leftContainer, rightContainer ] ;
    },

    getLeftItems: function() {
        var me = this;

        return [{
            fieldLabel: '{s name=importProducts}Import products{/s}',
            name: 'import_products',
            xtype: 'checkbox'
        }, {
            fieldLabel: '{s name=importTranslations}Import translations{/s}',
            name: 'import_translations',
            xtype: 'checkbox'
        }, {
            fieldLabel: '{s name=importProperties}Import product properties{/s}',
            name: 'import_properties',
            xtype: 'checkbox'
        }, {
            fieldLabel: '{s name=importCategories}Import categories{/s}',
            name: 'import_categories',
            xtype: 'checkbox'
        }, {
            fieldLabel: '',
            name: 'import_article_categories',
            xtype: 'checkbox',
            checked: false,
            hidden: true
        }, {
            fieldLabel: '{s name=importPrices}Import customer group prices{/s}',
            name: 'import_prices',
            xtype: 'checkbox'
        }, {
            fieldLabel: '{s name=generateVariants}Generate variants from attributes{/s}',
            name: 'import_generate_variants',
            xtype: 'checkbox'
        }, {
            fieldLabel: '',
            name: 'import_create_configurator_variants',
            xtype: 'checkbox',
            checked: false,
            hidden: true
        }];
    },

    getRightItems: function() {
        var me = this;

        me.basePath = Ext.create('Ext.form.field.Text', {
            fieldLabel: '{s name=articleImagesPath}Shop path (e.g. http://www.example.org/old_shop or /var/www/old_shop){/s}',
            name: 'basepath',
            value: '',
            labelWidth: 250,
            allowBlank: false,
            listeners: {
                change: function() {
                    me.fireEvent('validate');
                }
            }
        });

        me.saltInput = Ext.create('Ext.form.field.Text', {
            fieldLabel: '{s name=saltInput}Password Salt{/s}',
            name: 'salt',
            value: '',
            labelWidth: 250,
            allowBlank: true,
            helpText: "{s name=saltInputHelp}The destination shop uses a salt to make its password more secure. In this special case the salt cannot be read automatically. Please copy the salt from /destination_shop/config/settings.inc.php to this field. It is defined as 'COOKIE KEY' there.{/s}",
            hidden: !me.saltInputNeeded,
            listeners: {
                change: function() {
                    me.fireEvent('validate');
                }
            }
        });

        return [{
            fieldLabel: '{s name=importArticleImages}Import product images{/s}',
            name: 'import_images',
            xtype: 'checkbox',
            listeners: {
                change: function(checkBox, newValue, oldValue, eOpts) {
                    // if the product images are going to be imported, the basePath field is mandatory
                    me.basePath.allowBlank = !newValue;
                    if(newValue) {
                        me.basePath.show();
                    }else{
                        me.basePath.hide();
                    }
                    me.fireEvent('validate');
                }
            }
        }, {
            fieldLabel: '{s name=importCustomers}Import customers{/s}',
            name: 'import_customers',
            xtype: 'checkbox',
            listeners: {
                change: function(checkBox, newValue, oldValue, eOpts) {
                    if(newValue && me.saltInputNeeded) {
                        me.saltInput.show();
                        me.saltInput.allowBlank = false;
                    }else{
                        me.saltInput.hide();
                        me.saltInput.allowBlank = true;
                    }
                    me.fireEvent('validate');
                }
            }
        }, {
            fieldLabel: '{s name=importRatings}Import ratings{/s}',
            name: 'import_ratings',
            xtype: 'checkbox'
        }, {
            fieldLabel: '{s name=importOrders}Import orders{/s}',
            name: 'import_orders',
            xtype: 'checkbox'
        }, {
            fieldLabel: '{s name=finish}Finish import{/s}',
            name: 'finish_import',
            xtype: 'checkbox',
            checked: false
        }, {
            fieldLabel: '{s name=defaultSupplier}Default supplier{/s}',
            name: 'supplier',
            hiddenName: 'supplier',
            valueField: 'name',
            displayField: 'name',
            triggerAction: 'all',
            xtype: 'combo',
            allowBlank: false,
            mode: 'remote',
            selectOnFocus: true,
            store: Ext.create('Shopware.apps.Base.store.Supplier'),
            listeners: {
                change: function() {
                    me.fireEvent('validate');
                }
            }
        },
            me.basePath,
            me.saltInput
        ];

    },

    getAdvancedInputItems: function() {
        var me = this;

        var radioGroup = Ext.create('Ext.form.RadioGroup', {
                fieldLabel: '{s name=handleInvalidProductNumbers}How to handle invalid product numbers{/s}',
                labelWidth: 500,
                columns: 1,
                items: [
                    {
                        xtype: 'radiofield',
                        boxLabel: '{s name=handleProductNumber/complain}Complain about invalid product numbers{/s}',
                        name: 'number_validation_mode',
                        checked: true,
                        inputValue: 'complain'
                    },
                    {
                        xtype: 'radiofield',
                        boxLabel: '{s name=handleProductNumber/convert}Generate new valid numbers{/s}',
                        name: 'number_validation_mode',
                        inputValue: 'make_valid'
                    },
                    {
                        xtype: 'radiofield',
                        boxLabel: '{s name=handleProductNumber/ignore}Ignore invalid product numbers (not recommended){/s}',
                        name: 'number_validation_mode',
                        inputValue: 'ignore'
                    }
                ]
            });


        return [radioGroup];

    },

    setSaltInputNeeded: function(value) {
        var me = this;

        me.saltInputNeeded = value;

        if(value) {
            me.saltInput.show();
            me.saltInput.allowBlank = false;
        }else{
            me.saltInput.hide();
            me.saltInput.allowBlank = true;
        }
    }


});
//{/block}
