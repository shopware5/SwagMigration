<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagMigration\Components\Migration\Import\Resource;

use Shopware\SwagMigration\Components\DbServices\Import\Import;
use Shopware\SwagMigration\Components\Migration;
use Shopware\SwagMigration\Components\Migration\Import\Progress;

/**
 * Shopware SwagMigration Components - Image
 *
 * Image import adapter
 */
class Image extends AbstractResource
{
    /**
     * {@inheritdoc}
     */
    public function getDefaultErrorMessage()
    {
        return $this->getNameSpace()->get('errorImportingImages', 'An error occurred while importing images');
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentProgressMessage(Progress $progress)
    {
        return \sprintf(
            $this->getNameSpace()->get('progressImages', '%s out of %s images imported'),
            $this->getProgress()->getOffset(),
            $this->getProgress()->getCount()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getDoneMessage()
    {
        return $this->getNameSpace()->get('importedImages', 'Images successfully imported!');
    }

    /**
     * {@inheritdoc}
     */
    public function run()
    {
        $call = \array_merge($this->Request()->getPost(), $this->Request()->getQuery());
        $offset = $this->getProgress()->getOffset();

        $result = $this->Source()->queryProductImages($offset);

        if (empty($result)) {
            return $this->getProgress()->done();
        }

        $count = $result->rowCount() + $offset;
        $this->getProgress()->setCount($count);

        $this->initTaskTimer();

        if ($call['profile'] !== 'WooCommerce') {
            $image_path = \rtrim($this->Request()->basepath, '/') . '/' . $this->Source()->getProductImagePath();
        }

        /* @var Import $import */
        $import = Shopware()->Container()->get('swagmigration.import');

        while ($image = $result->fetch()) {
            if ($call['profile'] !== 'WooCommerce') {
                $image['link'] = $image_path . $image['image'];
            } else {
                $image['link'] = $image['image'];
            }

            if (!isset($image['name'])) {
                $image['name'] = \pathinfo(\basename($image['image']), \PATHINFO_FILENAME);
            }
            $image['name'] = $this->removeSpecialCharacters($image['name']);

            $sql = '
                SELECT ad.articleID
                FROM s_plugin_migrations pm
                JOIN s_articles_details ad
                ON ad.id=pm.targetID
                WHERE pm.`sourceID`=?
                AND (`typeID`=? OR `typeID`=?)
            ';
            $image['articleID'] = Shopware()->Db()->fetchOne(
                $sql,
                [
                    $image['productID'],
                    Migration::MAPPING_ARTICLE,
                    Migration::MAPPING_VALID_NUMBER,
                ]
            );

            $sql = '
                SELECT ad.articleID, ad.ordernumber, ad.kind
                FROM s_plugin_migrations pm
                JOIN s_articles_details ad
                ON ad.id=pm.targetID
                WHERE pm.`sourceID`=?
                AND (`typeID`=? OR `typeID`=?)
            ';
            $product_data = Shopware()->Db()->fetchRow(
                $sql,
                [
                    $image['productID'],
                    Migration::MAPPING_ARTICLE,
                    Migration::MAPPING_VALID_NUMBER,
                ]
            );

            if (!empty($product_data)) {
                if ($this->Source()->checkForDuplicateImages()) {
                    if ($this->imageAlreadyImported($product_data['articleID'], $image['link'])) {
                        ++$offset;
                        continue;
                    }
                }

                if (!empty($image['main']) && $product_data['kind'] == 1) {
                    $import->deleteArticleImages($product_data['articleID']);
                }
                $image['articleID'] = $product_data['articleID'];
                if ($product_data['kind'] == 2) {
                    $image['relations'] = $product_data['ordernumber'];
                }
                $image['articleimagesID'] = $import->articleImage($image);
            }

            $this->increaseProgress();
            if ($this->newRequestNeeded()) {
                return $this->getProgress();
            }
        }

        return $this->getProgress()->done();
    }

    /**
     * Helper function which tells, if a given image was already assigned to a given product
     *
     * @param int $articleId
     * @param string $image
     *
     * @return bool
     */
    public function imageAlreadyImported($articleId, $image)
    {
        // Get a proper image name (without path and extension)
        $info = \pathinfo($image);
        $extension = $info['extension'];
        $name = \basename($image, '.' . $extension);

        // Find images with the same articleId and image name
        $sql = '
			SELECT COUNT(*)
			FROM `s_articles_img`
			WHERE articleID = ?
			AND img = ?
		';
        $numOfImages = Shopware()->Db()->fetchOne(
            $sql,
            [$articleId, $name]
        );

        if ((int) $numOfImages > 0) {
            return true;
        }

        return false;
    }

    /**
     * Helper function to format image names the way the media resource expects it
     *
     * @param string $name
     *
     * @return string
     */
    private function removeSpecialCharacters($name)
    {
        $name = \iconv('utf-8', 'ascii//translit', $name);
        $name = \strtolower($name);
        $name = \preg_replace('#[^a-z0-9\-_]#', '-', $name);
        $name = \preg_replace('#-{2,}#', '-', $name);
        $name = \trim($name, '-');

        return \mb_substr($name, 0, 180);
    }
}
