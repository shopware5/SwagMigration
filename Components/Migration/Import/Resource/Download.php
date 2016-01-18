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

/**
 * Shopware SwagMigration Components - Download
 *
 * Downloads import adapter
 *
 * @category  Shopware
 * @package Shopware\Plugins\SwagMigration\Components\Migration\Import\Resource
 * @copyright Copyright (c), shopware AG (http://www.shopware.de)
 */
class Shopware_Components_Migration_Import_Resource_Download extends Shopware_Components_Migration_Import_Resource_Abstract
{
    public function getDefaultErrorMessage()
    {
        return $this->getNameSpace()->get('errorImportingMedia', "An error occurred while importing media");
    }

    public function getCurrentProgressMessage($progress)
    {
        return sprintf(
            $this->getNameSpace()->get('progressDownload', "%s out of %s downloads imported"),
            $this->getProgress()->getOffset(),
            $this->getProgress()->getCount()
        );
    }

    public function getDoneMessage()
    {
        return $this->getNameSpace()->get('importedDownload', "Downloads successfully imported!");
    }

    /**
     * run() method of the import adapter for downloads (article attached)
     *
     * @return $this|\Shopware_Components_Migration_Import_Progress
     */
    public function run()
    {
        $offset = $this->getProgress()->getOffset();
        $numberValidationMode = $this->Request()->getParam('number_validation_mode', 'complain');

        /** @var Zend_Db_Statement_Interface $result */
        $result = $this->Source()->queryArticleDownload();
        $count = $result->rowCount() + $offset;
        $this->getProgress()->setCount($count);

        $localPath = Shopware()->DocPath('files/downloads');
        $remotePath = rtrim($this->Request()->basepath, '/') . '/out/media/';

        $numberSnippet = $this->getNameSpace()->get(
            'numberNotValid',
            "The product number %s is not valid. A valid product number must:<br>
            * not be longer than 40 chars<br>
            * not contain other chars than: 'a-zA-Z0-9-_.' and SPACE<br>
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
            $path = str_replace(" ", "%20", $path);

            $documentUrl = $remotePath . $path;
            $document = file_get_contents($documentUrl);

            // Check the ordernumber
            if (!isset($orderNumber)) {
                $orderNumber = '';
            }
            if ($numberValidationMode !== 'ignore'
                && (empty($orderNumber) || strlen($orderNumber) > 30
                || preg_match('/[^a-zA-Z0-9-_. ]/', $orderNumber))
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

            if (strlen($document) == 0) {
                continue;
            }
            file_put_contents($localPath . str_replace("%20", " ", $path), $document);

            // Write entry to database
            $sql = "SELECT articleID
                    FROM s_articles_details
                    WHERE ordernumber = ?";
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
                "INSERT INTO s_articles_downloads (articleID, description, filename, size) VALUES (?,?,?,?)",
                [$getShopwareArticleId, $description, "/files/downloads/" . $path, filesize($localPath . $path)]
            );
        }

        return $this->getProgress()->done();
    }
}
