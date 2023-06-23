FROM ubuntu:22.04

RUN apt-get update && apt-get install -y \
    apt-transport-https \
    curl \
    locales \
    mysql-client \
    nginx \
    software-properties-common \
    unzip \
    vim

# System-level PHP setup
RUN add-apt-repository ppa:ondrej/php -y
RUN apt-get install -y php7.4 php7.4-curl php7.4-fpm php7.4-gd php7.4-mbstring php7.4-mysql php7.4-xml
RUN mkdir /run/php # For the php-fpm socket file
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# nginx setup
# note that these symlinks require the repo be mounted @ /var/www/openflights
RUN rm /etc/nginx/sites-enabled/default
RUN ln -s /var/www/openflights/nginx/openflights-dev /etc/nginx/sites-available/
RUN ln -s /var/www/openflights/nginx/openflights-dev /etc/nginx/sites-enabled/

CMD ["/bin/bash", "-c", "php-fpm7.4 -D; nginx -g 'daemon off;'"]
