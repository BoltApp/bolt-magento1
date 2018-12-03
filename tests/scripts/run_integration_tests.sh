#!/usr/bin/env bash

set -e
set -u
set -x

mkdir -p $SCREENSHOT_DIR
git clone git@github.com:BoltApp/integration-tests.git
cd integration-tests
npm install
TEST_ENV=sandbox npm run test-checkout-magento1
