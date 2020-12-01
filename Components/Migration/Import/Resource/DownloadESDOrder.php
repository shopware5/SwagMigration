<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagMigration\Components\Migration\Import\Resource;

use Shopware\SwagMigration\Components\Migration\Import\Progress;
use ZEND_Db;

class DownloadESDOrder extends AbstractResource
{
    /**
     * {@inheritdoc}
     */
    public function getDefaultErrorMessage()
    {
        return $this->getNameSpace()->get('errorImportingMedia', 'An error occurred while importing media');
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentProgressMessage(Progress $progress)
    {
        return \sprintf(
            $this->getNameSpace()->get('progressDownload', '%s out of %s ESD Orders imported'),
            $this->getProgress()->getOffset(),
            $this->getProgress()->getCount()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getDoneMessage()
    {
        return $this->getNameSpace()->get('importedDownload', 'ESD Orders successfully imported!');
    }

    /**
     * import adapter for ESD orders
     *
     * {@inheritdoc}
     */
    public function run()
    {
        $offset = $this->getProgress()->getOffset();

        $result = $this->Source()->queryEsdOrder();

        if (empty($result)) {
            return $this->getProgress()->done();
        }

        $count = $result->rowCount() + $offset;
        $this->getProgress()->setCount($count);

        while ($order = $result->fetch()) {
            $orderNumber = $order['ordernumber'];
            $filename = $order['filename'];
            $orderDate = $order['orderdate'];

            // get sw orderId, userId, orderDetailsId
            list($orderId, $userId, $orderDetailsId) = Shopware()->Db()->fetchRow(
                'SELECT o.id, o.userID, od.id FROM s_order o INNER JOIN s_order_details od ON o.id = od.orderID WHERE o.ordernumber = ?',
                [
                    $orderNumber,
                ],
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
                'SELECT id FROM s_articles_esd WHERE file = ? LIMIT 1',
                [$filename]
            );

            // Insert into esd orders
            Shopware()->Db()->query(
                'INSERT INTO s_order_esd (serialID, esdID, userID, orderID, orderdetailsID, datum) VALUES (?,?,?,?,?,?)',
                [
                    0,  // we don't support serial numbers yet
                    $esdId,
                    $userId,
                    $orderId,
                    $orderDetailsId,
                    $orderDate,
                ]
            );

            // Mark this order as ESD order
            Shopware()->Db()->query(
                'UPDATE s_order_details SET esdarticle = 1, ordernumber = ?, price = 1, releasedate = ? WHERE orderID = ?',
                [$orderNumber, $orderDate, $orderId]
            );

            // this query actually enables the ESD Downloads to be downloadable in the frontend - set the payment-status (cleared) to 12 (completely paid)
            Shopware()->Db()->query(
                'UPDATE s_order SET cleared = 12 WHERE ordernumber = ?',
                [$orderNumber]
            );
        }

        return $this->getProgress()->done();
    }
}
