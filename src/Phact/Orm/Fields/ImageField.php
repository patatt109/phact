<?php
/**
 * Created by PhpStorm.
 * User: aleksandrgordeev
 * Date: 09.08.16
 * Time: 12:55
 */

namespace Phact\Orm\Fields;

use Exception;
use Imagine\Image\AbstractImagine;
use Imagine\Image\Box;
use Imagine\Image\BoxInterface;
use Imagine\Image\ImageInterface;
use Imagine\Image\ManipulatorInterface;
use Imagine\Image\Metadata\DefaultMetadataReader;
use Phact\Exceptions\InvalidConfigException;
use Phact\Storage\Files\FileInterface;
use Phact\Storage\Files\StorageFile;

class ImageField extends FileField
{
    /**
     * Array with image sizes
     * key 'original' is reserved!
     * example:
     * [
     *      'thumb' => [
     *          300,200,
     *          'method' => 'cover',
     *          'ext' => 'png'
     *      ]
     * ]
     *
     * There are 2 methods: cover and contain
     *
     * @var array
     */
    public $sizes = [];

    /**
     * Force resize images
     * @var bool
     */
    public $force = false;

    /**
     * Imagine default options
     * @var array
     */
    public $options = [
        'resolution-units' => ImageInterface::RESOLUTION_PIXELSPERINCH,
        'resolution-x' => 72,
        'resolution-y' => 72,
        'jpeg_quality' => 100,
        'quality' => 100,
        'png_compression_level' => 0
    ];

    /**
     * @var bool
     */
    public $storeOriginal = true;

    /**
     * Cached original
     * @var null | \Imagine\Image\ImagineInterface
     */
    public $_original = null;

    /**
     * Cached original name
     * @var null | string
     */
    public $_originalName = null;

    /**
     * Recreate file if missing
     * @var bool
     */
    public $checkMissing = false;

    /**
     * @var bool.
     * Set true if AbstractImagine use defaultMetadataReader
     */
    public $useDefaultMetadataReader = true;


    /**
     * @var AbstractImagine instance
     */
    protected $_imagine;


    const RESIZE_METHOD_PREFIX = 'size';


    public function __get($name)
    {
        if (strpos($name, 'url_') === 0) {
            return $this->sizeUrl(str_replace('url_', '', $name));
        } else {
            return parent::__smartGet($name);
        }
    }

    public function sizeCleanPath($prefix)
    {
        /** @var StorageFile $storageFile */
        $storageFile = $this->getAttribute();
        list($filename, $extension) = $this->getSizeFilenameAndExt($prefix, $storageFile->getBaseName());
        $newBasename = $prefix . '_' . $filename . ($extension ? '.' . $extension : '');
        $basePath = dirname($storageFile->getPath());
        return $basePath.DIRECTORY_SEPARATOR.$newBasename;
    }

    public function sizeUrl($prefix)
    {
        $path = $this->sizeCleanPath($prefix);
        return $this->getStorage()->getUrl($path);
    }

    public function sizePath($prefix)
    {
        $path = $this->sizeCleanPath($prefix);
        return $this->getStorage()->getPath($path);
    }

    public function setValue($value, $aliasConfig = NULL)
    {
        $createSizes = !(($value instanceof StorageFile) && $value->equalsTo($this->getAttribute()));
        $attribute = parent::setValue($value, $aliasConfig);
        if ($createSizes) {
            $this->createSizes();
        }
        return $attribute;
    }

    public function deleteOld()
    {
        if ($this->deleteOld) {
            parent::deleteOld();
            if (is_a($this->getOldAttribute(), FileInterface::class)) {
                foreach (array_keys($this->sizes) as $prefix) {
                    $this->getStorage()->delete($this->sizeStoragePath($prefix, $this->getOldAttribute()));
                }
            }
        }
    }

    public function createSizes()
    {
        foreach ($this->sizes as $sizeName => $params) {

            if (!$params['method']) {
                continue;
            }

            $imageInstance = $this->getImageInstance();

            $methodName = $params['method'];
            $methodName = self::RESIZE_METHOD_PREFIX . ucfirst($methodName);

            if ($imageInstance && method_exists($this, $methodName)) {

                $box = $this->getSizeBox($imageInstance, $params);

                if (!$imageInstance->getSize()->contains($box)) {
                    $source = $imageInstance;
                } else {
                    /** @var ImageInterface $source */
                    $source = $this->{$methodName}($box, $imageInstance);
                }

                $this->saveSize($sizeName, $source);
            }
        }
    }

    /**
     * @param BoxInterface $box
     * @param ImageInterface $imageInstance
     * @return ImageInterface|static
     */
    public function sizeCover(BoxInterface $box, $imageInstance)
    {
        return $imageInstance->thumbnail($box, ManipulatorInterface::THUMBNAIL_OUTBOUND);
    }

