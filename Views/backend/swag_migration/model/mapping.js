/*
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Shopware Model - mapping model
 * Represents a mapping from the old shop to the new shop
 */
// {block name="backend/swag_migration/model/mapping"}
Ext.define('Shopware.apps.SwagMigration.model.Mapping', {
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
        // {block name="backend/swag_migration/model/mapping/fields"}{/block}
        { name: 'id', type: 'int' },
        { name: 'internalId', type: 'string' },    // e.g. anothershop_1
        { name: 'name', type: 'string' },           // e.g. Another Shop 1
        { name: 'mapping', type: 'string' },        // id of the mapping, e.g. 1
        { name: 'mapping_name', type: 'string' },  // name of the mapping, e.g. "Deutsch"
        { name: 'group', type: 'string' },         // e.g. "Shop"
        { name: 'required', type: 'string' }
    ]

});
// {/block}
