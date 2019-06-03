# Bolt Magento1 Plugin [![CircleCI](https://circleci.com/gh/BoltApp/bolt-magento1.svg?style=shield)](https://circleci.com/gh/BoltApp/bolt-magento1)

Plugin to integrate Bolt with Magento

See [CHANGELOG.md](./CHANGELOG.md) for change history.

## Supported Magento versions
+ 1.7
+ 1.9

## Supported PHP versions
5.4+

## Installation guide
[Magento 1 plugin installation guide](https://docs.bolt.com/docs/magento-integration-guide)

## Event Reference

| Name | Type | Area | Parameters | Description |
| --- | --- | --- | --- | --- |
| wc_bolt_after_set_cart_by_bolt_reference | action | bolt-payment-gateway-helpers.php | Allow for a merchant hook to do customization on cart content by Bolt Reference |
| wc_bolt_process_payment | action | class-bolt-checkout.php | Allow for a merchant hook prior to showing the success page |
| bolt_payment_checkout | action | class-bolt-checkout.php | Action to show the Bolt button on checkout page |
| wc_bolt_set_checkout_address_data | filter | class-bolt-address-helper.php | Allow for a merchant hook to add extra address fields |
| wc_bolt_order_creation_cart_data | filter | class-bolt-gateway-api.php | Add extra data to send to 'Bolt create_orders' API request |
| wc_bolt_after_load_shipping_options | filter | class-bolt-shipping-and-tax.php | Allow for a merchant hook to do customization on shipping options for shipping and tax method endpoint |
| wc_bolt_order_creation_hint_data | filter | bolt-cart-functions.php | Allow for a merchant hook to add extra hint data when creating Bolt order |

## Run tests

Run the following from root magento folder:

> php tests/unit/phpunit-5.7.9.phar -c tests/unit/phpunit.xml

Run phpunit test with coverage html report:

> php tests/unit/phpunit-5.7.9.phar -c tests/unit/phpunit.xml --coverage-html tests/unit/coverage

If you prefer to run test through PHPStorm, please read:

> http://devdocs.magento.com/guides/v2.2/test/unit/unit_test_execution_phpstorm.html 

## Modman guide
Our extension is set up for local development with [modman](https://github.com/colinmollenhour/modman).

To install modman, see the installation instructions [here](https://github.com/colinmollenhour/modman#installation).

Once modman is installed, change directory to your root Magento installation and run:
> modman init 

This will create an empty .modman folder in the Magento root directory. Then clone the repo by running:
> modman clone git@github.com:BoltApp/bolt-magento1.git

This will download the Bolt repository and symlink the modman Bolt files to the symlinked Magento files.
If you would like to pull the latest Bolt code from the Git repo and update Magento, simply run:
> modman update
