<?php

namespace Codeception\Module;

use Nette\Utils\Validators;

class Nette extends \Codeception\Util\Framework
{

	/** @var \Nette\DI\Container */
	protected $container;

	/**
	 * @var array $config
	 */
	public function __construct($config = array())
	{
		$this->requiredFields = array('tempDir');
		$this->config = array(
			'configFiles' => array(),
			'robotLoader' => array(),
		);
		parent::__construct($config);
	}

	protected function validateConfig()
	{
		parent::validateConfig();
		Validators::assertField($this->config, 'tempDir', 'string');
		Validators::assertField($this->config, 'configFiles', 'array');
		Validators::assertField($this->config, 'robotLoader', 'array');
	}

	public function _beforeSuite($settings = array())
	{
		parent::_beforeSuite($settings);

		self::purge($this->config['tempDir']);
		$configurator = new \Nette\Config\Configurator();
		$configurator->addParameters(array(
			'container' => array(
				'class' => ucfirst($this->detectSuiteName($settings)) . 'SuiteContainer',
			),
		));
		$configurator->setTempDirectory($this->config['tempDir']);
		$files = $this->config['configFiles'];
		$files[] = __DIR__ . '/config.neon';
		foreach ($files as $file) {
			$configurator->addConfig($file);
		}
		$loader = $configurator->createRobotLoader();
		foreach ($this->config['robotLoader'] as $dir) {
			$loader->addDirectory($dir);
		}
		$loader->register();
		$this->container = $configurator->createContainer();
	}

	/**
	 * @param string $service
	 * @return object
	 */
	public function grabService($service)
	{
		try {
			return $this->container->getByType($service);
		} catch (\Nette\DI\MissingServiceException $e) {
			$this->fail($e->getMessage());
		}
	}

	private function detectSuiteName($settings)
	{
		if (!isset($settings['path'])) {
			throw new \Nette\InvalidStateException('Could not detect suite name, path is not set.');
		}
		$directory = rtrim($settings['path'], DIRECTORY_SEPARATOR);
		$position = strrpos($directory, DIRECTORY_SEPARATOR);
		if ($position === FALSE) {
			throw new \Nette\InvalidStateException('Could not detect suite name, path is invalid.');
		}
		return substr($directory, $position + 1);
	}

	/**
	 * Purges directory.
	 * @param string $dir
	 */
	protected static function purge($dir)
	{
		if (!is_dir($dir)) {
			mkdir($dir);
		}
		foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir), \RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
			if (substr($entry->getBasename(), 0, 1) === '.') {
				// nothing
			} elseif ($entry->isDir()) {
				rmdir($entry);
			} else {
				unlink($entry);
			}
		}
	}

}
