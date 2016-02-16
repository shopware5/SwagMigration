/*
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Shopware UI - Migration database form
 * ClearShop fieldset
 */
//{namespace name=backend/swag_migration/main}
//{block name="backend/swag_migration/view/form/fieldSets/clearShop"}
Ext.define('Shopware.apps.SwagMigration.view.form.fieldsets.ClearShop', {
    /**
     * Define that the base field set is an extension of the Ext.form.FieldSet
     * @string
     */
    extend:'Ext.form.FieldSet',
    /**
     * List of short aliases for class names. Most useful for defining xtypes for widgets.
     * @string
     */
    alias:'widget.migration-fieldset-clearshop',

    /**
     * Layout type for the component.
     * @string
     */
    layout: 'anchor',

    autoHeight: true,

    title: '{s name=initShop}Initialize Shop{/s}',

    initComponent: function() {
        var me = this;

        me.items = me.getClearShopElements();

        me.callParent(arguments);
    },

    /**
     *
     * @return Array
     */
    getClearShopElements: function() {
        var me = this;

        var leftContainer = Ext.create('Ext.container.Container', {
            columnWidth:.3,
            border:false,
            cls: Ext.baseCSSPrefix + 'field-set-container',
            layout:'anchor',
            defaults:{
                anchor:'100%',
                labelWidth:150,
                minWidth:250,
                xtype:'textfield'
            },
            items:me.getLeftElements()
        });

        var middleContainer = Ext.create('Ext.container.Container', {
            columnWidth:.3,
            border:false,
            cls: Ext.baseCSSPrefix + 'field-set-container',
            layout:'anchor',
            defaults:{
                anchor:'100%',
                labelWidth:100,
                xtype:'textfield'
            },
            items:me.getMiddleElements()
        });

        var rightContainer = Ext.create('Ext.container.Container', {
            columnWidth:.3,
            border:false,
            cls: Ext.baseCSSPrefix + 'field-set-container',
            layout:'anchor',
            defaults:{
                anchor:'100%',
                labelWidth:100,
                xtype:'textfield'
            },
            items:me.getRightElements()
        });

        var checkBoxes = Ext.create('Ext.container.Container', {
            border:false,
            layout:'column',
            items:[ leftContainer, middleContainer, rightContainer]
        });

        return [checkBoxes, me.getClearShopButtons()];
    },

    /**
     * Helper function which returns the left side of the "init shop" fieldSet
     * @return Array
     */
    getLeftElements: function() {
        var me = this;

        me.leftCheckBoxes = [
            Ext.create('Ext.form.field.Checkbox', {
                boxLabel: '{s name=clearArticles}Delete articles{/s}',
                name: 'clear_articles'
            }),
            Ext.create('Ext.form.field.Checkbox', {
                boxLabel: '{s name=clearCategories}Delete categories{/s}',
                name: 'clear_categories'
            }),
            Ext.create('Ext.form.field.Checkbox', {
                boxLabel: '{s name=clearImages}Delete images{/s}',
                name: 'clear_images'
            }),
            Ext.create('Ext.form.field.Checkbox', {
                boxLabel: '{s name=clearProperties}Delete product properties{/s}',
                name: 'clear_properties'
            })
        ];

        return me.leftCheckBoxes;
    },

    /**
     * Helper function which returns the middle of the "init shop" fieldSet
     * @return Array
     */
    getMiddleElements: function() {
        var me = this;

        me.middleCheckBoxes = [
            Ext.create('Ext.form.field.Checkbox', {
                boxLabel: '{s name=clearCustomers}Delete customer{/s}',
                name: 'clear_customers'
            }),
            Ext.create('Ext.form.field.Checkbox', {
                boxLabel: '{s name=clearCustomersAndOrders}Delete customers and orders{/s}',
                name: 'clear_orders'
            }),
            Ext.create('Ext.form.field.Checkbox', {
                boxLabel: '{s name=clearArticleDownloads}Delete article downloads{/s}',
                name: 'clear_article_downloads'
            }),
            Ext.create('Ext.form.field.Checkbox', {
                boxLabel: '{s name=clearEsdArticleDownloads}Delete ESD article downloads{/s}',
                name: 'clear_esd_article_downloads'
            })
        ];

        return me.middleCheckBoxes;
    },

    /**
     * Helper function which returns the right side of the "init shop" fieldSet
     * @return Array
     */
    getRightElements: function() {
        var me = this;

        me.rightCheckBoxes = [
            Ext.create('Ext.form.field.Checkbox', {
                boxLabel: '{s name=clearVotes}Delete votes{/s}',
                name: 'clear_votes'
            }),
            Ext.create('Ext.form.field.Checkbox', {
                boxLabel: '{s name=clearSupplier}Delete supplier{/s}',
                name: 'clear_supplier'
            })
//            Ext.create('Ext.form.field.Checkbox', {
//                boxLabel: '{s name=clearMappings}Clear temporary mappings{/s}',
//                name: 'clear_mappings'
//            })
        ];

        return me.rightCheckBoxes
    },

    /**
     * Helper function which returns the "Delete selected data" buttons
     * @return Ext.container.Container
     */
    getClearShopButtons: function() {
        var me = this;

        var deleteButton = Ext.create('Ext.button.Button', {
            tooltip: '{s name=clearShop}Delete selected {/s}',
            name: 'deleteArticlesAndCategories',
            text: '{s name=deleteCategoriesAndArticles}Delete selected data{/s}',
            cls: 'primary',
            scope: this,
            handler  : function() {
                Ext.Msg.show({
                    title: '{s name=initShop}Reset this Shop{/s}',
                    msg: '{s name=initShop/AreYouSure}Are you sure? All selected elements will be deleted permanently.{/s}',
                    buttons: Ext.Msg.YESNO,
                    scope: this,
                    fn: function (btn) {
                        if (btn === "yes") {
                            var form = me.up('form');
                            form.setLoading(true);
                            Ext.Ajax.request({
                                url: '{url action="clearShop"}',
                                params: form.getForm().getValues(),
                                success: function (r, o){
                                    form.setLoading(false);
                                    Shopware.Notification.createGrowlMessage('{s name=deleted/successTitle}Success{/s}', '{s name=deleted/successMessage}Successfully delete all categories and articles{/s}', 'SwagMigration');

                                },
                                scope: this
                            });
                        }
                    },
                    icon: Ext.MessageBox.WARNING
                });
            }
        });

        var inverseButton = Ext.create('Ext.button.Button', {
            tooltip: '',
            name: 'inverseSelection',
            text: '{s name=inverseSelection}Inverse selection{/s}',
            cls: 'small',
            style: 'position: absolute;right:0;top:10px;',
            scope: this,
            handler  : function() {
                var me = this;
                Ext.each(me.leftCheckBoxes.concat(me.middleCheckBoxes, me.rightCheckBoxes), function(checkbox) {
                    checkbox.setValue(!checkbox.getValue());
                });

            }
        });


        return Ext.create('Ext.container.Container', {
            border: false,
            layout: 'anchor',
            items: [ deleteButton,  inverseButton ]
        });

    }


});
// {/block}
