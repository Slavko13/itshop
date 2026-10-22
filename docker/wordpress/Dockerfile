FROM wordpress:cli-php8.4 AS wpcli

FROM wordpress:php8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends msmtp msmtp-mta \
    && rm -rf /var/lib/apt/lists/*

COPY docker/wordpress/msmtprc /etc/msmtprc
COPY docker/wordpress/php-local.ini /usr/local/etc/php/conf.d/zz-itsoft-local.ini
COPY --from=wpcli /usr/local/bin/wp /usr/local/bin/wp

RUN chmod 0644 /etc/msmtprc
