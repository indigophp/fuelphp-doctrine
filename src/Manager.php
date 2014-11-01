<?php

/*
 * This file is part of the FuelPHP Doctrine package.
 *
 * (c) Indigo Development Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fuel\Doctrine;

use Indigo\Fuel\Dependency\Container as DiC;
use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\Mapping\Driver\DriverChain;
use Doctrine\Common\EventManager;
use Doctrine\Common\EventSubscriber;
use Gedmo\DoctrineExtensions;

/**
 * Entity Manager Facade
 *
 * @author Márk Sági-Kazár <mark.sagikazar@gmail.com>
 */
class Manager
{
	use \Indigo\Core\Helper\Config;

	/**
	 * Entity Manager
	 *
	 * @var EntityManager
	 */
	protected $entityManager;

	/**
	 * Creates a new Manager
	 *
	 * @param array $config
	 */
	public function __construct(array $config)
	{
		$this->config = $config;

		if ($this->getConfig('mapping.auto', false))
		{
			$this->autoLoadMappingInfo();
		}
	}

	/**
	 * Creates a new Entity Manager
	 *
	 * @return EntityManager
	 */
	protected function createEntityManager()
	{
		// Cache can be null in case of auto setup
		if ($cache = $this->getConfig('cache_driver', 'array'))
		{
			$cache = 'doctrine.cache.'.$cache;

			$cache = DiC::resolve($cache);
		}

		// Auto or manual setup
		if ($this->getConfig('auto_config', false))
		{
			$dev = $this->getConfig('dev_mode', \Fuel::$env === \Fuel::DEVELOPMENT);
			$proxy_dir = $this->getConfig('proxy_dir');

			$config = Setup::createConfiguration($dev, $proxy_dir, $cache);
		}
		else
		{
			$config = new Configuration;

			$config->setProxyDir($this->getConfig('proxy_dir'));
			$config->setProxyNamespace($this->getConfig('proxy_namespace'));
			$config->setAutoGenerateProxyClasses($this->getConfig('auto_generate_proxy_classes', false));

			if ($cache)
			{
				$config->setMetadataCacheImpl($cache);
				$config->setQueryCacheImpl($cache);
				$config->setResultCacheImpl($cache);
			}
		}

		// Ugly hack for autoloading annotations
		$config->newDefaultAnnotationDriver(array());

		$this->registerMapping($config);

		$conn = DiC::multiton('dbal', $this->getConfig('dbal'), [$this->getConfig('dbal')]);
		$evm = $conn->getEventManager();

		$this->registerBehaviors($evm, $config);

		return $this->entityManager = EntityManager::create($conn, $config, $evm);
	}

	/**
	 * Returns the Entity Manager
	 *
	 * @return EntityManager
	 */
	public function getEntityManager()
	{
		if ($this->entityManager === null)
		{
			return $this->createEntityManager();
		}

		return $this->entityManager;
	}

	/**
	 * Returns mapping config
	 *
	 * @return array
	 */
	public function getMappings()
	{
		return $this->getConfig('mappings', array());
	}

	/**
	 * Sets a mapping configuration
	 *
	 * @param string $mappingName
	 * @param array  $mappingConfig
	 *
	 * @return this
	 */
	public function setMappings($mappingName, array $mappingConfig = null)
	{
		if (is_array($mappingName) === false)
		{
			$mappingName = array($mappingName => $mappingConfig);
		}

		\Arr::set($this->config['mappings'], $mappingName);

		return $this;
	}

	/**
	 * Returns default config path
	 *
	 * @return string
	 */
	public function getConfigPath()
	{
		return $this->getConfig('mapping.config_path', 'config'.DS.'doctrine'.DS);
	}

	/**
	 * Returns default class path
	 *
	 * @return string
	 */
	public function getClassPath()
	{
		return $this->getConfig('mapping.class_path', 'classes'.DS);
	}

	/**
	 * Returns default object name
	 *
	 * @return string
	 */
	public function getObjectName()
	{
		return $this->getConfig('mapping.object_name', 'Entity');
	}

