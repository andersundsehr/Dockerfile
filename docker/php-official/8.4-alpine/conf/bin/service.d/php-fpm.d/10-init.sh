# the pool file of the values from the environment, which PHP-FPM reads last; application.conf stays as it is
FPM_POOL_ENVIRONMENT_FILE="/opt/docker/etc/php/fpm/pool.d/zz-environment.conf"

## Setup container uid
if [[ -n "$CONTAINER_UID" ]]; then
    echo "Setting php-fpm user to $CONTAINER_UID"
    if [[ ! -f "$FPM_POOL_ENVIRONMENT_FILE" ]]; then
        echo '[www]' > "$FPM_POOL_ENVIRONMENT_FILE"
    fi
    go-replace --mode=lineinfile --regex \
        -s '^user[\s]*='  -r "user = $CONTAINER_UID" \
        -s '^group[\s]*=' -r "group = $CONTAINER_UID" \
        -- "$FPM_POOL_ENVIRONMENT_FILE"
fi
