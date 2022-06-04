#!/bin/sh

set -e

XDEBUG_INI_FILE="/usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini"
DISABLED_EXT="disabled"

if [ -f "${XDEBUG_INI_FILE}.${DISABLED_EXT}" ]; then
    mv "${XDEBUG_INI_FILE}.${DISABLED_EXT}" "${XDEBUG_INI_FILE}"
fi

if [ ! -f "${XDEBUG_INI_FILE}" ]; then
    docker-php-ext-enable xdebug
    sed -i '1 a xdebug.mode=debug' "${XDEBUG_INI_FILE}"
    sed -i '1 a xdebug.client_host=host.docker.internal' "${XDEBUG_INI_FILE}"
    sed -i '1 a xdebug.max_nesting_level=400' "${XDEBUG_INI_FILE}"
    sed -i '1 a xdebug.idekey=PHPSTORM' "${XDEBUG_INI_FILE}"
    sed -i '1 a xdebug.start_with_request=yes' "${XDEBUG_INI_FILE}"

fi

# set -e

# if [ "${HOST_IP}" = "" ]; then
#   HOST_IP=$(/sbin/ip route|awk '/default/ { print $3 }')
# fi

# if [ "${ENABLE_XDEBUG}" = "true" ]; then
#   docker-php-ext-enable xdebug
#   sed -i "1 a xdebug.client_host=${HOST_IP}" /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
#   sed -i '1 a xdebug.mode=debug' /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
#   sed -i '1 a xdebug.max_nesting_level=400' /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

#   sed -i "1 a max_execution_time=${TIMEOUT}" /usr/local/etc/php/conf.d/php-config.ini
#   sed -i "s/Timeout 300/Timeout ${TIMEOUT}/" /etc/apache2/apache2.conf
# fi
