#!/usr/bin/env bash

red=`tput setaf 1`
green=`tput setaf 2`
reset=`tput sgr0`

cd /home/travis/build/BoltApp/bolt-magento1/

export PHPUNIT_ENVIRONMENT=$PHPUNIT_ENVIRONMENT

php ${PHPUNIT_PHAR} -c tests/unit/phpunit.xml

