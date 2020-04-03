#!/usr/bin/env bash

set -e
set -u
set -x

echo "Installing magento..."
curl -O https://files.magerun.net/n98-magerun.phar
chmod +x n98-magerun.phar
MAGENTO_DIR='./magento'
php -d memroy_limit=512M n98-magerun.phar install --magentoVersionByName=$MAGENTO_VERSION \
  --installationFolder="${MAGENTO_DIR}" --dbHost=127.0.0.1 --dbUser=root --dbName=circle_test --dbPass="" \
  --installSampleData=no --useDefaultConfigParams=yes --baseUrl=http://ci.test.magento1/
php n98-magerun.phar config:set currency/options/base USD --root-dir $MAGENTO_DIR
php n98-magerun.phar config:set currency/options/default USD --root-dir $MAGENTO_DIR
php n98-magerun.phar config:set currency/options/allow USD --root-dir $MAGENTO_DIR
cp -r magento/. .

export TEST_ENV=local
export PHPUNIT_ENVIRONMENT=true

if [ "$1" == "nocov" ]; then
  php ${PHPUNIT_PHAR} --verbose --stderr --report-useless-tests -c tests/unit/phpunit.xml
else
  php ${PHPUNIT_PHAR} --verbose --stderr --report-useless-tests -c tests/unit/phpunit.xml --coverage-clover=./artifacts/coverage.xml
  bash <(curl -s https://bolt-devops.s3-us-west-2.amazonaws.com/testing/codecov_uploader) -f ./artifacts/coverage.xml -F $TEST_ENV
fi
