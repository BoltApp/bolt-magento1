#!/usr/bin/env bash


cd /home/travis/build/

curl -O https://files.magerun.net/n98-magerun.phar

chmod +x n98-magerun.phar

php -d memroy_limit=512M n98-magerun.phar install --magentoVersionByName="${MAGENTO_VERSION}" --installationFolder="./magento" --dbHost=localhost --dbUser=root --dbName="$DB_NAME" --dbPass="" --installSampleData=no --useDefaultConfigParams=yes --baseUrl="${HOST_NAME}"
php n98-magerun.phar config:set currency/options/base USD --root-dir ./magento/
php n98-magerun.phar config:set currency/options/default USD --root-dir ./magento/
php n98-magerun.phar config:set currency/options/allow USD --root-dir ./magento/

cp -r magento/. BoltApp/bolt-magento1/
