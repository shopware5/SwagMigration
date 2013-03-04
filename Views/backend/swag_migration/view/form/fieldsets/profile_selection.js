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
 * Shopware UI - Migration database form
 * DatabaseSelection fieldset
 */
//{namespace name=backend/swag_migration/main}
//{block name="backend/swag_migration/view/form/fieldSets/profileSelection"}
Ext.define('Shopware.apps.SwagMigration.view.form.fieldsets.ProfileSelection', {
    /**
     * Define that the base field set is an extension of the Ext.form.FieldSet
     * @string
     */
    extend:'Ext.form.FieldSet',

    /**
     * List of short aliases for class names. Most useful for defining xtypes for widgets.
     * @string
     */
    alias:'widget.migration-fieldset-profile-selection',

    /**
     * Title of the fieldset
     */
    title: '{s name=selectProfile}Select profile{/s}',

    /**
     * Default style for the child elements
     */
    defaults:{ anchor:'100%' },

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
            fieldLabel: '{s name=profile}Profile{/s}',
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
            forceSelection : true,
            editable: false,
            store: me.profileStore,
            listeners: {
                change: function(combo, newValue, oldValue, eOpts) {
                    if (newValue) {
                        var databaseSelection = me.up().down('migration-fieldset-database-selection').databaseSelection
                        databaseSelection.setDisabled(false);
                        databaseSelection.emptyText = '{s name=selectDatabaseWhenSettingsMatch}Select source database if above settings do match{/s}';
                        databaseSelection.select(null);
                    }
                }
            }
        });

        return [{
                xtype: 'label',
                text: '{s name=profileSelectDescription}Select the shop you want to migrate to Shopware{/s}'
            },
            me.profileSelection
        ];

    }

});
// {/block}