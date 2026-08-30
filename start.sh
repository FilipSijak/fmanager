#!/usr/bin/env bash

set -euo pipefail

docker compose up -d

exec ./watch.sh
