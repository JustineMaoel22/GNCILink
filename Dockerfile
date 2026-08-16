FROM php:8.2-apache
RUN docker-php-ext-install pdo pdo_mysql

# Add this to your Dockerfile to install composer
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    git \
    unzip \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Raise PHP's upload limits to match the app's 10MB image / 50MB video caps
# (see uploadHeroMedia() in admin-functions.php). Any .ini file dropped into
# conf.d/ is auto-loaded by this base image.
COPY uploads.ini /usr/local/etc/php/conf.d/uploads.ini