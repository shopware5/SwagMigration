<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\SwagMigration\Components\DbServices;

use Enlight_Components_Db_Adapter_Pdo_Mysql as PDOConnection;

class DeleteService
{
    /** @var PDOConnection $db */
    private $db = null;

    /**
     * DeleteService constructor.
     *
     * @param PDOConnection $db
     */
    public function __construct(PDOConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @return bool
     */
    public function deleteAllCategories()
    {
        $sql = 'SELECT category_id
                FROM s_core_shops';
        $shopCategoriesIds = $this->db->fetchCol($sql);

        //don't delete shop's categories
        if (empty($shopCategoriesIds)) {
            $sql = 'TRUNCATE s_categories';
        } else {
            $ids = 'id != ' . implode(' AND id != ', $shopCategoriesIds);
            $sql = 'DELETE FROM s_categories
                    WHERE parent IS NOT NULL
                      AND ' . $ids;
        }

        if ($this->db->exec($sql) === false) {
            return false;
        }
        if ($this->db->exec('TRUNCATE s_articles_categories') === false) {
            return false;
        }
        if ($this->db->exec('TRUNCATE s_emarketing_banners') === false) {
            return false;
        }

        $sql = 'SELECT MAX(category_id)
                FROM s_core_shops';
        $lastCategoryId = $this->db->fetchOne($sql);
        $auto_increment = empty($lastCategoryId) ? 2 : $lastCategoryId + 1;

        $sql = 'ALTER TABLE s_categories AUTO_INCREMENT = ' . $auto_increment;
        if ($this->db->exec($sql) === false) {
            return false;
        }

        return true;
    }
}
