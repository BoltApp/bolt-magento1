#!/usr/bin/env bash

red=`tput setaf 1`
green=`tput setaf 2`
reset=`tput sgr0`

cd /home/travis/build/BoltApp/bolt-magento1/

git clone https://${INTEGRATION_TESTS_TOKEN}@github.com/BoltApp/integration-tests.git/

cd integration-tests

npm install

TEST_ENV=sandbox xvfb-run npm run test-checkout-magento1