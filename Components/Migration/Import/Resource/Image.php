<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagMigration\Components\Migration\Import\Resource;

use Shopware\SwagMigration\Components\Migration;
use Shopware\SwagMigration\Components\DbServices\Import\Import;
use Shopware\SwagMigration\Components\Migration\Import\Progress;

/**
 * Shopware SwagMigration Components - Image
 *
 * Image import adapter
 *
 * @category  Shopware
 * @package Shopware\Plugins\SwagMigration\Components\Migration\Import\Resource
 * @copyright Copyright (c) 2012, shopware AG (http://www.shopware.de)
 */
class Image extends AbstractResource
{
    /**
     * Returns the default error message for this import class
     *
     * @return mixed
     */
    public function getDefaultErrorMessage()
    {
        return $this->getNameSpace()->get('errorImportingImages', "An error occurred while importing images");
    }

    /**
     * Returns the progress message for the current import step. A Progress-Object will be passed, so
     * you can get some context info for your snippet
     *
     * @param Progress $progress
     * @return string
     */
    public function getCurrentProgressMessage($progress)
    {
        return sprintf(
            $this->getNameSpace()->get('progressImages', "%s out of %s images imported"),
            $this->getProgress()->getOffset(),
            $this->getProgress()->getCount()
        );
    }

    /**
     * Returns the default 'all done' message
     *
     * @return mixed
     */
    public function getDoneMessage()
    {
        return $this->getNameSpace()->get('importedImages', "Images successfully imported!");
    }

    /**
     * Main run method of each import adapter. The run method will query the source profile, iterate
     * the results and prepare the data for import via the old Shopware API.
     *
     * If you want to import multiple entities with one import-class, you might want to check for
     * $this->getInternalName() in order to distinct which (sub)entity you where called for.
     *
     * The run method may only return instances of Progress
     * The calling instance will use those progress object to communicate with the ExtJS backend.
     * If you want this to work properly, think of calling:
     * - $this->initTaskTimer() at the beginning of your run method
     * - $this->getProgress()->setCount(222) to set the total number of data
     * - $this->increaseProgress() to increase the offset/progress
     * - $this->getProgress()->getOffset() to get the current progress' offset
     * - return $this->getProgress()->error("Message") in order to stop with an error message
     * - return $this->getProgress() in order to be called again with the current offset
     * - return $this->getProgress()->done() in order to mark the import as finished
     *
     *
     * @return Progress
     */
    public function run()
    {
        $offset = $this->getProgress()->getOffset();

        $result = $this->Source()->queryProductImages($offset);
        $count = $result->rowCount() + $offset;
        $this->getProgress()->setCount($count);

        $this->initTaskTimer();
        $image_path = rtrim($this->Request()->basepath, '/') . '/' . $this->Source()->getProductImagePath();

        /* @var Import $import */
        $import = Shopware()->Container()->get('swagmigration.import');

        while ($image = $result->fetch()) {
            $image['link'] = $image_path . $image['image'];

            if (!isset($image['name'])) {
                $image['name'] = pathinfo(basename($image['image']), PATHINFO_FILENAME);
            }
            $image['name'] = $this->removeSpecialCharacters($image['name']);

            $sql = '
                SELECT ad.articleID
                FROM s_plugin_migrations pm
                JOIN s_articles_details ad
                ON ad.id=pm.targetID
                WHERE pm.`sourceID`=?
                AND `typeID`=?
            ';
            $image['articleID'] = Shopware()->Db()->fetchOne($sql, [$image['productID'], Migration::MAPPING_ARTICLE]);

            $sql = '
                SELECT ad.articleID, ad.ordernumber, ad.kind
                FROM s_plugin_migrations pm
                JOIN s_articles_details ad
                ON ad.id=pm.targetID
                WHERE pm.`sourceID`=?
                AND `typeID`=?
            ';
            $product_data = Shopware()->Db()->fetchRow($sql, [$image['productID'], Migration::MAPPING_ARTICLE]);

            if (!empty($product_data)) {
                if ($this->Source()->checkForDuplicateImages()) {
                    if ($this->imageAlreadyImported($product_data['articleID'], $image['link'])) {
                        $offset++;
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
     * Helper function to format image names the way the media resource expects it
     *
     * @param $name
     * @return string
     */
    private function removeSpecialCharacters($name)
    {
        $name = iconv('utf-8', 'ascii//translit', $name);
        $name = strtolower($name);
        $name = preg_replace('#[^a-z0-9\-_]#', '-', $name);
        $name = preg_replace('#-{2,}#', '-', $name);
        $name = trim($name, '-');

        return mb_substr($name, 0, 180);
    }

    /**
     * Helper function which tells, if a given image was already assigned to a given product
     *
     * @param $articleId
     * @param $image
     * @return boolean
     */
    public function imageAlreadyImported($articleId, $image)
    {
        // Get a proper image name (without path and extension)
        $info = pathinfo($image);
        $extension = $info['extension'];
        $name = basename($image, '.' . $extension);

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
}
