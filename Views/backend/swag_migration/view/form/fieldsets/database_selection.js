/*
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Shopware UI - Migration database form
 * DatabaseSelection fieldset
 */
//{namespace name=backend/swag_migration/main}
//{block name="backend/swag_migration/view/form/fieldSets/databaseSelection"}
Ext.define('Shopware.apps.SwagMigration.view.form.fieldsets.DatabaseSelection', {
    /**
     * Define that the base field set is an extension of the Ext.form.FieldSet
     * @string
     */
    extend:'Ext.form.FieldSet',

    /**
     * List of short aliases for class names. Most useful for defining xtypes for widgets.
     * @string
     */
    alias:'widget.migration-fieldset-database-selection',

    /**
     * Title of the fieldSet
     */
    title: '{s name=databaseSettings}Database settings{/s}',

    /**
     * Default config for the cild elements
     */
    defaults: { anchor: '100%' },

    /**
     * Default child type
     */
    defaultType: 'textfield',


    initComponent: function() {
        var me = this;

        me.items = me.getItems();

        me.callParent(arguments);
    },


    /**
     * Creates the database selection combobox
     * @return Ext.form.ComboBox
     */
    createDatabaseSelection: function() {
        var me = this;

        me.databaseSelection = Ext.create('Ext.form.ComboBox', {
            store: me.databaseStore,
            fieldLabel: '{s name=database}Database{/s}',
            name: 'database',
            displayField: 'name',
            allowBlank: false,
            mode: 'remote',
            disabled: true,
            emptyText: '{s name=selctProfileFirst}You need to select a profile first{/s}',
            listeners: {
                'beforequery': { fn: function(e) {
                    var form = this.up().getForm(),
                        values = form.getValues();

                    if (values.profile === Ext.undefined) {
                        return false;
                    }

                    // Add database settings to the request
                    e.combo.store.getProxy().extraParams = values;

                    // Load the store with the database settings from above
                    e.combo.store.load(function(data, operation, success) {
                        if (!success) {
                            var rawData = operation.request.proxy.reader.jsonData,
                                message = rawData.message;
                            Shopware.Notification.createGrowlMessage('{s name=getDatabases/errorTitle}Error{/s}', '{s name=getDatabases/errorMessage}Could not get databases{/s}' + '<br />' + message, 'SwagMigration');
                            e.combo.emptyText = '{s name=selectDatabaseWhenSettingsMatch}Select source database if above settings do match{/s}';
                            e.combo.select(null);
                        }
                    });
                }, scope: this }
            }
        });

        return me.databaseSelection;
    },

    /**
     * Creates the fieldset with the database settings
     * @return Array
     */
    getItems: function() {
        var me = this;

        me.createDatabaseSelection();


        me.databaseSettingsForm = [{
                xtype: 'label',
                text: '{s name=dbDescription}Database settings for the shop you want to migrate{/s}'
            }, {
				fieldLabel: '{s name=dbUser}User{/s}',
				name: 'username',
				value: 'root'
			}, {
				fieldLabel: '{s name=dbPassword}Password{/s}',
				name: 'password',
				inputType: 'password',
				value: 'root'
			}, {
				fieldLabel: '{s name=dbServer}Server{/s}',
				name: 'host',
				value: 'localhost',
				allowBlank: false
			}, {
				fieldLabel: '{s name=dbPort}Port{/s}',
				name: 'port',
				value: 'default'
			}, {
				fieldLabel: '{s name=dbPrefix}Prefix{/s}',
				name: 'prefix',
				value: 'default'
			}, me.databaseSelection
        ];

        return me.databaseSettingsForm;

    }


});
// {/block}
