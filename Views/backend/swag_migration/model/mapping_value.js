/*
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Shopware Model - mapping value model
 * Represents a valued available for a specific mapping
 */
// {block name="backend/swag_migration/model/mapping_value"}
Ext.define('Shopware.apps.SwagMigration.model.MappingValue', {
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
        // {block name="backend/swag_migration/model/mapping_value/fields"}{/block}
        { name: 'id', type: 'string' },
        { name: 'name', type: 'string' }
    ]

});
// {/block}
