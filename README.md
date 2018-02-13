# MagentoIntegration
Plugin to integrate Bolt with Magento

## Supported Magento versions
+ 1.7
+ 1.9

### Releases
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

1. Bump plugin version in `app/code/local/Bolt/Boltpay/etc/config.xml`

2. Check what is the latest versions

> aws s3 ls s3://bolt-public/magento-integration-release/

3. Create .tar.gz file and .zip file.

> ./create_release.sh

4. Upload to s3

> aws s3 cp magento.zip s3://bolt-public/magento-integration-release/magento.zip --acl public-read

> aws s3 cp bolt_magento_plugin.tar.gz s3://bolt-public/magento-integration-release/magento_integration_vxxx.tar.gz --acl public-read

5. Add git tag

```
git tag magento_v0.xx
git push origin --tags
```

## Run tests

Run the following (Assuming you have MAMP)

> /Applications/MAMP/bin/php/php5.6.25/bin/php /usr/local/bin/phpunit
