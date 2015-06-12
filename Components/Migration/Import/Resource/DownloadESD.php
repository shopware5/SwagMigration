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
 * Downloads import adapter
 *
 * @category  Shopware
 * @package Shopware\Plugins\SwagMigration\Components\Migration\Import\Resource
 * @copyright Copyright (c), shopware AG (http://www.shopware.de)
 */
class Shopware_Components_Migration_Import_Resource_DownloadESD extends Shopware_Components_Migration_Import_Resource_Abstract
{
    public function getDefaultErrorMessage()
    {
        return $this->getNameSpace()->get('errorImportingMedia', "An error occurred while importing media");
    }

    public function getCurrentProgressMessage($progress)
    {
        return sprintf(
            $this->getNameSpace()->get('progressDownload', "%s out of %s ESD downloads imported"),
            $this->getProgress()->getOffset(),
            $this->getProgress()->getCount()
        );
    }

    public function getDoneMessage()
    {
        return $this->getNameSpace()->get('importedDownload', "ESD Downloads successfully imported!");
    }

    /**
     * run() method of the import adapter for ESD Downloads
     *
     * @return $this|Shopware_Components_Migration_Import_Progress
     */
    public function run()
    {
        $offset = $this->getProgress()->getOffset();

        $result = $this->Source()->queryArticleDownloadESD();
        $count = $result->rowCount() + $offset;

        $this->getProgress()->setCount($count);

        $localPath = Shopware()->DocPath('files/' . Shopware()->Config()->get('sESDKEY'));
        $remotePath = $this->Request()->basepath;

        $downloadNotPossibleSnippet = $this->getNameSpace()->get(
            'downloadNotPossible',
            "Download for ESD file %s was not succesful"
        );

        while ($esdFile = $result->fetch()) {
            $orderNumber = $esdFile['number'];
            $filename = $esdFile['filename'];
            $path = $esdFile['path'] . $esdFile['downloadId'];
            $datum = $esdFile['datum'];
            $documentUrl = $remotePath . $path;

            // execute the actual download and check for success
            $document = file_get_contents($documentUrl);

            if ($this->get_http_response_code($documentUrl) != 200 // check http response code and only continue if document can be accessed
                || strlen($document) == 0
                || !isset($orderNumber) // We have to check for an existing article number. Otherwise the file can't be associated with existing shopware's ESD article
            ) {
                return $this->getProgress()->error(sprintf($downloadNotPossibleSnippet, $filename));
                continue;
            }

            // write file to disk
            file_put_contents($localPath . $filename, $document);

            // get article_detail information
            list($articleDetailsId, $articleId) = Shopware()->Db()->fetchRow(
                "SELECT id, articleID FROM s_articles_details WHERE ordernumber = ?",
                array($orderNumber),
                ZEND_Db::FETCH_NUM
            );

            // if no articleId was found, skip this article
            if (empty($articleId)) {
                continue;
            }

            $this->increaseProgress();
            if ($this->newRequestNeeded()) {
                return $this->getProgress();
            }

            // check if we already have the current esd file associated: if the query for s_articles_esd.id is successful then skip
            $existingId = Shopware()->Db()->fetchOne(
                "SELECT id FROM s_articles_esd WHERE articleID = ? AND file = ?",
                array($articleId, $filename)
            );

            if ($existingId) {
                continue;
            }

            // Add actual download to s_articles_esd
            Shopware()->Db()->query(
                "INSERT INTO s_articles_esd (articleID, articledetailsID, file, datum) VALUES (?,?,?,?)",
                array($articleId, $articleDetailsId, $filename, $datum)
            );
        }

        return $this->getProgress()->done();
    }

    /**
     * Return the response code for a given url
     *
     * @param $url
     * @return string
     */
    private function get_http_response_code($url)
    {
        $headers = get_headers($url);

        return substr($headers[0], 9, 3);
    }
}