    /**
     * @param BoxInterface $box
     * @param ImageInterface $imageInstance
     * @return ImageInterface|static
     */
    public function sizeContain(BoxInterface $box, $imageInstance)
    {
        return $imageInstance->thumbnail($box, ManipulatorInterface::THUMBNAIL_INSET);
    }

    /**
     * @param $sizeName
     * @param $source ImageInterface
     */
    public function saveSize($sizeName, $source)
    {
        /** @var StorageFile $storageFile */
        $storageFile = $this->attribute;
        list($filename, $extension) = $this->getSizeFilenameAndExt($sizeName, $storageFile->getBaseName());
        $options = isset($params['options']) ? $params['options'] : $this->options;
        $this->getStorage()->save($this->sizeStoragePath($sizeName, $storageFile), $source->get($extension, $options));
    }

    /**
     * @param $sizeName
     * @return string path storage
     */
    public function sizeStoragePath($sizeName, StorageFile $storageFile)
    {
        $directory = pathinfo($storageFile->path, PATHINFO_DIRNAME);

        list($filename, $ext) = $this->getSizeFilenameAndExt($sizeName, $storageFile->getBaseName());
        $newBasename = $filename . ($ext ? '.' . $ext : '');
        $sizeFileName = $this->preparePrefixSize($sizeName) . $newBasename;
        return $directory . DIRECTORY_SEPARATOR . $sizeFileName;
    }

    protected function getSizeFilenameAndExt($sizeName, $basename)
    {
        $options = $this->sizes[$sizeName] ?? [];
        list($filename, $ext) = $this->getFilenameAndExt($basename);
        if (isset($options['ext'])) {
            $ext = $options['ext'];
        }
        return [$filename, $ext];
    }

    protected function getFilenameAndExt($basename)
    {
        $ext = '';
        $filename = $basename;
        $lastDot = mb_strrpos($basename, '.', 0, 'UTF-8');
        if ($lastDot !== false) {
            $ext = mb_substr($basename, $lastDot + 1, null, 'UTF-8');
            $filename = mb_substr($basename, 0, $lastDot, 'UTF-8');
        }
        return [$filename, $ext];
    }
    /**
     * @param ImageInterface $image
     * @param $sizeParams
     * @return Box|BoxInterface
     */
    protected function getSizeBox(ImageInterface $image, $sizeParams)
    {
        $width = isset($sizeParams[0]) ? $sizeParams[0] : null;
        $height = isset($sizeParams[1]) ? $sizeParams[1] : null;

        /** if one of size params not passed, scale image proportion */
        if (!$width || !$height) {
            $box = new Box($image->getSize()->getWidth(), $image->getSize()->getHeight());
            if (!$width) {
                $box = $box->heighten($height);
            }
            if (!$height) {
                $box = $box->widen($width);
            }
        } else {
            $box = new Box($width, $height);
        }

        return $box;
    }

    protected function preparePrefixSize($prefix)
    {
        return rtrim($prefix, '_') . '_';
    }

    public function getImagine()
    {
        if ($this->_imagine === null) {
            $this->_imagine = $this->initImagine();
        }
        return $this->_imagine;
    }

    public function getImageInstance()
    {
        $filePath = $this->getPath();
        $instance = null;
        if (is_readable($filePath)) {
            try {
                $instance = $this->getImagine()->open($filePath);
            } catch (Exception $e) {
            }
        }
        return $instance;
    }


    public function initImagine()
    {
        $imagine = null;

        if (class_exists('Gmagick', false)) {
            $imagine = new \Imagine\Gmagick\Imagine();
        }
        if (class_exists('Imagick', false)) {
            $imagine = new \Imagine\Imagick\Imagine();
        }
        if (function_exists('gd_info')) {
            $imagine = new \Imagine\Gd\Imagine();
        }

        if ($imagine && $this->useDefaultMetadataReader) {
            $imagine->setMetadataReader(new DefaultMetadataReader());
        }

        if ($imagine) {
            return $imagine;
        }

        throw new InvalidConfigException('Libs: Gmagick, Imagick or Gd not found');
    }

    public function getFormField()
    {
        return $this->setUpFormField([
            'class' => \Phact\Form\Fields\ImageField::class
        ]);
    }

    public function getDimensions($prefix = null)
    {
        if (is_a($this->attribute, FileInterface::class)) {
            if (!$prefix) {
                $path = $this->getStorage()->getPath($this->attribute->getPath());
            } else {
                $path = $this->sizePath($prefix);
            }
            if ($path && is_file($path)) {
                $size = getimagesize($path);
                if ($size) {
                    return [
                        'width' => $size[0],
                        'height' => $size[1]
                    ];
                }
            }
        }
        return null;
    }
}