#!/bin/bash

php artisan migrate:fresh --database=test-mysql
php artisan db:seed --database=test-mysql

curl http://localhost:8000/api/setNewGame

php ./vendor/bin/phpunit
