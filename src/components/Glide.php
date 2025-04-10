<?php

declare(strict_types=1);

namespace WolfpackIT\glide\components;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use League\Flysystem\Filesystem;
use League\Glide\Api\Api;
use League\Glide\Manipulators\Background;
use League\Glide\Manipulators\Blur;
use League\Glide\Manipulators\Border;
use League\Glide\Manipulators\Brightness;
use League\Glide\Manipulators\Contrast;
use League\Glide\Manipulators\Crop;
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
use yii\di\Instance;
use yii\helpers\Url;

class Glide extends Component
{
    public array|string $baseUrl = '';
    public Filesystem|array|string $cache;
    public string $cachePathPrefix = '';
    public array $defaults = [];
    public bool $groupCacheInFolders = true;
    public ImageManager $imageManager;
    /**
     * @var ManipulatorInterface[]
     */
    public array $manipulators;
    public int|null $maxImageSize = null;
    public array $presets = [];
    public ResponseFactoryInterface|array|string $responseFactory = ResponseFactoryInterface::class;
    public Filesystem|array|string $source;
    public string $sourcePathPrefix = '';
    public Filesystem|array|string $watermarks;
    public string $watermarksPathPrefix = '';

    private Api $_api;
    private Server $_server;
    private ImageManager $_imageManager;

    public function createUrl(array|string $path, bool $scheme = false): string
    {
        $url = is_string($this->baseUrl) ? [$this->baseUrl] : $this->baseUrl;
        $url['path'] = $path;
        return Url::to($url, $scheme);
    }

    public function init()
    {
        $this->cache = Instance::ensure($this->cache, Filesystem::class);
        $this->source = Instance::ensure($this->source, Filesystem::class);
        $this->watermarks = Instance::ensure($this->watermarks, Filesystem::class);
        $this->responseFactory = Instance::ensure($this->responseFactory, ResponseFactoryInterface::class);

        $this->initManipulators();

        if (YII_ENV_PROD && !isset($this->maxImageSize)) {
            \Yii::warning('It is higly recommended to set max image size on production.', 'glide');
        }

        parent::init();
    }

    protected function initManipulators(): void
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
            ]);
    }

    public function getApi(): Api
    {
        if (!isset($this->_api)) {
            $this->_api = new Api(
                $this->getImageManager(),
                $this->manipulators
            );
        }

        return $this->_api;
    }

    public function getImageManager(): ImageManager
    {
        if (!isset($this->_imageManager)) {
            $this->_imageManager =
                $this->imageManager
                ?? new ImageManager((extension_loaded('imagick') ? new ImagickDriver() : new GdDriver()));
        }

        return $this->_imageManager;
    }

    public function getServer(): Server
    {
        if (!isset($this->_server)) {
            $this->_server = new Server(
                $this->source,
                $this->cache,
                $this->getApi()
            );

            $this->_server->setSourcePathPrefix($this->sourcePathPrefix);
            $this->_server->setCachePathPrefix($this->cachePathPrefix);
            $this->_server->setGroupCacheInFolders($this->groupCacheInFolders);
            $this->_server->setDefaults($this->defaults);
            $this->_server->setPresets($this->presets);
            $this->_server->setBaseUrl(Url::to($this->baseUrl));
            $this->_server->setResponseFactory($this->responseFactory);
        }

        return $this->_server;
    }

    public function makeImage(string $path, array $params = []): string
    {
        return $this->getServer()->makeImage($path, $params);
    }

    public function outputImage(string $path, array $params = []): void
    {
        $this->getServer()->outputImage($path, $params);
    }
}
