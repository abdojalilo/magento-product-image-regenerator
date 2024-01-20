<?php

require __DIR__ . '/app/bootstrap.php';

use Magento\Framework\App\Bootstrap;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Image\AdapterFactory;
use Magento\Framework\Filesystem\Io\File;

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

$areaList = $objectManager->get(\Magento\Framework\App\AreaList::class);
$areaCode = 'frontend';
$areaList->getCodeByFrontName($areaCode);
$state = $objectManager->get(\Magento\Framework\App\State::class);
$state->setAreaCode($areaCode);

/** @var ProductCollectionFactory $productCollectionFactory */
$productCollectionFactory = $objectManager->get(ProductCollectionFactory::class);
$productCollection = $productCollectionFactory->create();
$productCollection->addAttributeToSelect('*');

$adminPanelCachePath = 'pub/media/catalog/product/cache';
$allowedExtensions = ['jpg', 'png', 'jpeg'];

$cacheFilePaths = [];
$cacheFilePathsHash = [];

function scanDirectory($directory, $currentDirectory = '', &$cacheFilePaths)
{
    $files = scandir($directory);

    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            $filePath = $directory . '/' . $file;

            if (is_dir($filePath)) {
                $subdirectory = $currentDirectory . '/' . $file;
                scanDirectory($filePath, $subdirectory, $cacheFilePaths);
            } else {
                $fileExtension = pathinfo($file, PATHINFO_EXTENSION);

                if (in_array($fileExtension, $GLOBALS['allowedExtensions'])) {
                    $cacheFilePaths[] = $currentDirectory . '/' . $file;
                }
            }
        }
    }
}

scanDirectory($adminPanelCachePath, '', $cacheFilePaths);
$cacheFilePaths = array_unique($cacheFilePaths);

/** @var ProductRepositoryInterface $productRepository */
$productRepository = $objectManager->get(ProductRepositoryInterface::class);

/** @var AdapterFactory $imageFactory */
$imageFactory = $objectManager->get(AdapterFactory::class);

/** @var File $fileIo */
$fileIo = $objectManager->get(File::class);
echo BP;
foreach ($productCollection as $product) {
    $product = $productRepository->getById($product->getId());
    $images = $product->getMediaGalleryImages();
    foreach ($cacheFilePaths as $path) {
        $cashPathImages = BP . '/' . $adminPanelCachePath . $path;
        if (file_exists($cashPathImages))
        {
            foreach ($images as $key => $image)
            {
                /* === Path Without Hash === */
                $pathWithoutHash = explode('/', $path);
                $pathWithoutHash = implode('/', array_slice($pathWithoutHash, -3));
                if('/'.$pathWithoutHash === $image->getFile())
                {
                    echo $product->getSku() . ' =====> ' .'/'.$pathWithoutHash . " ====> " . $image->getFile() . "\n";

                    $product = $objectManager->create('Magento\Catalog\Model\Product')->load($product->getId());
                    $productRepository = $objectManager->create('Magento\Catalog\Api\ProductRepositoryInterface');
                    $existingMediaGalleryEntries = $product->getMediaGalleryEntries();

                    foreach ($existingMediaGalleryEntries as $key => $entry)
                    {
                        unset($existingMediaGalleryEntries[$key]);
                    }

                    $product->setMediaGalleryEntries($existingMediaGalleryEntries);
                    $productRepository->save($product);

                    $product->addImageToMediaGallery($cashPathImages, array('image', 'small_image', 'thumbnail'), false, false);
                    $product->save();
                    $product->reindex();
                    if($product->save())
                    {
                        echo "Done \n";
                    }
                }
            }
        }
    }
}

