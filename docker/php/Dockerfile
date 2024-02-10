FROM php:8.1-fpm-buster

RUN apt-get update && apt-get install -y apt-utils ldap-utils zip unzip zlib1g-dev libzip-dev libldb-dev libldap2-dev libfreetype6-dev libjpeg62-turbo-dev libpng-dev && \
                          rm -rf /var/lib/apt/lists/* && \
                          docker-php-ext-configure ldap && \
                          docker-php-ext-install ldap

RUN docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/
RUN docker-php-ext-configure zip
RUN docker-php-ext-install zip pdo_mysql gd

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer

RUN pecl install xdebug-2.8.1

RUN docker-php-ext-enable xdebug

COPY ./config/xdebug.ini .
RUN cat xdebug.ini >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && rm xdebug.ini

COPY ./config/php.ini /usr/local/etc/php/

WORKDIR /var/www
