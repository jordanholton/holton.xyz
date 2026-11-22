# get rid of the Artisan command that could expose secrets in a cached config
rm -f /var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/Console/ConfigCacheCommand.php

# start php
php-fpm --fpm-config /usr/local/etc/php-fpm.d/www.conf
