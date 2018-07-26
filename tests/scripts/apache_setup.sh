#!/usr/bin/env bash

if ! mkdir -p /home/travis/build/BoltApp/bolt-magento1/; then
echo "Web directory already Exist !"
else
echo "Web directory created with success !"
fi

echo "<VirtualHost *:80>
    ServerName travis_magento.loc
    DocumentRoot '/home/travis/build/BoltApp/bolt-magento1/'
    ErrorLog '/home/travis/build/BoltApp/local-errors.log'

    <Directory '/home/travis/build/BoltApp/bolt-magento1/'>
        AllowOverride All
        Options Indexes MultiViews FollowSymLinks
        Require all granted
    </Directory>
</VirtualHost>" > /etc/apache2/sites-available/travis_magento.conf

if ! echo -e /etc/apache2/sites-available/travis_magento.conf; then
echo "Virtual host wasn't created !"
else
echo "Virtual host created !"
fi

sh -c "echo '127.0.0.1     travis_magento.loc' >> /etc/hosts"

echo "Testing configuration"
sudo apachectl configtest

sudo apachectl -k restart

echo "==================================================================================="
echo "All works done! You should be able to see your website at http://travis_magento.loc"
echo "==================================================================================="
echo ""
