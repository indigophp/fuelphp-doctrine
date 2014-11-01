<?php

/*
 * This file is part of the FuelPHP Doctrine package.
 *
 * (c) Indigo Development Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Indigo\Fuel\Doctrine\Providers;

use Fuel\Dependency\ServiceProvider;
use Doctrine\Common\Cache\Cache;

/**
 * Provides Doctrine service
 *
 * @author Márk Sági-Kazár <mark.sagikazar@gmail.com>
 */
class FuelServiceProvider extends ServiceProvider
{
	/**
	 * {@inheritdoc}
	 */
	public $provides = [
		'doctrine.manager',
		'doctrine.metadata.php',
		'doctrine.metadata.xml',
		'doctrine.metadata.simplified_xml',
		'doctrine.metadata.yml',
		'doctrine.metadata.simplified_yml',
		'doctrine.cache.array',
		'doctrine.cache.apc',
		'doctrine.cache.xcache',
		'doctrine.cache.wincache',
		'doctrine.cache.zend',
		'doctrine.behavior.blameable',
		'doctrine.behavior.iptraceable',
		'doctrine.behavior.loggable',
		'doctrine.behavior.sluggable',
		'doctrine.behavior.soft_deletable',
		'doctrine.behavior.sortable',
		'doctrine.behavior.timestampable',
		'doctrine.behavior.translatable',
		'doctrine.behavior.tree',
		'doctrine.behavior.uploadable',
	];

	/**
	 * Default configuration values
	 *
	 * @var []
	 */
	protected $defaultConfig = [];

	public function __construct()
	{
		\Config::load('doctrine', true);

		$config = \Config::get('doctrine', []);
		$this->defaultConfig = \Arr::filter_keys($config, ['managers', 'types'], true);

		// We don't have defined managers
		if ($managers = \Arr::get($config, 'managers', false) and ! empty($managers))
		{
			\Config::set('dbal.managers.__default__', []);
		}

		// if (\Arr::get($manager, 'mapping.auto', false) and count($managers) > 1)
		// {
		// 	throw new \LogicException('Auto mapping is only possible if exactly one manager is used.');
		// }
	}

	/**
	 * {@inheritdoc}
	 */
	public function provide()
	{
		$this->register('doctrine.manager', function($dic, $name = '__default__', array $config = [])
		{
			$config = array_merge($this->defaultConfig, \Config::get('doctrine.managers.'.$name, []), $config);

			return $dic->resolve('Indigo\\Fuel\\Doctrine\\Manager', [$config]);
		});

		$this->register('doctrine.metadata.php', function($dic, $paths = [])
		{
			return $dic->resolve('Doctrine\\ORM\\Mapping\\Driver\\PHPDriver', [$paths]);
		});

		$this->register('doctrine.metadata.xml', function($dic, $paths = [])
		{
			return $dic->resolve('Doctrine\\ORM\\Mapping\\Driver\\XmlDriver', [$paths]);
		});

		$this->register('doctrine.metadata.simplified_xml', function($dic, $paths = [])
		{
			return $dic->resolve('Doctrine\\ORM\\Mapping\\Driver\\SimplifiedXmlDriver', [$paths]);
		});

		$this->register('doctrine.metadata.yml', function($dic, $paths = [])
		{
			return $dic->resolve('Doctrine\\ORM\\Mapping\\Driver\\YamlDriver', [$paths]);
		});

		$this->register('doctrine.metadata.simplified_yml', function($dic, $paths = [])
		{
			return $dic->resolve('Doctrine\\ORM\\Mapping\\Driver\\SimplifiedYamlDriver', [$paths]);
		});

		$this->register('doctrine.cache.array', 'Doctrine\\Common\\Cache\\ArrayCache');
		$this->register('doctrine.cache.apc', 'Doctrine\\Common\\Cache\\ApcCache');
		$this->register('doctrine.cache.xcache', 'Doctrine\\Common\\Cache\\XcacheCache');
		$this->register('doctrine.cache.wincache', 'Doctrine\\Common\\Cache\\WincacheCache');
		$this->register('doctrine.cache.zend', 'Doctrine\\Common\\Cache\\ZendDataCache');

		$this->register('doctrine.behavior.blameable', 'Gedmo\\Blameable\\BlameableListener');
		$this->register('doctrine.behavior.iptraceable', 'Gedmo\\IpTraceable\\IpTraceableListener');
		$this->register('doctrine.behavior.loggable', 'Gedmo\\Loggable\\LoggableListener');
		$this->register('doctrine.behavior.sluggable', 'Gedmo\\Sluggable\\SluggableListener');
		$this->register('doctrine.behavior.soft_deletable', 'Gedmo\\SoftDeletable\\SoftDeletableListener');
		$this->register('doctrine.behavior.sortable', 'Gedmo\\Sortable\\SortableListener');
		$this->register('doctrine.behavior.timestampable', 'Gedmo\\Timestampable\\TimestampableListener');

		$this->register('doctrine.behavior.translatable', function($dic)
		{
			$es = $dic->resolve('Gedmo\\Translatable\\TranslatableListener');
			$es->setTranslatableLocale(\Config::get('language', 'en'));
			$es->setDefaultLocale(\Config::get('language_fallback', 'en'));

			return $es;
		});

		$this->register('doctrine.behavior.tree', 'Gedmo\\Tree\\TreeListener');
		$this->register('doctrine.behavior.uploadable', 'Gedmo\\Uploadable\\UploadableListener');
	}
}
