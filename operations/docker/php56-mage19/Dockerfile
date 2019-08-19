# Dockerfile
FROM circleci/php:5.6.40-apache-node-browsers

USER root
ENV LANG=C.UTF-8

# Install Magento requirements
# from https://github.com/EmakinaFR/docker-magento/blob/1e54625fe98232502fbcd1ed9efd28031a40bd32/php/Dockerfile
# and https://github.com/alexcheng1982/docker-magento/blob/master/Dockerfile
RUN apt-get update -qq && \
    apt-get install -y -qq --no-install-recommends \
        autoconf \
        git \
        jq \
        libicu-dev \
        libfreetype6-dev \
        libjpeg-dev \
        libmcrypt-dev \
        libpng-dev \
        libssl-dev \
        libxml2-dev \
        libxml2-utils \
        mailutils \
        mysql-client \
        ssh-client \
        ssmtp
RUN docker-php-ext-install -j$(nproc) \
        bcmath \
        hash \
        intl \
        mcrypt \
        opcache \
        pdo_mysql \
        soap \
        zip
RUN docker-php-ext-configure gd --with-jpeg-dir=/usr/lib/ --with-freetype-dir=/usr/lib/ && \
    docker-php-ext-install gd
RUN perl -pi -e "s/mailhub=mail/mailhub=maildev/" /etc/ssmtp/ssmtp.conf

RUN a2enmod rewrite

# Download magento
USER circleci
WORKDIR /home/circleci
RUN curl -O https://files.magerun.net/n98-magerun.phar
RUN chmod +x n98-magerun.phar
RUN php -d memroy_limit=512M n98-magerun.phar install --magentoVersionByName=magento-mirror-1.9.3.6 \
  --installationFolder="/var/www/html" --only-download

# Download ngrok
RUN wget -O ngrok.zip https://bolt-devops.s3-us-west-2.amazonaws.com/testing/ngrok.zip
RUN unzip ngrok.zip

# Download magento sample data - magerun doesn't provide a way to download sample data only ( https://github.com/netz98/n98-magerun/issues/824 )
RUN wget -O sample-data.tar.bz2 https://bolt-devops.s3-us-west-2.amazonaws.com/testing/magento-sample-data-1.9.2.4-2016-10-11-06-57-39.tar.bz2
RUN tar xjf sample-data.tar.bz2
RUN cp -R magento-sample-data-1.9.2.4/media/* /var/www/html/media/.
RUN cp -R magento-sample-data-1.9.2.4/skin/* /var/www/html/skin/.

COPY init-bolt.php /var/www/html/.

# Clean up
RUN rm ngrok.zip
RUN rm sample-data.tar.bz2
RUN rm -r magento-sample-data-1.9.2.4/media
RUN rm -r magento-sample-data-1.9.2.4/skin
