<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
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
