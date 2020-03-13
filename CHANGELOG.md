# Changelog
## [V2.4.0](https://bolt-public.s3-us-west-1.amazonaws.com/magento-integration-release/bolt-magento1-v2.4.0.zip) 2020-03-12
 - Added support for a bunch of product types for product page checkout. Now the following are supported:
    - Simple Product
    - Simple Product w/ Custom Options
    - Configurable Product
    - Grouped Product
    - Virtual Product
    - Bundle Product
    - Downloadable Product with logged in customer required
 - Fixed a bug where images for products with variants are not populated correctly
 - Unit test coverage ~100%
## [V2.3.0](https://bolt-public.s3-us-west-1.amazonaws.com/magento-integration-release/bolt-magento1-v2.3.0.zip) 2020-02-13
 - Fixed an intermittent issue related to virtual product
 - Prevent processing on non-Bolt orders or orders in unexpected states
 - Various other bug fixes
 - Another massive increase in unit test coverage
## [V2.2.0](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/bolt-magento1_v2.2.0.zip) 2020-01-30
 - Better Bolt-side metrics and logging
 - Cleanup abandoned failed pre-auth orders preemptively
 - Fix inventory issue around rejected orders
 - Massively increased unit test coverage
 - Various small bugfixes
## [V2.1.0](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/bolt-magento1_v2.1.0.zip) 2019-11-22
 - Restrict Bolt by customer group feature
 - Create memo popup on offline refund attempt
 - Automatic invoice creation after shipment
 - Replace curl usages with guzzle
 - Squelch emails on irreversibly rejected hooks
 - Various small bugfixes 
## [V2.0.2](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/bolt-magento1_v2.0.2.zip) 2019-09-26
 - Internal testing fixes
 - Order note support
 - Add additional filter support
 - Increased datadog support
 - Some pre-auth fixes
## [V2.0.1](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/bolt-magento1_v2.0.1.zip) 2019-07-03
 - Improved logging
 - Improved multistore support
 - Add optional global JS injection
## [V2.0.0](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/bolt-magento1_v2.0.0.zip) 2019-06-12
 - "Pre-auth release"
   - Elimination of inventory errors
   - Real-time order processing to better match sales timelines
   - Improved customer messaging
   - In-app order note support
   - Native events for seamless 3rd party plugin support

## [V1.4.1](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/bolt-magento1_v1.4.1.zip) 2019-04-02
 - Various improvements to Bolt Order creation
 - Bug fixes

## [V1.4.0](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/bolt-magento1_v1.4.0.zip) 2019-02-21
 - Order creation refactor - now supports more granular calculation overrides for easier future upgradability
 - Improved caching and DB resource consumption
 - Configurable deferred immutable quote creation
 - Added additional events to better support plugin customization
 - Bug fixes

## [V1.3.8](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/bolt-magento1_v1.3.8.zip) 2019-01-31
 - Apple Pay support
 - Item properties support
 - Product Page Checkout (beta)
 - Bug fixes

## [V1.3.7](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/bolt-magento1_v1.3.7.zip) 2018-11-08
 - Implement support for multiple capture
 - Bug fixes around admin order

## [V1.3.6](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/bolt-magento1_v1.3.6.zip) 2018-10-21
 - Bug fixes

## [V1.3.5](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/bolt-magento1_v1.3.5.zip) 2018-10-15
 - Fix session related bug
 - Add user name to order
 - Tweak bugsnag

## [v1.3.4](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/bolt-magento1_v1.3.4.zip) 2018-10-08
 - Cleanup
 - Tweak logic of quote cleanup - keep quotes that tied to orders

## [v1.3.3](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/bolt-magento1_v1.3.3.zip) 2018-10-02
 - Fix for not including JavaScript when not needed
 - Bugfix around shipping&tax

## [v1.3.2](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/bolt-magento1_v1.3.2.zip) 2018-09-29
 - Refactoring
 - Minor bugfix

## [v1.3.1](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/bolt-magento1_v1.3.1.zip) 2018-09-25
 - Refactoring
 - Fix issue with firecheckout
 - Allow merchants to edit orders

## [v1.3.0](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/bolt-magento1_v1.3.0.zip) 2018-09-17
 - Add cron to cleanup expired immutable quotes
 - Remove JS alert and replaced with custom UI

## [v1.2.3](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/bolt-magento1_v1.2.3.zip) 2018-09-12
 - Improved firecheckout loading performance
 - Fix issue with webhook and user session
 - Fix issue with missing region

## [v1.2.2](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/bolt-magento1_v1.2.2.zip) 2018-08-28
 - Fixed off-by-one error
 - Optimize loading
 - Support discount hook

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
 - Added support for taxjar extension
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
