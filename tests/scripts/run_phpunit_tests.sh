#!/usr/bin/env bash

export TEST_ENV=local
export PHPUNIT_ENVIRONMENT=true

php ${PHPUNIT_PHAR} --verbose --stderr --report-useless-tests -c tests/integration/phpunit.xml
