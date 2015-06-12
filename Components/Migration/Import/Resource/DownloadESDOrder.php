<?php
/**
 * Shopware 4.0
 * Copyright © 2013 shopware AG
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

/**
 * Shopware SwagMigration Components - Download
 *
 * ESD orders import adapter
 *
 * @category  Shopware
 * @package   Shopware\Plugins\SwagMigration\Components\Migration\Import\Resource
 * @copyright Copyright (c), shopware AG (http://www.shopware.de)
 */
class Shopware_Components_Migration_Import_Resource_DownloadESDOrder extends Shopware_Components_Migration_Import_Resource_Abstract
{
    public function getDefaultErrorMessage()
    {
        return $this->getNameSpace()->get('errorImportingMedia', "An error occurred while importing media");
    }

    public function getCurrentProgressMessage($progress)
    {
        return sprintf(
            $this->getNameSpace()->get('progressDownload', "%s out of %s ESD Orders imported"),
            $this->getProgress()->getOffset(),
            $this->getProgress()->getCount()
        );
    }

    public function getDoneMessage()
    {
        return $this->getNameSpace()->get('importedDownload', "ESD Orders successfully imported!");
    }

    /**
     * run() method of the import adapter for ESD orders
     *
     * @return $this|Shopware_Components_Migration_Import_Progress
     */
    public function run()
    {
        $offset = $this->getProgress()->getOffset();

        $result = $this->Source()->queryEsdOrder();
        $count = $result->rowCount() + $offset;
        $this->getProgress()->setCount($count);


        while ($order = $result->fetch()) {
            $orderNumber = $order['ordernumber'];
            $filename = $order['filename'];
            $orderDate = $order['orderdate'];

            // get sw orderId, userId, orderDetailsId
            list($orderId, $userId, $orderDetailsId) = Shopware()->Db()->fetchRow(
                "SELECT o.id, o.userID, od.id FROM s_order o INNER JOIN s_order_details od ON o.id = od.orderID WHERE o.ordernumber = ?",
                array(
                    $orderNumber
                ),
                ZEND_Db::FETCH_NUM
            );

            // no userId was found -> skip this ESD order
            if (empty($userId)) {
                continue;
            }

            $this->increaseProgress();
            if ($this->newRequestNeeded()) {
                return $this->getProgress();
            }

            $esdId = Shopware()->Db()->fetchOne(
                "SELECT id FROM s_articles_esd WHERE file = ? LIMIT 1",
                array($filename)
            );

            // Insert into esd orders
            Shopware()->Db()->query(
                "INSERT INTO s_order_esd (serialID, esdID, userID, orderID, orderdetailsID, datum) VALUES (?,?,?,?,?,?)",
                array(
                    0,  // we don't support serial numbers yet
                    $esdId,
                    $userId,
                    $orderId,
                    $orderDetailsId,
                    $orderDate
                )
            );

            // Mark this order as ESD order
            Shopware()->Db()->query(
                "UPDATE s_order_details SET esdarticle = 1, ordernumber = ?, price = 1, releasedate = ? WHERE orderID = ?",
                array($orderNumber, $orderDate, $orderId)
            );

            // this query actually enables the ESD Downloads to be downloadable in the frontend - set the payment-status (cleared) to 12 (completely paid)
            Shopware()->Db()->query(
                "UPDATE s_order SET cleared = 12 WHERE ordernumber = ?",
                array($orderNumber)
            );
        }

        return $this->getProgress()->done();
    }
}
