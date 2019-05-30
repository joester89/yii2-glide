<?php

namespace WolfpackIT\glide\components;

use creocoder\flysystem\Filesystem;
use Intervention\Image\ImageManager;
use League\Flysystem\FilesystemInterface;
use League\Glide\Api\Api;
use League\Glide\Manipulators\Background;
use League\Glide\Manipulators\Blur;
use League\Glide\Manipulators\Border;
use League\Glide\Manipulators\Brightness;
use League\Glide\Manipulators\Contrast;
use League\Glide\Manipulators\Crop;
use League\Glide\Manipulators\Encode;
use League\Glide\Manipulators\Filter;
use League\Glide\Manipulators\Flip;
use League\Glide\Manipulators\Gamma;
use League\Glide\Manipulators\ManipulatorInterface;
use League\Glide\Manipulators\Orientation;
use League\Glide\Manipulators\Pixelate;
use League\Glide\Manipulators\Sharpen;
use League\Glide\Manipulators\Size;
use League\Glide\Manipulators\Watermark;
use League\Glide\Responses\ResponseFactoryInterface;
use League\Glide\Server;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;

/**
 * Class Glide
 * @package WolfpackIT\glide\components
 */
class Glide extends Component
{
    /**
     * @var string
     */
    public $baseUrl;

    /**
     * Cache filesystem
     *
     * @var array|string|Filesystem
     */
    public $cache;

    /**
     * @var string
     */
    public $cachePathPrefix;

    /**
     * @var array
     */
    public $defaults = [];

    /**
     * @var bool
     */
    public $groupCacheInFolders = true;

    /**
     * Image manager, must be imagick or gd
     * Default will first try imagick and otherwise use gd
     *
     * @var string
     */
    public $imageManager;

    /**
     * @var ManipulatorInterface[]
     */
    public $manipulators;

    /**
     * @var int
     */
    public $maxImageSize;

    /**
     * @var array
     */
    public $presets = [];

    /**
     * @var array|string|ResponseFactoryInterface
     */
    public $responseFactory;

    /**
     * Source filesystem
     *
     * @var array|string|Filesystem
     */
    public $source;

    /**
     * @var string
     */
    public $sourcePathPrefix;

    /**
     * Source filesystem
     *
     * @var array|string|Filesystem
     */
    public $watermarks;

    /**
     * @var string
     */
    public $watermarksPathPrefix;

    /**
     * @var Api
     */
    protected $_api;

    /**
     * @var Server
     */
    protected $_server;

    /**
     * @var ImageManager
     */
    protected $_imageManager;

    public function init()
    {
        $this->cache = is_string($this->cache) && \Yii::$app->has($this->cache) ? \Yii::$app->get($this->cache) : \Yii::createObject($this->cache);
        $this->source = is_string($this->source) && \Yii::$app->has($this->source) ? \Yii::$app->get($this->source) : \Yii::createObject($this->source);
        $this->watermarks = $this->watermarks ? \Yii::createObject($this->watermarks) : null;
        
        if (!$this->cache || !$this->cache instanceof Filesystem) {
            throw new InvalidConfigException('Cache must be set and be instance of ' . Filesystem::class);
        }

        if (!$this->source || !$this->source instanceof Filesystem) {
            throw new InvalidConfigException('Source must be set and be instance of ' . Filesystem::class);
        }

        if ($this->watermarks && !$this->watermarks instanceof Filesystem) {
            throw new InvalidConfigException('Source must be instance of ' . Filesystem::class);
        }

        $allowedImageManagerValues = ['imagic', 'gd'];

        if ($this->imageManager && !ArrayHelper::isIn($this->imageManager, $allowedValues)) {
            throw new InvalidConfigException('ImageManager must be one of: ' . implode(', ', $allowedValues));
        }

        if (is_string($this->responseFactory) || is_array($this->responseFactory)) {
            $this->responseFactory = \Yii::createObject(ResponseFactoryInterface::class);
        }

        $this->initManipulators();

        parent::init();
    }

    protected function initManipulators()
    {
        $this->manipulators =
            $this->manipulators
            ?? array_filter([
                new Size($this->maxImageSize),
                new Orientation(),
                new Crop(),
                new Brightness(),
                new Contrast(),
                new Gamma(),
                new Sharpen(),
                new Filter(),
                new Flip(),
                new Blur(),
                new Pixelate(),
                new Background(),
                new Border(),
                $this->watermarks ? new Watermark($this->watermarks, $this->watermarksPathPrefix) : null,
                new Encode(),
            ]);
    }

    /**
     * @return Api
     */
    public function getApi(): Api
    {
        if (!$this->_api) {
            $this->_api = new Api(
                $this->getImageManager(),
                $this->manipulators
            );
        }

        return $this->_api;
    }

    /**
     * @return ImageManager
     */
    public function getImageManager(): ImageManager
    {
        if (!$this->_imageManager) {
            $imageManager =
                $this->imageManager
                ?? (extension_loaded('imagick') ? 'imagick' : 'gd');

            $this->_imageManager = new ImageManager(['driver' => $imageManager]);
        }

        return $this->_imageManager;
    }

    /**
     * @return Server
     */
    public function getServer(): Server
    {
        if (!$this->_server) {
            $this->_server = new Server(
                $this->source,
                $this->cache,
                $this->getApi()
            );
            
            $this->_server->setSourcePathPrefix($this->sourcePathPrefix);
            $this->_server->setCache($this->cachePathPrefix);
            $this->_server->setGroupCacheInFolders($this->groupCacheInFolders);
            $this->_server->setDefaults($this->defaults);
            $this->_server->setPresets($this->presets);
            $this->_server->setBaseUrl($this->baseUrl);
            $this->_server->setResponseFactory($this->responseFactory);
        }        

        return $this->_server;
    }
}