	/**
	 * Generates mapping information for packages, modules and the app
	 *
	 * @return array
	 */
	protected function autoLoadMappingInfo()
	{
		$mappings = array();

		foreach (\Package::loaded() as $package => $path)
		{
			$mappings[] = $package . '::package';
		}

		foreach (\Module::loaded() as $module => $path)
		{
			$mappings[] = $module . '::module';
		}

		$mappings[] = 'app';

		$mappings = array_fill_keys($mappings, array('is_component' => true));

		$this->setMappings($mappings);
	}

	/**
	 * Registers mapping in a Configuration
	 *
	 * @param Configuration $config
	 */
	public function registerMapping(Configuration $config)
	{
		$driverChain = new DriverChain;
		$aliasMap = array();
		$drivers = array();

		$this->parseMappingInfo();

		// Get actual drivers
		foreach ($this->getMappings() as $mappingName => $mappingConfig)
		{
			if (empty($mappingConfig['prefix']))
			{
				$mappingConfig['prefix'] = '__DEFAULT__';
			}

			$drivers[$mappingConfig['type']][$mappingConfig['prefix']] = $mappingConfig['dir'];

			if (isset($mappingConfig['alias']))
			{
				$aliasMap[$mappingConfig['alias']] = $mappingConfig['prefix'];
			}
		}

		foreach ($drivers as $driverType => $driverPaths)
		{
			if ($driverType === 'annotation')
			{
				$driver = $config->newDefaultAnnotationDriver($driverPaths, false);
				// Annotations are needed to be registered, thanks Doctrine
				// $driver = new AnnotationDriver(
				// 	new CachedReader(
				// 		new AnnotationReader,
				// 		$config->getMetadataCacheImpl()
				// 	),
				// 	$driverPaths
				// );
			}
			else
			{
				$paths = $driverPaths;

				if (strpos($driverType, 'simplified') === 0)
				{
					$paths = array_flip($driverPaths);
				}

				$driver = DiC::resolve($driverType, [$paths]);
			}

			foreach ($driverPaths as $prefix => $driverPath)
			{
				if ($prefix === '__DEFAULT__' or count($this->config['mappings']) === 1)
				{
					$driverChain->setDefaultDriver($driver);
				}
				else
				{
					$driverChain->addDriver($driver, $prefix);
				}
			}
		}

		$config->setMetadataDriverImpl($driverChain);
		$config->setEntityNamespaces($aliasMap);
	}

	/**
	 * Parses mapping info
	 *
	 * @return array
	 */
	public function parseMappingInfo()
	{
		$mappings = array();

		foreach ($this->getMappings() as $mappingName => $mappingConfig)
		{
			// This is from symfony DoctrineBundle, should be reviewed
			if (is_array($mappingConfig) === false or \Arr::get($mappingConfig, 'mapping', true) === false)
			{
				continue;
			}

			$mappingConfig = array_replace(array(
				'dir'    => false,
				'type'   => false,
				'prefix' => false,
			), $mappingConfig);

			if (isset($mappingConfig['is_component']) === false)
			{
				$mappingConfig['is_component'] = false;

				if (is_dir($mappingConfig['dir']) === false)
				{
					$mappingConfig['is_component'] = (\Package::loaded($mappingName) or \Module::loaded($mappingName));
				}
			}

			if ($mappingConfig['is_component'])
			{
				$mappingConfig = $this->getComponentDefaults($mappingName, $mappingConfig);
			}

			if (empty($mappingConfig))
			{
				continue;
			}

			$mappings[$mappingName] = $mappingConfig;
		}

		$this->config['mappings'] = $mappings;
	}

