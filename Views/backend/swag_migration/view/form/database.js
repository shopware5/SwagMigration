/*
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Shopware UI - Migration database form
 * Form for the database and import settings
 */
//{namespace name=backend/swag_migration/main}
//{block name="backend/swag_migration/view/form/database"}
Ext.define('Shopware.apps.SwagMigration.view.form.Database', {
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
    alias: 'widget.migration-form-database',
    /**
     * Set css class for this component
     * @string
     */
    cls: 'shopware-form',

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

        me.items = me.createItems();
        me.callParent(arguments);
    },

    /**
     * Returns an array with the fieldsets available in this view
     * @return Array
     */
    createItems: function() {
        var me = this;

        return [{
                xtype: 'migration-fieldset-profile-selection',
                profileStore:  me.profileStore
            },{
                xtype:'migration-fieldset-database-selection',
                databaseStore: me.databaseStore

            },{
                xtype:'migration-fieldset-clearshop'
        }];
    }


});
//{/block}
