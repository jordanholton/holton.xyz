FROM php:8.5.13-fpm-alpine3.24

# Local development image: the app is bind-mounted at /var/www/html by
# .docker/docker-compose.yml, so only the runtime (extensions, Relay, Xdebug) is baked in.

WORKDIR /tmp
COPY --chmod=755 .docker/php/scripts .tmp

RUN /tmp/.tmp/php-apk.sh \
  && /tmp/.tmp/php-pie.sh \
  && /tmp/.tmp/php-ext.sh \
  && /tmp/.tmp/php-xdebug.sh \
  && /tmp/.tmp/php-conf.sh \
  && /tmp/.tmp/php-fpm-conf.sh \
  && /tmp/.tmp/php-ldaps.sh \
  && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
  && echo 'ls -lah --color=always "$@"' > /usr/bin/ll \
  && chmod 755 /usr/bin/ll \
  && rm -rf /tmp/.tmp/

WORKDIR /var/www/html
CMD ["php-fpm"]
