# Changelog
## [v1.2.2](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/bolt-magento1_v1.2.2.zip) 2018-08-28
 - Fixed off-by-one error
 - Optimize loading

## [v1.2.1](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/bolt-magento1_v1.2.1.zip) 2018-08-17
 - Introduced modman
 - Tweaked explanation of publishable keys
 - Bugfix for:
  - shipping discount
  - refund hook
  - region_id

## [v1.2.0](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/bolt-magento1_v1.2.0.zip) 2018-07-29
 - Bugfix around duplicate orders
 - Admin order creation bugfix
 - Better multi-store support

## [v1.1.7](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/bolt-magento1_v1.1.7.zip) 2018-07-23
 - Add product type
 - Stability fix for quote lookup
 - Admin order creation bugfix

## [v1.1.6](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/bolt-magento1_v1.1.6.zip) 2018-07-10
 - refund hook support
 - fix on-hold was accidentally updated via hook
 - fix off-by-one price mismatch
 - fix issue where clone was accidentally deleted before order creation
 - fix around void

## [v1.1.5](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/bolt-magento1_v1.1.5.zip) 2018-06-28
 - Minor bugfixes around payment from magento admin
 - Enhancement of bugsnag report
 - Bugfix around shipping rules
 - Make publishable keys unencrypted

## [v1.1.4](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/bolt-magento1_v1.1.4.zip) 2018-06-17
 - Support order creation from magento admin
 - Add option to disallow shipment to POBox

## [v1.1.3](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/bolt-magento1_v1.1.3.zip) 2018-06-12
 - Add index to fix performance issue
 - Minor fix around transaction states

## [v1.1.2](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/bolt-magento1_v1.1.2.zip) 2018-06-06
 - Support for mini-cart
 - Remove legacy status page in admin
 - Fix shipping and tax prefetch when IP-address cannot resolve address

## [v1.1.1](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/bolt-magento1_v1.1.1.zip) 2018-06-04
 - Support partial capture
 - Bugfix around customer group based tax

## [v1.1.0](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/bolt-magento1_v1.1.0.zip) 2018-05-31
 - Do not load JavaScript when not needed
 - Handling for virtual cart
 - Update way to handle quote to disallow mutating quote after checkout is opened

## [v1.0.12](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/bolt-magento1_v1.0.12.zip) 2018-05-20
 - Fix discount rounding issue
 - Improve request logging

## [v1.0.11](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/bolt-magento1_v1.0.11.zip) 2018-05-03
 - Add workaround for magento's discount rounding issue (#42)
 - Fix issue with paymentonly flow

## [v1.0.10](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/bolt-magento1_v1.0.10.zip) 2018-05-01
 - Improved shipping and tax prefetch logic

## [v1.0.9](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/bolt-magento1_v1.0.9.zip) 2018-04-25
 - Add admin-only option to create order from admin page
 - Bugfix around inventory check
 - (Temporarily) disable address prefetch
 - Add extra logging

## [v1.0.8](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/bolt-magento1_v1.0.8.zip) 2018-04-11
 - Refine handling of shipping method selection
 - Tweak message in comment history
 - Optimize loading of connect.js

## [v1.0.7](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/bolt-magento1_v1.0.7.zip) 2018-04-05
 - Updated response format of hook to be JSON
 - Added support for taxjar extention
 - Proper sorting of shipping methods

## [v1.0.6](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/bolt-magento1_v1.0.6.zip) 2018-03-27
 - Refined bugsnag error tracking
 - Added support for amasty store credit
 - Removed configuration for min/max transaction amount

## [v1.0.3](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/bolt-magento1_v1.0.3.zip) 2018-03-17
 - Bugfix around shipping cost

## [v1.0.2](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/magento1.0.2.zip) 2018-03-14
 - Move library code to /community directory to allow easy override via users of the library

## [v1.0.0](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/magento1.0.0.zip) 2018-03-06
