#!/bin/bash

set -euo pipefail

php ./vendor/bin/phpunit --testsuite Integration "$@"
