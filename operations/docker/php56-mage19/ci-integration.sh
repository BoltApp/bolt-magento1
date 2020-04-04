mysql -u root -h 127.0.0.1 circle_test < magento-sample-data-1.9.2.4/magento_sample_data_for_1.9.2.4.sql
php -d memroy_limit=512M n98-magerun.phar install --magentoVersionByName=magento-mirror-1.9.3.6 \
  --installationFolder=/var/www/html --forceUseDb --noDownload --dbHost=127.0.0.1 --dbUser=circleci --dbName=circle_test  --dbPass="" \
  --installSampleData=yes --useDefaultConfigParams=yes --baseUrl=http://m1-test.kazuki.dev.bolt.me

cd /var/www/html
# we can do this when we include DB in docker
# php init-bolt.php TODO

chown -R www-data:www-data /var/www/html
