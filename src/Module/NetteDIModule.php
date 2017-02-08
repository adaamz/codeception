<?php

declare(strict_types=1);

namespace Arachne\Codeception\Module;

use Codeception\Module;
use Codeception\TestInterface;
use Nette\Caching\Storages\IJournal;
use Nette\Caching\Storages\SQLiteJournal;
use Nette\Configurator;
use Nette\DI\Container;
use Nette\DI\MissingServiceException;
use Nette\Http\Session;
use Nette\Utils\FileSystem;
use ReflectionProperty;

class NetteDIModule extends Module
{
    /**
     * @var callable[]
     */
    public $onCreateContainer = [];

    protected $config = [
        'configFiles' => [],
        'appDir' => null,
        'logDir' => null,
        'wwwDir' => null,
        'debugMode' => null,
        'removeDefaultExtensions' => false,
        'newContainerForEachTest' => false,
    ];

    protected $requiredFields = [
        'tempDir',
    ];

    /**
     * @var string
     */
    private $path;

    /**
     * @var array|null
     */
    private $configFiles;

    /**
     * @var Container|null
     */
    private $container;

    public function _beforeSuite($settings = [])
    {
        $this->path = $settings['path'];
        $this->clearTempDir();
    }

    public function _before(TestInterface $test)
    {
        if ($this->config['newContainerForEachTest']) {
            $this->clearTempDir();
            $this->container = null;
            $this->configFiles = null;
        }
    }

    public function _afterSuite()
    {
        $this->stopContainer();
    }

    public function _after(TestInterface $test)
    {
        if ($this->config['newContainerForEachTest']) {
            $this->stopContainer();
        }
    }

    public function useConfigFiles(array $configFiles)
    {
        if (!$this->config['newContainerForEachTest']) {
            $this->fail('The useConfigFiles can only be used if the newContainerForEachTest option is set to true.');
        }
        if ($this->container) {
            $this->fail('Can\'t set configFiles after the container is created.');
        }
        $this->configFiles = $configFiles;
    }

    /**
     * @return Container
     */
    public function getContainer()
    {
        if (!$this->container) {
            $this->createContainer();
        }

        return $this->container;
    }

    /**
     * @param string $service
     *
     * @return object
     */
    public function grabService($service)
    {
        try {
            return $this->getContainer()->getByType($service);
        } catch (MissingServiceException $e) {
            $this->fail($e->getMessage());
        }
    }

    private function createContainer()
    {
        $configurator = new Configurator();
        if ($this->config['removeDefaultExtensions']) {
            $configurator->defaultExtensions = [
                'extensions' => 'Nette\DI\Extensions\ExtensionsExtension',
            ];
        }

        if ($this->config['logDir']) {
            $logDir = $this->path.'/'.$this->config['logDir'];
            FileSystem::createDir($logDir);
            $configurator->enableDebugger($logDir);
        }

        $configurator->addParameters([
            'appDir' => $this->path.($this->config['appDir'] ? '/'.$this->config['appDir'] : ''),
            'wwwDir' => $this->path.($this->config['wwwDir'] ? '/'.$this->config['wwwDir'] : ''),
        ]);

        $tempDir = $this->path.'/'.$this->config['tempDir'];
        FileSystem::delete($tempDir);
        FileSystem::createDir($tempDir);
        $configurator->setTempDirectory($tempDir);

        if ($this->config['debugMode'] !== null) {
            $configurator->setDebugMode($this->config['debugMode']);
        }

        $configFiles = is_array($this->configFiles) ? $this->configFiles : $this->config['configFiles'];
        foreach ($configFiles as $file) {
            $configurator->addConfig($this->path.'/'.$file);
        }

        $this->container = $configurator->createContainer();

        foreach ($this->onCreateContainer as $callback) {
            $callback($this->container);
        }
    }

    private function clearTempDir(): void
    {
        $tempDir = $this->path.'/'.$this->config['tempDir'];
        if (is_dir($tempDir)) {
            FileSystem::delete(realpath($tempDir));
        }
        FileSystem::createDir($tempDir);
    }

    private function stopContainer(): void
    {
        if (!$this->container) {
            return;
        }

        try {
            $this->container->getByType(Session::class)->close();
        } catch (MissingServiceException $e) {
        }

        try {
            $journal = $this->container->getByType(IJournal::class);
            if ($journal instanceof SQLiteJournal) {
                $property = new ReflectionProperty(SQLiteJournal::class, 'pdo');
                $property->setAccessible(true);
                $property->setValue($journal, null);
            }
        } catch (MissingServiceException $e) {
        }

        FileSystem::delete(realpath($this->container->getParameters()['tempDir']));
    }
}
