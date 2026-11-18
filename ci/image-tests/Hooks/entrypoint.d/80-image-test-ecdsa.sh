# Image tests: with IMAGE_TEST_ECDSA_CERT set, replaces the certificate of the web server with a self-signed ECDSA
# certificate before the web server starts, see PiPageChecks
if [[ -n "${IMAGE_TEST_ECDSA_CERT:-}" ]]; then
    openssl req -x509 -newkey ec -pkeyopt ec_paramgen_curve:prime256v1 -nodes -subj /CN=localhost -days 2 \
        -keyout /tmp/image-test-ecdsa.key -out /tmp/image-test-ecdsa.crt 2>/dev/null
    for directory in /opt/docker/etc/nginx/ssl /opt/docker/etc/httpd/ssl; do
        if [[ -d "$directory" ]]; then
            cp /tmp/image-test-ecdsa.crt "$directory/server.crt"
            cp /tmp/image-test-ecdsa.key "$directory/server.key"
        fi
    done
fi
