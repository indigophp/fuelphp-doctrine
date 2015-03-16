<?php

/*
 * This file is part of the FuelPHP Doctrine package.
 *
 * (c) Indigo Development Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fuel\Doctrine\Providers;

use Fuel\Common\Arr;
use League\Container\ServiceProvider;
use Doctrine\Common\Cache\Cache;

/**
 * Provides Doctrine service
 *
 * @author Márk Sági-Kazár <mark.sagikazar@gmail.com>
 */
class FuelServiceProvider extends ServiceProvider
{
	/**
	 * @var array
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

	/**
	 * Initializes doctrine
	 */
	private function initDoctrine()
	{
		$app = $this->getApp();

		$config = $app->getConfig();

		$config->load('doctrine', true);

		$this->defaultConfig = Arr::filterKeys($config->get('doctrine', []), ['managers', 'types'], true);
	}

	/**
	 * {@inheritdoc}
	 */
	public function register()
	{
		$this->initDoctrine();

		$this->register('doctrine.manager', function($context, array $config = [])
		{
			if ($context->isMultiton())
			{
				$instance = $context->getName() ?: '__default__';
			}
			else
			{
				$instance = '__default__';
			}

			$app = $this->getApp();
			$conf = $app->getConfig();

			$config = array_merge($this->defaultConfig, $conf->get('doctrine.managers.'.$instance, []), $config);

			return $context->resolve('Fuel\\Doctrine\\Manager', [$config]);
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

	/**
	 * Returns the current application
	 *
	 * @return \Fuel\Foundation\Application
	 */
	private function getApp()
	{
		$stack = $this->resolve('requeststack');

		if ($request = $stack->top())
		{
			$app = $request->getApplication();
		}
		else
		{
			$app = $this->resolve('application::__main');
		}

		return $app;
	}
}
