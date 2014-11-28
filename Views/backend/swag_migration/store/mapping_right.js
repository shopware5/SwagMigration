/*
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Shopware Store - mappingRight store
 * Holds additional mappings for the shop
 */
//{block name="backend/swag_migration/store/mapping_right"}
Ext.define('Shopware.apps.SwagMigration.store.MappingRight', {
    /**
     * Extend for the standard ExtJS 4
     * @string
     */
    extend: 'Ext.data.Store',
    groupField: 'group',

    /**
     * Define the used model for this store
     * @string
     */
    model: 'Shopware.apps.SwagMigration.model.Mapping',
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
        url: '{url action="mappingListRight"}',

        /**
         * Configure the data reader
         * @object
         */
        reader: {
            type: 'json',
            root: 'data',
            totalProperty: 'count'
        }
    }
});
//{/block}

