#!/bin/bash

php artisan migrate:fresh --database=fmanager-test
php artisan db:seed --database=tfmanager-test

php ./vendor/bin/phpunit $1 $2
