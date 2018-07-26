#!/usr/bin/env bash

# defaults
SITE_DIR="/home/travis/build/BoltApp/bolt-magento1/"
SITE_URL="http://travis_magento.loc"
SITE_HOST="127.0.0.1"

PARSED_OPTIONS=$(getopt -n "$0"  -o 'd::,u::,h::' --long "dir::,url::,host::"  -- "$@")

#Bad arguments, something has gone wrong with the getopt command.
if [ $? -ne 0 ];
then
  exit 1
fi

eval set -- "$PARSED_OPTIONS"

# extract options and their arguments into variables.
while true ; do
    case "$1" in
        -d|--dir ) SITE_DIR="$2"; shift 2;;
        -u|--url ) SITE_URL="$2"; shift 2;;
        -h|--host ) SITE_HOST="$2"; shift 2;;
        --) shift; break;;
    esac
done

BREATH="\n\n"
SEP="================================================================================\n"

SOURCE="${BASH_SOURCE[0]}"
while [ -h "$SOURCE" ]; do # resolve $SOURCE until the file is no longer a symlink
  DIR="$( cd -P "$( dirname "$SOURCE" )" && pwd )"
  SOURCE="$(readlink "$SOURCE")"
  [[ $SOURCE != /* ]] && SOURCE="$DIR/$SOURCE" # if $SOURCE was a relative symlink, we need to resolve it relative to the path where the symlink file was located
done
SCRIPT_DIR="$( cd -P "$( dirname "$SOURCE" )" && pwd )"

echo "Script executing from path $SCRIPT_DIR"
printf $BREATH
echo "Updating and installing apache2 packages"
printf $SEP

sudo apt-get update
sudo apt-get install -y apache2 libapache2-mod-fastcgi make
sudo apt-get install -y php5-dev php-pear php5-mysql php5-gd php5-json
sudo a2enmod headers

printf $BREATH
echo "Enabling php-fpm"
printf $SEP

if [[ ${TRAVIS_PHP_VERSION:0:1} == "5" ]]; then sudo groupadd nobody; fi
# credit: https://www.marcus-povey.co.uk/2016/02/16/travisci-with-php-7-on-apache-php-fpm/
if [[ ${TRAVIS_PHP_VERSION:0:1} != "5" ]]; then cp $SCRIPT_DIR/www.conf ~/.phpenv/versions/$(phpenv version-name)/etc/php-fpm.d/; fi
cp ~/.phpenv/versions/$(phpenv version-name)/etc/php-fpm.conf.default ~/.phpenv/versions/$(phpenv version-name)/etc/php-fpm.conf
sudo a2enmod rewrite actions fastcgi alias
echo "cgi.fix_pathinfo = 1" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
~/.phpenv/versions/$(phpenv version-name)/sbin/php-fpm

printf $BREATH
echo "Configuring Apache virtual hosts"
printf $SEP
echo "Apache default virtual host configuration will be overwritten to serve $SITE_URL from $SITE_DIR"

sudo cp -f $SCRIPT_DIR/travis-ci-apache.conf /etc/apache2/sites-available/
sudo sed -e "s?%DIR%?$SITE_DIR?g" --in-place /etc/apache2/sites-available/travis-ci-apache.conf
sudo sed -e "s?%URL%?$SITE_URL?g" --in-place /etc/apache2/sites-available/travis-ci-apache.conf
sudo sh -c "echo '\n$SITE_HOST    $SITE_URL' >> /etc/hosts"
sudo a2ensite travis-ci-apache

printf $BREATH
echo "Restarting Apache"
printf $SEP

sudo service apache2 restart
