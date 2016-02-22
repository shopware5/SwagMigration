/*
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Shopware Model - Profile models
 * Holds a profile record representing an available profile to migrate from
 */
//{block name="backend/swag_migration/model/profile"}
Ext.define('Shopware.apps.SwagMigration.model.Profile', {
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
		//{block name="backend/swag_migration/model/profile/fields"}{/block}
        { name: 'id', type: 'string' },
        { name: 'name', type: 'string' }
    ]

});
//{/block}
