/*
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Shopware Store - database store
 * Holds a list of database names
 */
//{block name="backend/swag_migration/store/database"}
Ext.define('Shopware.apps.SwagMigration.store.Database', {
    /**
     * Extend for the standard ExtJS 4
     * @string
     */
    extend: 'Ext.data.Store',
    /**
     * Define the used model for this store
     * @string
     */
    model: 'Shopware.apps.SwagMigration.model.Database',
    /**
     * Configure the data communication
     * @object
     */
    proxy: {
        /**
         * Set proxy type to ajax
         * @string
         */
        type: 'ajax',

        /**
         * Configure the url mapping for the different
         * store operations based on
         * @object
         */
        url: '{url action="databaseList"}',

        /**
         * Configure the data reader
         * @object
         */
        reader: {
            type: 'json',
            root: 'data',
            totalProperty: 'total'
        },

//        actionMethods: {
//            read   : 'POST'
//        }
    }
});
//{/block}

