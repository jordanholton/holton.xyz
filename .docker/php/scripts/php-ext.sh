set -e

# cap parallel compile jobs so low-memory builders don't OOM-kill cc1
PIE_MAKE_JOBS="${PIE_MAKE_JOBS:-2}"

echo "Installing mcrypt extension..."
pie install -j"${PIE_MAKE_JOBS}" pecl/mcrypt

# Relay (https://relay.so), the PhpRedis maintainers' next-generation Redis
# client - the app's only Redis extension (Laravel drives it through
# cachewerk/relay, see config/database.php). Run as a client only (free
# Community tier, CacheWerk's own EULA ships in the tarball):
# - relay.maxmemory=0 turns its in-process cache off
# - relay.default_pconnect=0 keeps one connection per request, like
#   phpredis - a persistent connection per php-fpm worker would sit idle
#   between requests
# Pinned build with checksums rather than builds.r2.relay.so/meta/latest,
# so a base rebuild never silently changes it. Relay publishes one musl
# build for Alpine 3.17+; it needs msgpack loaded first
# (docker-php-ext-*.ini files load alphabetically, so it is). igbinary is
# optional for Relay and has no PHP 8.5 release on PIE yet.
RELAY_VERSION="v0.60.0"
echo "Installing relay ${RELAY_VERSION}..."
apk add lz4-libs zstd-libs
pie install -j"${PIE_MAKE_JOBS}" msgpack/msgpack-php
case "$(uname -m)" in
    aarch64|arm64) RELAY_ARCH=aarch64; RELAY_SHA256=5470787d3e33796bfd9a7ebe4c59ea30c1f87ccaf77ade0573759d3e7e44937b ;;
    *) RELAY_ARCH=x86-64; RELAY_SHA256=3ea3edf18e77cd590a0d327ea3752e3bd3cfe319c2c4b6935638d74868c37cdc ;;
esac
RELAY_PHP="$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')"
RELAY_BUILD="relay-${RELAY_VERSION}-php${RELAY_PHP}-alpine3.17-${RELAY_ARCH}"
curl -fsSL "https://builds.r2.relay.so/${RELAY_VERSION}/${RELAY_BUILD}.tar.gz" -o /tmp/relay.tar.gz
echo "${RELAY_SHA256}  /tmp/relay.tar.gz" | sha256sum -c -
tar -xzf /tmp/relay.tar.gz -C /tmp
RELAY_EXT_DIR="$(php-config --extension-dir)"
cp "/tmp/${RELAY_BUILD}/relay-pkg.so" "${RELAY_EXT_DIR}/relay.so"
# Each install gets its own instance id (what install-php-extensions does too).
sed -i "s/00000000-0000-0000-0000-000000000000/$(cat /proc/sys/kernel/random/uuid)/" "${RELAY_EXT_DIR}/relay.so"
cat > /usr/local/etc/php/conf.d/docker-php-ext-relay.ini <<'RELAY_INI'
extension=relay.so
relay.maxmemory=0
relay.default_pconnect=0
relay.environment=production
RELAY_INI
rm -rf /tmp/relay.tar.gz "/tmp/${RELAY_BUILD}"
php -r 'exit(extension_loaded("relay") ? 0 : 1);' || { echo "ERROR: relay did not load"; exit 1; }

# Install the php extensions needed by the app - pcntl lets queue workers
# honor job timeouts and stop cleanly on SIGTERM when a pod is replaced.
echo "Installing core PHP extensions..."
docker-php-ext-install zip \
                       pdo_mysql \
                       pdo_pgsql \
                       ldap \
                       gd \
                       pcntl

echo "All PHP extensions installed successfully!"
