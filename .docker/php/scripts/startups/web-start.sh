# get rid of the Artisan command that could expose secrets in a cached config
rm -f /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Console/ConfigCacheCommand.php

# start php - the default config loads php-fpm.d/www.conf plus the image's
# zz-docker.conf (listen = 9000, daemonize = no); www.conf alone has no listen address
php-fpm
