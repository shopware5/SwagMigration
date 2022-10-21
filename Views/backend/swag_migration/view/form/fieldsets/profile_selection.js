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
// {namespace name=backend/swag_migration/main}
// {block name="backend/swag_migration/view/form/fieldSets/profileSelection"}
Ext.define('Shopware.apps.SwagMigration.view.form.fieldsets.ProfileSelection', {
    /**
     * Define that the base field set is an extension of the Ext.form.FieldSet
     * @string
     */
    extend: 'Ext.form.FieldSet',

    /**
     * List of short aliases for class names. Most useful for defining xtypes for widgets.
     * @string
     */
    alias: 'widget.migration-fieldset-profile-selection',

    /**
     * Title of the fieldset
     */
    title: '{s name="selectProfile"}Select profile{/s}',

    /**
     * Default style for the child elements
     */
    defaults: { anchor: '100%' },

    initComponent: function() {
        var me = this;

        me.items = me.getItems();

        me.callParent(arguments);
    },

    /**
     * Creates the fieldset with the profile selection
     * @return Array
     */
    getItems: function() {
        var me = this;

        me.profileSelection = Ext.create('Ext.form.ComboBox', {
            fieldLabel: '{s name="profile"}Profile{/s}',
            name: 'profile',
            hiddenName: 'profile',
            valueField: 'id',
            displayField: 'name',
            typeAhead: true,
            triggerAction: 'all',
            xtype: 'combo',
            allowBlank: false,
            mode: 'remote',
            selectOnFocus: true,
            forceSelection: true,
            editable: false,
            store: me.profileStore,
            listeners: {
                change: function(combo, newValue) {
                    if (newValue) {
                        var databaseSelection = me.up().down('migration-fieldset-database-selection').databaseSelection;
                        databaseSelection.setDisabled(false);
                        databaseSelection.emptyText = '{s name="selectDatabaseWhenSettingsMatch"}Select source database if above settings do match{/s}';
                        databaseSelection.select(null);
                    }
                }
            }
        });

        return [
            {
                xtype: 'label',
                text: '{s name="profileSelectDescription"}Select the shop you want to migrate to Shopware{/s}'
            },
            me.profileSelection
        ];
    }

});
// {/block}