	/**
	 * Returns default settings for components
	 *
	 * @param string $mappingName
	 * @param array  $mappingConfig
	 *
	 * @return array
	 */
	protected function getComponentDefaults($mappingName, array $mappingConfig)
	{
		if (strpos($mappingName, '::'))
		{
			list($componentName, $componentType) = explode('::', $mappingName);
		}
		else
		{
			$componentName = $mappingName;

			$componentType = $this->detectComponentType($componentName);

			if ($componentType === false and $componentName === 'app')
			{
				$componentType = 'app';
			}
		}

		if (($componentPath = $this->getComponentPath($componentName, $componentType)) === false)
		{
			return false;
		}

		$configPath = $mappingConfig['dir'];

		if ($configPath === false)
		{
			$configPath = $this->getConfigPath();
		}

		if ($mappingConfig['type'] === false)
		{
			$mappingConfig['type'] = $this->detectMetadataDriver($componentPath, $configPath);
		}

		if ($mappingConfig['type'] === false)
		{
			return false;
		}

		if ($mappingConfig['dir'] === false)
		{
			if (in_array($mappingConfig['type'], array('annotation', 'staticphp')))
			{
				$mappingConfig['dir'] = $this->getClassPath().$this->getObjectName();
			}
			else
			{
				$mappingConfig['dir'] = $configPath;
			}
		}

		if (is_array($mappingConfig['dir']))
		{
			foreach ($mappingConfig['dir'] as &$path)
			{
				$path = $componentPath . $path;
			}
		}
		else
		{
			$mappingConfig['dir'] = $componentPath . $mappingConfig['dir'];
		}

		if ($mappingConfig['prefix'] === false)
		{
			$mappingConfig['prefix'] = $this->detectComponentNamespace($componentName, $componentType);
		}

		// Set this to false to prevent reinitialization on subsequent load calls
		$mappingConfig['is_component'] = false;

		return $mappingConfig;
	}

	/**
	 * Detects which metadata driver to use for the supplied directory
	 *
	 * @param string       $dir        A directory path
	 * @param string|array $configPath Config path or paths
	 *
	 * @return string|null A metadata driver short name, if one can be detected
	 */
	protected function detectMetadataDriver($dir, $configPath)
	{
		foreach ((array) $configPath as $cPath)
		{
			$path = $dir.DS.$cPath.DS;

			if (($files = glob($path.'*.dcm.xml')) && count($files))
			{
				return 'xml';
			}
			elseif (($files = glob($path.'*.orm.xml')) && count($files))
			{
				return 'simplified_xml';
			}
			elseif (($files = glob($path.'*.dcm.yml')) && count($files))
			{
				return 'yml';
			}
			elseif (($files = glob($path.'*.orm.yml')) && count($files))
			{
				return 'simplified_yml';
			}
			elseif (($files = glob($path.'*.php')) && count($files))
			{
				return 'php';
			}
		}

		if (is_dir($dir.DS.$this->getClassPath().$this->getObjectName()))
		{
			return 'annotation';
		}

		return false;
	}

	/**
	 * Registers subscribers to Event Manager
	 *
	 * @param EventManager $evm
	 */
	protected function registerBehaviors(EventManager $evm, Configuration $config)
	{
		$reader = new AnnotationReader;

		if ($cache = $config->getMetadataCacheImpl())
		{
			$reader = new CachedReader($reader, $cache);
		}

		foreach ($this->getConfig('behaviors', array()) as $behavior)
		{
			if ($class = DiC::resolve('doctrine.behavior.'.$behavior))
			{
				$class->setAnnotationReader($reader);

				$this->configureBehavior($behavior, $class);

				$evm->addEventSubscriber($class);
			}
		}

		if ($mapping = $config->getMetadataDriverImpl())
		{
			$type = 'registerMappingIntoDriverChainORM';

			if ($this->getConfig('behavior.superclass', false))
			{
				$type = 'registerAbstractMappingIntoDriverChainORM';
			}

			DoctrineExtensions::$type(
				$mapping,
				$reader
			);
		}
	}

	/**
	 * Configures Behavior Subscriber
	 *
	 * @param  string          $behavior
	 * @param  EventSubscriber $es
	 */
	protected function configureBehavior($behavior, EventSubscriber $es)
	{
		switch ($behavior) {
			case 'translatable':
				$es->setTranslatableLocale(\Config::get('language', 'en'));
				$es->setDefaultLocale(\Config::get('language_fallback', 'en'));
				break;
		}
	}
}
