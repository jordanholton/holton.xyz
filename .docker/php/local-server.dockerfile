FROM php:8.5.13-fpm-alpine3.24

# Local, self-contained image that runs like the deployed one (code baked in,
# no Xdebug). Used by .docker/docker-compose-server.yml; build assets and
# vendor/ on the host first (npm run build, composer install).

# copy the setup scripts
WORKDIR /tmp
COPY --chmod=755 .docker/php/scripts .tmp
RUN rm -rf /home/www-data/startups && mv /tmp/.tmp/startups/ /home/www-data/

# setup the os and php environments and clean up the setup scripts
RUN /tmp/.tmp/php-apk.sh \
  && /tmp/.tmp/php-pie.sh \
  && /tmp/.tmp/php-ext.sh \
  && /tmp/.tmp/php-conf.sh \
  && /tmp/.tmp/php-fpm-conf.sh \
  && /tmp/.tmp/php-fpm-opcache.sh \
  && /tmp/.tmp/php-composer.sh \
  && /tmp/.tmp/php-ldaps.sh \
  && rm -rf /tmp/.tmp/

# set permissions on the php folders to make sure the operating user can get to all the places
RUN chown -R www-data:www-data /usr/local/etc/php-fpm.d/* && \
    chown -R www-data:www-data /usr/local/etc/php/* && \
    chown -R www-data:www-data /home/www-data/* && \
    touch /usr/local/var/log/php-fpm.log && \
    chown www-data:www-data /usr/local/var/log/php-fpm.log

# set the operating user for php
USER www-data:www-data

#  switch to the folder with the code and copy the specific files
WORKDIR /var/www/html
COPY --chown=www-data:www-data composer.json composer.json
COPY --chown=www-data:www-data composer.lock composer.lock
COPY --chown=www-data:www-data vite.config.ts vite.config.ts
COPY --chown=www-data:www-data package.json package.json
COPY --chown=www-data:www-data phpunit.xml phpunit.xml
COPY --chown=www-data:www-data artisan artisan
COPY --chown=www-data:www-data bootstrap/app.php bootstrap/app.php
COPY --chown=www-data:www-data bootstrap/providers.php bootstrap/providers.php

# copy the folders
COPY --chown=www-data:www-data public public
COPY --chown=www-data:www-data app app
COPY --chown=www-data:www-data config config
COPY --chown=www-data:www-data database database
COPY --chown=www-data:www-data resources resources
COPY --chown=www-data:www-data routes routes
COPY --chown=www-data:www-data storage storage
COPY --chown=www-data:www-data tests tests
COPY --chown=www-data:www-data vendor vendor

# php-composer.sh seeded .env from .env.initial; give it a fresh key and a database
RUN mkdir -p bootstrap/cache && \
    php artisan key:generate --force && \
    touch database/database.sqlite && \
    php artisan migrate --force

CMD ["php-fpm"]
