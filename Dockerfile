FROM php:8.2-apache

# Enable mod_rewrite and allow .htaccess overrides
RUN a2enmod rewrite \
 && sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

RUN docker-php-ext-install pdo pdo_mysql

# Image uploads are automatically optimized to the application's 5 MB limit.
RUN apt-get update && apt-get install -y --no-install-recommends \
    libmagickwand-dev libjpeg62-turbo-dev libpng-dev libwebp-dev libonig-dev libsqlite3-dev \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install gd exif mbstring pdo_sqlite \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Add this to your Dockerfile to install composer
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl libcurl4-openssl-dev \
    git \
    unzip \
    && docker-php-ext-install curl \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Allow original photos to reach the optimizer; saved images are capped at 5 MB.
# Videos retain their separate 50 MB application limit.
# (see uploadHeroMedia() in admin-functions.php). Any .ini file dropped into
# conf.d/ is auto-loaded by this base image.
COPY uploads.ini /usr/local/etc/php/conf.d/uploads.ini
