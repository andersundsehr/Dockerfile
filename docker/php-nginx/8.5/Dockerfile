#+++++++++++++++++++++++++++++++++++++++
# Dockerfile for webdevops/php-nginx:8.5
#    -- automatically generated  --
#+++++++++++++++++++++++++++++++++++++++

FROM webdevops/php:8.5

ENV WEB_DOCUMENT_ROOT=/app \
    WEB_DOCUMENT_INDEX=index.php \
    WEB_ALIAS_DOMAIN=*.vm \
    WEB_PHP_TIMEOUT=600 \
    WEB_PHP_SOCKET=""
ENV WEB_PHP_SOCKET=127.0.0.1:9000
ENV SERVICE_NGINX_CLIENT_MAX_BODY_SIZE="50m"

COPY conf/ /opt/docker/

RUN set -x \
    # Install nginx
    # the key in a keyring of its own, which only this repository uses, instead of the deprecated trusted.gpg
    && curl -fsSL https://nginx.org/keys/nginx_signing.key | gpg --dearmor -o /usr/share/keyrings/nginx-archive-keyring.gpg \
    && echo "deb [signed-by=/usr/share/keyrings/nginx-archive-keyring.gpg] http://nginx.org/packages/debian/ $(docker-image-info dist-codename) nginx" \
        > /etc/apt/sources.list.d/nginx.list \
    && apt-install \
    nginx \
    && docker-run-bootstrap \
    && docker-image-cleanup

EXPOSE 80 443
