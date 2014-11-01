# FuelPHP Doctrine

[![Latest Version](https://img.shields.io/github/release/indigophp/fuelphp-doctrine.svg?style=flat-square)](https://github.com/indigophp/fuelphp-doctrine/releases)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Total Downloads](https://img.shields.io/packagist/dt/indigophp/fuelphp-doctrine.svg?style=flat-square)](https://packagist.org/packages/indigophp/fuelphp-doctrine)

**This package is a wrapper around [doctrine/doctrine2](https://github.com/doctrine/doctrine2) package.**


## Install

Via Composer

``` bash
$ composer require indigophp/fuelphp-doctrine
```


## Usage

Simply install this package to be able to use Doctrine inside FuelPHP.


## Configuration

To make it work, you need the following `doctrine` configuration.

``` php
	'dbal'                        => 'default',
	'proxy_dir'                   => '/tmp',
	'proxy_namespace'             => 'PrOxYnAmEsPaCe',
	'auto_generate_proxy_classes' => true,
	'mappings'                    => array(
		'mapping' => array(
			'type'   => 'xml',
			'dir'    => '/mypath',
			'prefix' => 'MyPrefix',
		),
	),
	'cache_driver'                => 'array',
```

You can also use the `Setup` class to auto configure the `Configuration` object.

``` php
	'dbal'            => 'default',
	'auto_config'     => true,
	'dev_mode'        => \Fuel::$env === \Fuel::DEVELOPMENT,
	'proxy_dir'       => '/tmp',
	'cache_driver'    => 'array',
```


### Multiple managers

By default you have one manager (`default`). If you would like use multiple managers, you have to add a key `managers` to your doctrine config, and set your configurations there. You can also set global configurations in the config root. Make sure to set `auto_mapping` to `false`.

``` php
	'auto_mapping'    => false,
	'dbal'            => 'default',
	'managers'        => array(
		'default'   => array(),
		'aditional' => array()
	),
```


**Note:** This package uses [indigophp/fuelphp-dbal](https://github.com/indigophp/fuelphp-dbal) for connections. Check the package documentation.


## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.


## Credits

- [Márk Sági-Kazár](https://github.com/sagikazarmark)
- [aspendigital](https://github.com/aspendigital/fuel-doctrine2)
- [All Contributors](https://github.com/indigophp/fuelphp-doctrine/contributors)


## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
