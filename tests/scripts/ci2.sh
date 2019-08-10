#!/usr/bin/env bash

set -e
set -u
set -x

MAGENTO_DIR='./magento'
cd /home/circleci

echo "Waiting for DB..."
while ! mysql -uroot -h 127.0.0.1 -e "SELECT 1" >/dev/null 2>&1; do
    sleep 1
done

php -d memroy_limit=512M n98-magerun.phar install --magentoVersionByName=$MAGENTO_VERSION \
  --installationFolder="${MAGENTO_DIR}" --forceUseDb --noDownload --dbHost=127.0.0.1 --dbUser=root --dbName=circle_test  --dbPass="" \
  --installSampleData=no --useDefaultConfigParams=yes --baseUrl=http://ci.test.magento1/
php n98-magerun.phar config:set currency/options/base USD --root-dir $MAGENTO_DIR
php n98-magerun.phar config:set currency/options/default USD --root-dir $MAGENTO_DIR
php n98-magerun.phar config:set currency/options/allow USD --root-dir $MAGENTO_DIR

rsync -a project/ magento/
rm -rf project/*

cd magento
tests/scripts/run_phpunit_tests.sh
