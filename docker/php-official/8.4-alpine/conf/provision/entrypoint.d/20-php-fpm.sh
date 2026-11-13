#######################################
### FPM MAIN
#######################################

container-file-auto-restore "/opt/docker/etc/php/fpm/php-fpm.conf"

echo '' >> /opt/docker/etc/php/fpm/php-fpm.conf
echo '; container env settings' >> /opt/docker/etc/php/fpm/php-fpm.conf
echo '[global]' >> /opt/docker/etc/php/fpm/php-fpm.conf

if [[ -n "${FPM_PROCESS_MAX+x}" ]]; then
    echo "process.max = ${FPM_PROCESS_MAX}" >> /opt/docker/etc/php/fpm/php-fpm.conf
fi

# General fpm main setting
for ENV_VAR in $(envListVars "fpm\.global\."); do
    env_key=${ENV_VAR#fpm.global.}
    env_val=$(envGetValue "$ENV_VAR")

    echo "$env_key = ${env_val}" >> /opt/docker/etc/php/fpm/php-fpm.conf
done

#######################################
### FPM POOL
#######################################

# The values from the environment go to a pool file of their own, written anew at every start. PHP-FPM reads it after
# application.conf and after docker.conf of the official PHP image, so they win over both.
FPM_POOL_ENVIRONMENT_FILE=/opt/docker/etc/php/fpm/pool.d/zz-environment.conf
echo '; container env settings, written at every start' > "$FPM_POOL_ENVIRONMENT_FILE"
echo '[www]' >> "$FPM_POOL_ENVIRONMENT_FILE"

# General fpm pool setting
for ENV_VAR in $(envListVars "fpm\.pool\."); do
    env_key=${ENV_VAR#fpm.pool.}
    env_val=$(envGetValue "$ENV_VAR")

    echo "$env_key = ${env_val}" >> "$FPM_POOL_ENVIRONMENT_FILE"
done


if [[ -n "${FPM_PM_MAX_CHILDREN+x}" ]]; then
    echo "pm.max_children = ${FPM_PM_MAX_CHILDREN}" >> "$FPM_POOL_ENVIRONMENT_FILE"
fi

if [[ -n "${FPM_PM_START_SERVERS+x}" ]]; then
    echo "pm.start_servers = ${FPM_PM_START_SERVERS}" >> "$FPM_POOL_ENVIRONMENT_FILE"
fi

if [[ -n "${FPM_PM_MIN_SPARE_SERVERS+x}" ]]; then
    echo "pm.min_spare_servers = ${FPM_PM_MIN_SPARE_SERVERS}" >> "$FPM_POOL_ENVIRONMENT_FILE"
fi

if [[ -n "${FPM_PM_MAX_SPARE_SERVERS+x}" ]]; then
    echo "pm.max_spare_servers = ${FPM_PM_MAX_SPARE_SERVERS}" >> "$FPM_POOL_ENVIRONMENT_FILE"
fi

if [[ -n "${FPM_PROCESS_IDLE_TIMEOUT+x}" ]]; then
    echo "pm.process_idle_timeout = ${FPM_PROCESS_IDLE_TIMEOUT}" >> "$FPM_POOL_ENVIRONMENT_FILE"
fi

if [[ -n "${FPM_MAX_REQUESTS+x}" ]]; then
    echo "pm.max_requests = ${FPM_MAX_REQUESTS}" >> "$FPM_POOL_ENVIRONMENT_FILE"
fi

if [[ -n "${FPM_REQUEST_TERMINATE_TIMEOUT+x}" ]]; then
    echo "request_terminate_timeout = ${FPM_REQUEST_TERMINATE_TIMEOUT}" >> "$FPM_POOL_ENVIRONMENT_FILE"
fi

if [[ -n "${FPM_RLIMIT_FILES+x}" ]]; then
    echo "rlimit_files = ${FPM_RLIMIT_FILES}" >> "$FPM_POOL_ENVIRONMENT_FILE"
fi

if [[ -n "${FPM_RLIMIT_CORE+x}" ]]; then
    echo "rlimit_core = ${FPM_RLIMIT_CORE}" >> "$FPM_POOL_ENVIRONMENT_FILE"
fi

# Workaround for official PHP images
if [[ -n "${PHP_SENDMAIL_PATH+x}" ]]; then
    echo "php_admin_value[sendmail_path] = ${PHP_SENDMAIL_PATH}" >> "$FPM_POOL_ENVIRONMENT_FILE"
fi
