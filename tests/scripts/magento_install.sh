#!/usr/bin/env bash

set -e
set -u
set -x

echo "Installing magento..."
# Fix the error when trying to fetch jessie backports repository
echo "deb [check-valid-until=no] http://archive.debian.org/debian jessie-backports main" > /etc/apt/sources.list.d/jessie-backports.list
sed -i '/deb http:\/\/deb.debian.org\/debian jessie-updates main/d' /etc/apt/sources.list
# FSince Jessie is no longer the most recent stable release for Debian, we have to set Acquire::Check-Valid-Until=false
apt-get -o Acquire::Check-Valid-Until=false update
apt-get -y install curl php5-curl mysql-client php5-mcrypt php5-xdebug
php5enmod mcrypt

curl -O https://files.magerun.net/n98-magerun.phar
chmod +x n98-magerun.phar

MAGENTO_DIR='./magento'
php -d memroy_limit=512M n98-magerun.phar install --magentoVersionByName=$MAGENTO_VERSION \
  --installationFolder="${MAGENTO_DIR}" --dbHost=127.0.0.1 --dbUser=root --dbName=circle_test  --dbPass="" \
  --installSampleData=no --useDefaultConfigParams=yes --baseUrl=http://ci.test.magento1/
php n98-magerun.phar config:set currency/options/base USD --root-dir $MAGENTO_DIR
php n98-magerun.phar config:set currency/options/default USD --root-dir $MAGENTO_DIR
php n98-magerun.phar config:set currency/options/allow USD --root-dir $MAGENTO_DIR
cp -r magento/. .
