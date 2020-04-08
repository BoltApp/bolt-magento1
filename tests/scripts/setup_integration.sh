#!/usr/bin/env bash

set -e
set -u
set -x

cd /home/circleci

# Start mysql
sudo service mysql start -- --initialize-insecure --skip-grant-tables --skip-networking --protocol=socket

sudo rsync -a project/ /var/www/html/

echo "Waiting for DB..."
while ! sudo mysql -uroot -h localhost -e "SELECT 1" >/dev/null 2>&1; do
    sleep 1
done

sudo mysql -u root -h localhost -e "CREATE USER 'magento'@'127.0.0.1'"
sudo mysql -u root -h localhost -e "GRANT ALL PRIVILEGES ON *.* TO 'magento'@'127.0.0.1' WITH GRANT OPTION"
sudo mysql -u root -h localhost -e "FLUSH PRIVILEGES"
mysql -u magento -h 127.0.0.1 -e "CREATE DATABASE IF NOT EXISTS magento"
mysql -u magento -h 127.0.0.1 magento < magento-sample-data-1.9.2.4/magento_sample_data_for_1.9.2.4.sql
php -d memroy_limit=512M n98-magerun.phar install --magentoVersionByName=magento-mirror-1.9.3.6 \
  --installationFolder=/var/www/html --forceUseDb --noDownload --dbHost=127.0.0.1 --dbUser=magento --dbName=magento  --dbPass="" \
  --installSampleData=yes --useDefaultConfigParams=yes --baseUrl=http://m1-test.integrations.dev.bolt.me
php -d memroy_limit=512M n98-magerun.phar admin:user:create bolttest dev+m1-integration-admin@bolt.com bolt1234 \
  --root-dir /var/www/html --no-interaction

INC_NUM=$((100*${CIRCLE_BUILD_NUM}))
mysql -u magento -h 127.0.0.1 -e "SET SQL_MODE='ALLOW_INVALID_DATES'; USE magento; ALTER TABLE sales_flat_quote AUTO_INCREMENT=${INC_NUM};"

cd /var/www/html
php ~/project/operations/docker/php56-mage19/init-bolt.php $BOLT_SANDBOX_MERCHANT_API_KEY $BOLT_SANDBOX_MERCHANT_SIGNING_SECRET \
  $BOLT_SANDBOX_PUBLISHABLE_KEY_MULTISTEP $BOLT_SANDBOX_PUBLISHABLE_KEY_PAYMENTONLY $BOLT_SANDBOX_PUBLISHABLE_KEY_ADMIN
rm -rf project/*

sudo chown -R www-data:www-data /var/www/html

sudo service apache2 restart

cd /home/circleci
./ngrok authtoken $NGROK_TOKEN
./ngrok http 80 -hostname=m1-test.integrations.dev.bolt.me &
