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

## Custom Bolt Event Reference

| Name | Area | Description | Parameters | Filtered Value | 
| --- | --- | --- | --- | --- |
| bolt_boltpay_shipping_estimate_before | global | Performed before all shipping and tax estimates are calculated.  Custom environment state initialization logic can be set here. | **quote**<br>_Mage_Sales_Model_Quote_<br>the immutable quote<br><br>**transaction**<br>_object_<br>Bolt payload | | 
| bolt_boltpay_shipping_method_applied_before | global | Executed prior to a specific shipping method is applied to quote for shipping and tax calculation.  This is used for setting any shipping method specific conditions. |  **quote**<br>_Mage_Sales_Model_Quote_<br>the immutable quote<br><br>**shippingMethodCode**<br>_string_ Shipping rate code composed of {carrier}_{method}|
| bolt_boltpay_shipping_method_applied_after | global | Executed after a specific shipping method is applied to quote for shipping and tax calculation.  Shipping method specific cleanup logic is typically performed from here. |  **quote**<br>_Mage_Sales_Model_Quote_<br>the immutable quote<br><br>**shippingMethodCode**<br>_string_<br>Shipping rate code composed of {carrier}_{method}|
| bolt_boltpay_filter_shipping_and_tax_estimate | global | Allows for filtering the estimate array that is sent to Bolt.  This allows for things like options to be added or removed, labels to be changed, price to be adjusted, etc. |  <br><br>**quote**<br>_Mage_Sales_Model_Quote_<br>the immutable quote | **array** The complete response array that is sent to Bolt from the shipping and tax endpoint |
| bolt_boltpay_order_creation_before | global | Entry-point for applying any pre-conditions prior to the conversion of a Bolt order to Magento order | **immutableQuote**<br>_Mage_Sales_Model_Quote_<br>the Magento cart copy of the Bolt order<br><br>**parentQuote**<br>_Mage_Sales_Model_Quote_<br>the original Magento cart session<br><br>**transaction**<br>_object_<br>Bolt payload | | 
| bolt_boltpay_order_creation_after | global | Allows for post order creation actions to be applied exclusively to Bolt orders | **order**<br>_Mage_Sales_Model_Order_<br>the converted and saved Magento order<br><br>**quote**<br>_Mage_Sales_Model_Quote_<br>the immutable quote<br><br>**transaction**<br>_object_<br>Bolt payload | | 
| bolt_boltpay_validate_cart_session_before | global | Entry for altering the behavior of session validation prior to standard session validation | **immutableQuote**<br>_Mage_Sales_Model_Quote_<br>the Magento cart copy of the Bolt order<br><br>**parentQuote**<br>_Mage_Sales_Model_Quote_<br>the original Magento cart session<br><br>**transaction**<br>_object_<br>Bolt payload | |
| bolt_boltpay_validate_cart_session_after | global | Entry for adding additional session validation behavior performed after standard session validation | **immutableQuote**<br>_Mage_Sales_Model_Quote_<br>the Magento cart copy of the Bolt order<br><br>**parentQuote**<br>_Mage_Sales_Model_Quote_<br>the original Magento cart session<br><br>**transaction**<br>_object_<br>Bolt payload | |
| bolt_boltpay_validate_coupons_before | global | Entry for altering the behavior of coupon validation prior to standard coupon validation | **immutableQuote**<br>_Mage_Sales_Model_Quote_<br>the Magento cart copy of the Bolt order<br><br>**transaction**<br>_object_<br>Bolt payload | |
| bolt_boltpay_validate_coupons_after | global | Entry for adding additional coupon validation behavior performed after standard coupon validation | **immutableQuote**<br>_Mage_Sales_Model_Quote_<br>the Magento cart copy of the Bolt order<br><br>**transaction**<br>_object_<br>Bolt payload | |
| bolt_boltpay_validate_totals_before | global | Entry for altering the behavior of subtotal validation prior to standard subtotal validation | **immutableQuote**<br>_Mage_Sales_Model_Quote_<br>the Magento cart copy of the Bolt order<br><br>**transaction**<br>_object_<br>Bolt payload | |
| bolt_boltpay_validate_totals_after | global | Entry for adding additional subtotal validation behavior performed after standard subtotal validation | **immutableQuote**<br>_Mage_Sales_Model_Quote_<br>the Magento cart copy of the Bolt order<br><br>**transaction**<br>_object_<br>Bolt payload | |
| bolt_boltpay_order_received_before | global | Entry for altering the behavior of order activation and reception, (i.e. after an order payment has been authorized) | **order**<br>_Mage_Sales_Model_Order_<br>The recently converted order prior to being confirmed as authorized<br><br>**payload**<br>_object_<br>Bolt payload | |
| bolt_boltpay_order_received_after | global | Entry for adding additional behavior to order reception, (i.e. after an order payment has been authorized) | **order**<br>_Mage_Sales_Model_Order_<br>The authorized and activated order<br><br>**payload**<br>_object_<br>Bolt payload | |