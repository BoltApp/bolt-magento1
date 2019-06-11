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

## Custom Bolt Events

| Name | Description | Parameters |
| --- | --- | --- | 
| bolt_boltpay_shipping_method_applied  | | | |
| bolt_boltpay_discounts_applied_to_bolt_order  | | | |
| bolt_boltpay_correct_billing_address_for_bolt_order  | | | |
| bolt_boltpay_tax_applied_to_bolt_order  | | | |
| bolt_boltpay_correct_shipping_address_for_bolt_order  | | | |
| bolt_boltpay_shipping_applied_to_bolt_order  | | | |
| bolt_boltpay_filter_bolt_checkout_javascript  | | | |
| bolt_boltpay_order_received_before  | Entry for altering the behavior of order activation and reception, (i.e. after an order payment has been authorized) | **order**<br>_Mage_Sales_Model_Order_<br>The recently converted order prior to being confirmed as authorized<br><br>**payload**<br>_object_<br>Bolt payload |
| bolt_boltpay_order_received_after  | Entry for adding additional behavior to order reception, (i.e. after an order payment has been authorized) | **order**<br>_Mage_Sales_Model_Order_<br>The authorized and activated order<br><br>**payload**<br>_object_<br>Bolt payload |

## Custom Bolt Filters
| Name | Description | Parameters |
| --- | --- | --- | 
| bolt_boltpay_filter_bolt_checkout_javascript  | | | |

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
