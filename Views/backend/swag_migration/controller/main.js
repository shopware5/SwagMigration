/*
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

// {namespace name=backend/swag_migration/main}
// {block name="backend/swag_migration/controller/main"}
Ext.define('Shopware.apps.SwagMigration.controller.Main', {

    /**
     * The parent class that this class extends.
     * @string
     */
    extend: 'Ext.app.Controller',

    /**
     * Class property which holds the main application if it is created
     *
     * @default null
     * @object
     */
    mainWindow: null,

    /**
     * A template method that is called when your application boots.
     * It is called before the Application's launch function is executed
     * so gives a hook point to run any code before your Viewport is created.
     *
     * @return void
     */
    init: function () {
        var me = this;

        me.subApplication.profileStore = me.getStore('Shopware.apps.SwagMigration.store.Profile').load();
        // these stores needs to be loaded dynamically depending on the database credentials
        me.subApplication.databaseStore = me.getStore('Shopware.apps.SwagMigration.store.Database');
        me.subApplication.mappingStoreLeft = me.getStore('Shopware.apps.SwagMigration.store.MappingLeft');
        me.subApplication.mappingStoreRight = me.getStore('Shopware.apps.SwagMigration.store.MappingRight');

        me.mainWindow = me.getView('main.Window').create({
            profileStore: me.subApplication.profileStore,
            databaseStore: me.subApplication.databaseStore,
            mappingStoreLeft: me.subApplication.mappingStoreLeft,
            mappingStoreRight: me.subApplication.mappingStoreRight
        });
        me.subApplication.setAppWindow(me.mainWindow);

        me.callParent(arguments);
    }

});
// {/block}
