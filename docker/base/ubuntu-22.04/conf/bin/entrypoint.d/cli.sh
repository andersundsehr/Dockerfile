#!/usr/bin/env bash

#############################################
## Run CLI_SCRIPT from environment variable
#############################################

if [ -n "${CLI_SCRIPT:-}" ]; then
    # the arguments after "cli"
    shift
    if [ -n "${APPLICATION_USER:-}" ]; then
        # Run as APPLICATION_USER
        exec gosu "${APPLICATION_USER}" ${CLI_SCRIPT} "$@"
    else
        # Run as root
        exec ${CLI_SCRIPT} "$@"
    fi
else
    echo "[ERROR] No CLI_SCRIPT in in docker environment defined"
    exit 1
fi
