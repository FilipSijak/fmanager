#!/usr/bin/env bash

# Set the correct user permissions
if [[ -z "${UID_GID}" ]]; then
  export UID_GID="$(id -u):$(id -g)"
fi

docker compose --profile install run --rm pnpm $@
