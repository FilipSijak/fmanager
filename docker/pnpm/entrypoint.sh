#!/bin/sh

# If no arguments are passed to the container, run the default commands
if [ $# -eq 0 ]; then
  pnpm install && pnpm run build
else
  # If arguments are provided, run them as a command
  exec pnpm "$@"
fi
