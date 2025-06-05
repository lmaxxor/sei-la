FROM php:8.1-apache

WORKDIR /var/www/html

COPY . .

RUN apt-get update && \
    apt-get install -y libzip-dev unzip && \
    docker-php-ext-install mysqli pdo pdo_mysql && \
    docker-php-ext-enable mysqli && \
    a2enmod rewrite

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && \
    composer install --working-dir=payments --no-interaction --no-dev

EXPOSE 80

CMD ["apache2-foreground"]
