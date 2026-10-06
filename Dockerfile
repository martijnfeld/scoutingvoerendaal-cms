# Ontwikkel-image voor lokaal testen (zie docker-compose.yml).
# Niet bedoeld om zo op shared hosting te draaien — daar upload je de
# bestanden gewoon rechtstreeks, zie INSTALL.md.
FROM php:8.2-apache

RUN apt-get update && apt-get install -y --no-install-recommends libzip-dev $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-configure zip \
    && docker-php-ext-install pdo pdo_mysql zip \
    && pecl install xdebug \
    && a2enmod rewrite headers access_compat

COPY docker/apache-htaccess.conf /etc/apache2/conf-available/z-htaccess.conf
RUN a2enconf z-htaccess

# Xdebug: alleen in dit dev-image, nooit op shared hosting. Verbindt naar de
# host op poort 9003 zodra iets daar luistert (bv. VS Code "Listen for Xdebug");
# zonder listener faalt die verbinding vrijwel meteen, dus geen merkbare vertraging.
COPY docker/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini
