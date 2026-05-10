#!/bin/bash

set -euo pipefail

php artisan migrate:fresh --database=test-mysql
php artisan db:seed --database=test-mysql

php ./vendor/bin/phpunit --testsuite Integration "$@"
