# Magento 1 Integration
Plugin to integrate Bolt with Magento

## Supported Magento versions
+ 1.7
+ 1.9

### Releases
+ [v1.0.6](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/bolt-magento1_v1.0.6.zip) (March 27) (bugsnag improvement, amasty, remove min/max check)
+ [v1.0.3](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/bolt-magento1_v1.0.3.zip) (March 17)
+ [v1.0.2](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/magento1.0.2.zip) (March 14) (Move library code to community)
+ [v1.0.0](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/magento1.0.0.zip) (March 6)
+ [v0.0.18](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/magento0018.zip) (Feb 21, 2018) (quote creation reduction, merchant defined email)
+ [v0.0.17](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/magento0017.zip) (Feb 16, 2018) (bugfix for quote creation, removal of OAuth requirement, and fix total mismatch error)
+ [v0.0.15](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/magento_integration_v0015.zip) (Feb 12, 2018 (including bugsnag update, fix for shipping and tax estimate in firecheckout, fix for long descriptions)
+ [v0.0.14](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/magento_integration_v0014.zip) (Jan 23, 2018)
+ [v0.0.13](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/magento_integration_v0013.zip)
+ [v0.0.12](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/magento_integration_v0012.tar.gz) (Dec 19, 2017)
+ [v0.0.11](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/magento_integration_v0011.tar.gz) (Dec 18, 2017)
+ [v0.0.10](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/magento_integration_v0010.tar.gz) (Dec 13, 2017)
+ [v0.0.9](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/magento_integration_v009.tar.gz) (Nov 28, 2017)
+ [v0.0.8](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/magento_integration_v008.tar.gz) (Nov 6, 2017)
+ [v0.0.7](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/magento_integration_v007.tar.gz)
+ [v0.0.6](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/magento_integration_v006.tar.gz)
+ [v0.0.5](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/magento_integration_v005.tar.gz)
+ [v0.0.4](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/magento_integration_v004.tar.gz)
+ [v0.0.3](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/magento_integration_v003.tar.gz)
+ [v0.0.2](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/magento_integration_v002.tar.gz)
+ [v0.0.1](https://s3-us-west-1.amazonaws.com/bolt-public/magento-integration-release/magento_integration_v001.tar.gz)

## Release instructions

1. Check what is the latest versions

> aws s3 ls s3://bolt-public/magento-integration-release/

2. Bump plugin version in `app/code/local/Bolt/Boltpay/etc/config.xml`

3. Create .zip file.

> ./create_release.sh

4. Upload to s3

> aws s3 cp magento.zip s3://bolt-public/magento-integration-release/bolt-magento1_v1.0.3.zip --acl public-read

5. [Update installation guide](https://dash.readme.io/project/bolt/v1/docs/magento-integration-guide) with the latest link


## Run tests

Run the following (Assuming you have MAMP)

> /Applications/MAMP/bin/php/php5.6.25/bin/php /usr/local/bin/phpunit
