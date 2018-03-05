/*
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Shopware Model - database model
 * Represents a database
 */
// {block name="backend/swag_migration/model/database"}
Ext.define('Shopware.apps.SwagMigration.model.Database', {
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
        // {block name="backend/swag_migration/model/database/fields"}{/block}
        { name: 'id', type: 'int' },
        { name: 'name', type: 'string' }
    ]

});
// {/block}
