#!/usr/bin/env bash

set -e
set -u
set -x

cd /home/circleci

# update chrome
sudo apt-get update
sudo apt-get install lsb-release libappindicator3-1
curl -L -o google-chrome.deb https://dl.google.com/linux/direct/google-chrome-stable_current_amd64.deb
sudo dpkg -i google-chrome.deb
sudo sed -i 's|HERE/chrome"|HERE/chrome" --no-sandbox|g' /opt/google/chrome/google-chrome
rm google-chrome.deb

sudo apt-get upgrade -y git

ls -al /var/www/html
sudo rsync -a project/ /var/www/html/
# rm -rf project/*

echo "Waiting for DB..."
while ! mysql -uroot -h 127.0.0.1 -e "SELECT 1" >/dev/null 2>&1; do
    sleep 1
done

mysql -u root -h 127.0.0.1 circle_test < magento-sample-data-1.9.2.4/magento_sample_data_for_1.9.2.4.sql
php -d memroy_limit=512M n98-magerun.phar install --magentoVersionByName=magento-mirror-1.9.3.6 \
  --installationFolder=/var/www/html --forceUseDb --noDownload --dbHost=127.0.0.1 --dbUser=root --dbName=circle_test  --dbPass="" \
  --installSampleData=yes --useDefaultConfigParams=yes --baseUrl=http://m1-test.integrations.dev.bolt.me
php -d memroy_limit=512M n98-magerun.phar admin:user:create bolttest dev+m1-integration-admin@bolt.com bolt1234 \
  --root-dir /var/www/html --no-interaction

cd /var/www/html
php init-bolt.php $BOLT_SANDBOX_MERCHANT_API_KEY $BOLT_SANDBOX_MERCHANT_SIGNING_SECRET \ 
  $BOLT_SANDBOX_PUBLISHABLE_KEY_MULTISTEP $BOLT_SANDBOX_PUBLISHABLE_KEY_PAYMENTONLY $BOLT_SANDBOX_PUBLISHABLE_KEY_ADMIN
php ~/project/operations/docker/php56-mage19/init-bolt.php $BOLT_SANDBOX_MERCHANT_API_KEY $BOLT_SANDBOX_MERCHANT_SIGNING_SECRET \ 
  $BOLT_SANDBOX_PUBLISHABLE_KEY_MULTISTEP $BOLT_SANDBOX_PUBLISHABLE_KEY_PAYMENTONLY $BOLT_SANDBOX_PUBLISHABLE_KEY_ADMIN

sudo chown -R www-data:www-data /var/www/html

sudo service apache2 restart

cd /home/circleci
./ngrok authtoken $NGROK_TOKEN
./ngrok http 80 -hostname=m1-test.integrations.dev.bolt.me &
