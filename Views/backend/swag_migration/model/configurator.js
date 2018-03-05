/*
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Shopware Model - Article models.
 * The configurator model is responsible to create the article variants based on the configurator settings.
 */
// {block name="backend/swag_migration/model/configurator"}
Ext.define('Shopware.apps.SwagMigration.model.Configurator', {
    /**
     * Extends the standard Ext Model
     * @string
     */
    extend: 'Ext.data.Model',

    /**
     * The fields used for this model
     * @array
     */
    fields: [
        // {block name="backend/swag_migration/model/configurator/fields"}{/block}
        'articleId', 'setId', 'offset', 'limit', 'totalCount', 'mergeType'
    ],

    associations: [
        {
            type: 'hasMany',
            model: 'Shopware.apps.SwagMigration.model.ConfiguratorGroup',
            name: 'getConfiguratorGroups',
            associationKey: 'groups'
        }
    ],

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
        api: {
            create: '{url controller="article" action="createConfiguratorVariants"}',
            update: '{url controller="article" action="createConfiguratorVariants"}'
        },

        /**
         * Configure the data reader
         * @object
         */
        reader: {
            type: 'json',
            root: 'data',
            totalProperty: 'total'
        }
    }

});
// {/block}
