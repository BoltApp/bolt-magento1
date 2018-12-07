#!/usr/bin/env bash

export TEST_ENV=local
export PHPUNIT_ENVIRONMENT=true

php ${PHPUNIT_PHAR} --testdox --verbose --report-useless-tests -c tests/unit/phpunit.xml

