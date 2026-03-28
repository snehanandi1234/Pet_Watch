FROM php:8.3-apache

RUN a2enmod rewrite \
    && docker-php-ext-install mbstring

COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

COPY . /var/www/html/

RUN rm -rf /var/www/html/docker

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R u+rwX,g+rX,o+rX /var/www/html

# SQLite must be writable by Apache
RUN if [ -f /var/www/html/petwatch.sqlite ]; then \
      chmod 664 /var/www/html/petwatch.sqlite && chown www-data:www-data /var/www/html/petwatch.sqlite; \
    else \
      touch /var/www/html/petwatch.sqlite && chown www-data:www-data /var/www/html/petwatch.sqlite && chmod 664 /var/www/html/petwatch.sqlite; \
    fi

EXPOSE 80
