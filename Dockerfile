FROM php:8.3-apache

# Chromium powers the headless PDF export; fonts keep the output readable.
# ca-certificates provides the TLS root bundle (used when DB_SSL_CA is set).
RUN apt-get update \
    && apt-get install -y --no-install-recommends chromium fonts-liberation ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# MySQL driver used by includes/functions.php + config/db.php
RUN docker-php-ext-install pdo_mysql

# Serve from the project root and allow the uploads .htaccess rules.
RUN a2enmod rewrite headers \
    && printf '<Directory /var/www/html>\n    AllowOverride All\n</Directory>\n' \
        > /etc/apache2/conf-available/zz-app.conf \
    && a2enconf zz-app

# Render injects the database credentials as environment variables, which
# get_env() reads directly - no config/env.php needed in the image.
ENV CHROME_PATH=/usr/bin/chromium

COPY . /var/www/html

RUN mkdir -p /var/www/html/assets/uploads \
    && chown -R www-data:www-data /var/www/html/assets/uploads

# Render assigns the listening port via $PORT; rewrite Apache to match.
COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

EXPOSE 80
CMD ["/usr/local/bin/start.sh"]
