# Bolt Magento1 Plugin [![Build Status](https://travis-ci.org/BoltApp/bolt-magento1.svg?branch=develop)](https://travis-ci.org/BoltApp/bolt-magento1)
Plugin to integrate Bolt with Magento

See [CHANGELOG.md](./CHANGELOG.md) for change history.

## Supported Magento versions
+ 1.7
+ 1.9

## Supported PHP versions
5.4+

## Installation guide
[Magento 1 plugin installation guide](https://docs.bolt.com/docs/magento-integration-guide)

## Run tests

Run the following from root magento folder:

> php tests/unit/phpunit-5.7.9.phar -c tests/unit/phpunit.xml

Run phpunit test with coverage html report:

> php tests/unit/phpunit-5.7.9.phar -c tests/unit/phpunit.xml --coverage-html tests/unit/coverage

If you prefer to run test through PHPStorm, please read:

> http://devdocs.magento.com/guides/v2.2/test/unit/unit_test_execution_phpstorm.html 
