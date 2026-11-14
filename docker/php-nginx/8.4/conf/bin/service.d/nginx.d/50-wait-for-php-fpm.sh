# Wait until PHP-FPM in this container accepts connections, at most 30 s, before the web server starts: otherwise the
# first requests after the start get 502 (Nginx) or 503 (Apache) while PHP-FPM still starts
if [[ "$WEB_PHP_SOCKET" =~ ^(127\.0\.0\.1|localhost):([0-9]+)$ ]]; then
    PHP_FPM_PORT="${BASH_REMATCH[2]}"
    for attempt in {1..300}; do
        if (exec 3<>"/dev/tcp/127.0.0.1/${PHP_FPM_PORT}") 2>/dev/null; then
            break
        fi
        sleep 0.1
    done
fi
