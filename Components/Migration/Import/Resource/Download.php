<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagMigration\Components\Migration\Import\Resource;

use Shopware\SwagMigration\Components\Migration\Import\Progress;

class Download extends AbstractResource
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
        return sprintf(
            $this->getNameSpace()->get('progressDownload', '%s out of %s downloads imported'),
            $this->getProgress()->getOffset(),
            $this->getProgress()->getCount()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getDoneMessage()
    {
        return $this->getNameSpace()->get('importedDownload', 'Downloads successfully imported!');
    }

    /**
     * import adapter for downloads (article attached)
     *
     * {@inheritdoc}
     */
    public function run()
    {
        $offset = $this->getProgress()->getOffset();
        $numberValidationMode = $this->Request()->getParam('number_validation_mode', 'complain');

        /** @var \Zend_Db_Statement_Interface $result */
        $result = $this->Source()->queryArticleDownload();

        if (empty($result)) {
            return $this->getProgress()->done();
        }

        $count = $result->rowCount() + $offset;
        $this->getProgress()->setCount($count);

        $localPath = Shopware()->Container()->getParameter('shopware.app.rootdir') . 'files/downloads';

        $remotePath = rtrim($this->Request()->basepath, '/') . '/out/media/';

        $numberSnippet = $this->getNameSpace()->get(
            'numberNotValid',
            "The product number %s is not valid. A valid product number must:<br>
            * not be longer than 40 chars<br>
            * not contain other chars than: 'a-zA-Z0-9-_.'<br>
            <br>
            You can force the migration to continue. But be aware that this will: <br>
            * Truncate ordernumbers longer than 40 chars and therefore result in 'duplicate keys' exceptions <br>
            * Will not allow you to modify and save articles having an invalid ordernumber <br>
            "
        );

        while ($media = $result->fetch()) {
            $orderNumber = $media['number'];
            $description = $media['description'];
            $path = $media['url'];

            // Clear-Path
            $path = basename($path);
            $path = str_replace(' ', '%20', $path);

            $documentUrl = $remotePath . $path;
            $document = file_get_contents($documentUrl);

            // Check the ordernumber
            if (!isset($orderNumber)) {
                $orderNumber = '';
            }
            if ($numberValidationMode !== 'ignore'
                && (empty($orderNumber) || strlen($orderNumber) > 30 || strlen($orderNumber) < 4
                            || preg_match('/[^a-zA-Z0-9-_.]/', $orderNumber))
            ) {
                switch ($numberValidationMode) {
                    case 'complain':
                        return $this->getProgress()->error(sprintf($numberSnippet, $orderNumber));
                        break;
                    case 'make_valid':
                        $orderNumber = $this->makeInvalidNumberValid($orderNumber, $media['productID']);
                        break;
                }
            }

            if ($document === '') {
                continue;
            }
            file_put_contents($localPath . str_replace('%20', ' ', $path), $document);

            // Write entry to database
            $sql = 'SELECT articleID
                    FROM s_articles_details
                    WHERE ordernumber = ?';
            $getShopwareArticleId = Shopware()->Db()->fetchOne($sql, [$orderNumber]);
            if (empty($getShopwareArticleId)) {
                //No article
                continue;
            }

            $this->increaseProgress();
            if ($this->newRequestNeeded()) {
                return $this->getProgress();
            }

            // Add entry to s_articles_downloads
            Shopware()->Db()->query(
                'INSERT INTO s_articles_downloads (articleID, description, filename, size) VALUES (?,?,?,?)',
                [$getShopwareArticleId, $description, '/files/downloads/' . $path, filesize($localPath . $path)]
            );
        }

        return $this->getProgress()->done();
    }
}
