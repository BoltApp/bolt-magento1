#!/usr/bin/env bash

set -e
set -u
set -x

tests/scripts/magento_install.sh "$@"
tests/scripts/run_phpunit_tests.sh "$@"
