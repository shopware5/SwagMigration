<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagMigration\Components\Migration\Import\Resource;

use Shopware\SwagMigration\Components\Migration\Import\Progress;

class DownloadESD extends AbstractResource
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
            $this->getNameSpace()->get('progressDownload', '%s out of %s ESD downloads imported'),
            $this->getProgress()->getOffset(),
            $this->getProgress()->getCount()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getDoneMessage()
    {
        return $this->getNameSpace()->get('importedDownload', 'ESD Downloads successfully imported!');
    }

    /**
     * import adapter for ESD Downloads
     *
     * {@inheritdoc}
     */
    public function run()
    {
        $offset = $this->getProgress()->getOffset();

        $result = $this->Source()->queryArticleDownloadESD();

        if (empty($result)) {
            return $this->getProgress()->done();
        }

        $count = $result->rowCount() + $offset;

        $this->getProgress()->setCount($count);

        $basePath = Shopware()->Container()->getParameter('shopware.app.rootDir');
        $pathSuffix = Shopware()->Container()->get('config')->get('sESDKEY');

        $localPath = $basePath . 'files/' . $pathSuffix;
        $remotePath = $this->Request()->basepath;

        $downloadNotPossibleSnippet = $this->getNameSpace()->get(
            'downloadNotPossible',
            'Download for ESD file %s was not succesful'
        );

        while ($esdFile = $result->fetch()) {
            $orderNumber = $esdFile['number'];
            $filename = $esdFile['filename'];
            $path = $esdFile['path'] . $esdFile['downloadId'];
            $datum = $esdFile['datum'];
            $documentUrl = $remotePath . $path;

            // execute the actual download and check for success
            $document = \file_get_contents($documentUrl);

            if ($this->get_http_response_code($documentUrl) != 200 // check http response code and only continue if document can be accessed
                || $document === ''
                || !isset($orderNumber) // We have to check for an existing article number. Otherwise the file can't be associated with existing shopware's ESD article
            ) {
                return $this->getProgress()->error(\sprintf($downloadNotPossibleSnippet, $filename));
                continue;
            }

            // write file to disk
            \file_put_contents($localPath . $filename, $document);

            // get article_detail information
            list($articleDetailsId, $articleId) = Shopware()->Db()->fetchRow(
                'SELECT id, articleID FROM s_articles_details WHERE ordernumber = ?',
                [$orderNumber],
                \ZEND_Db::FETCH_NUM
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
                'SELECT id FROM s_articles_esd WHERE articleID = ? AND file = ?',
                [$articleId, $filename]
            );

            if ($existingId) {
                continue;
            }

            // Add actual download to s_articles_esd
            Shopware()->Db()->query(
                'INSERT INTO s_articles_esd (articleID, articledetailsID, file, datum) VALUES (?,?,?,?)',
                [$articleId, $articleDetailsId, $filename, $datum]
            );
        }

        return $this->getProgress()->done();
    }

    /**
     * Return the response code for a given url
     *
     * @param string $url
     *
     * @return string
     */
    private function get_http_response_code($url)
    {
        $headers = \get_headers($url);

        return \substr($headers[0], 9, 3);
    }
}
