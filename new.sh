#!/bin/bash

php artisan migrate:fresh
php artisan db:seed

curl --request GET -sL \
     --url 'http://localhost:8000/api/startNewGame'
