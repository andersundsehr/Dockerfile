#!/usr/bin/env bash

#############################################
## Run CLI_SCRIPT from environment variable
#############################################

if [ -n "${CLI_SCRIPT:-}" ]; then
    # the arguments after "cli"
    shift
    # APPLICATION_USER, by default application; root in the images without that user (base, nginx, apache)
    CLI_USER="${APPLICATION_USER:-application}"
    if id -u "$CLI_USER" > /dev/null 2>&1; then
        exec gosu "$CLI_USER" ${CLI_SCRIPT} "$@"
    else
        exec ${CLI_SCRIPT} "$@"
    fi
else
    echo "[ERROR] No CLI_SCRIPT in in docker environment defined"
    exit 1
fi
