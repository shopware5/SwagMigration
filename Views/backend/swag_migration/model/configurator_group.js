/*
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Shopware Model - Article models.
 * The article model contains all data about the article. Additional the model
 * contains the definition of the model associations. The article model
 * contains a proxy to define which request has to been sent to save, update and delete
 * the article.
 */
// {block name="backend/swag_migration/model/configurator_group"}
Ext.define('Shopware.apps.SwagMigration.model.ConfiguratorGroup', {
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
        // {block name="backend/swag_migration/model/configurator_group/fields"}{/block}
        { name: 'id', type: 'int' },
        { name: 'active', type: 'boolean' },
        { name: 'name', type: 'string' },
        { name: 'description', type: 'string', useNull: true, defaultValue: null },
        { name: 'position', type: 'int' }
    ],
    associations: [
        {
            type: 'hasMany',
            model: 'Shopware.apps.SwagMigration.model.ConfiguratorOption',
            name: 'getConfiguratorOptions',
            associationKey: 'options'
        }
    ]

});
// {/block}
