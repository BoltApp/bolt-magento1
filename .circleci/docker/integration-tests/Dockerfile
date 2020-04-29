# Dockerfile
FROM circleci/php:5.6.40-apache-node-browsers

USER root
ENV LANG=C.UTF-8

# Install MySQL
# Based off of https://github.com/docker-library/mysql/blob/master/5.7/Dockerfile
RUN apt-get update && apt-get install -y --no-install-recommends \
    apt-utils \
		pwgen \
		openssl \
		perl \
	&& rm -rf /var/lib/apt/lists/*

RUN mkdir -p ~/.gnupg
RUN echo "disable-ipv6" >> ~/.gnupg/dirmngr.conf

RUN set -ex; \
# Not the most reliable may need to run a couple times https://github.com/docker-library/mysql/issues/530
# gpg: key 5072E1F5: public key "MySQL Release Engineering <mysql-build@oss.oracle.com>" imported
	key='A4A9406876FCBD3C456770C88C718D3B5072E1F5'; \
	export GNUPGHOME="$(mktemp -d)"; \
	gpg --batch --keyserver ha.pool.sks-keyservers.net --recv-keys "$key"; \
	gpg --batch --export "$key" > /etc/apt/trusted.gpg.d/mysql.gpg; \
	gpgconf --kill all; \
	rm -rf "$GNUPGHOME"; \
	apt-key list > /dev/null

ENV MYSQL_MAJOR 5.7

RUN echo "deb http://repo.mysql.com/apt/debian/ stretch mysql-${MYSQL_MAJOR}" > /etc/apt/sources.list.d/mysql.list

# the "/var/lib/mysql" stuff here is because the mysql-server postinst doesn't have an explicit way to disable the mysql_install_db codepath besides having a database already "configured" (ie, stuff in /var/lib/mysql/mysql)
# also, we set debconf keys to make APT a little quieter
RUN { \
		echo mysql-community-server mysql-community-server/data-dir select ''; \
		echo mysql-community-server mysql-community-server/root-pass password ''; \
		echo mysql-community-server mysql-community-server/re-root-pass password ''; \
		echo mysql-community-server mysql-community-server/remove-test-db select false; \
	} | debconf-set-selections \
	&& apt-get update && apt-get install -y mysql-server && rm -rf /var/lib/apt/lists/* \
	&& rm -rf /var/lib/mysql && mkdir -p /var/lib/mysql /var/run/mysqld \
	&& chown -R mysql:mysql /var/lib/mysql /var/run/mysqld \
# ensure that /var/run/mysqld (used for socket and lock files) is writable regardless of the UID our mysqld instance ends up having at runtime
	&& chmod 777 /var/run/mysqld \
# comment out a few problematic configuration values
	&& find /etc/mysql/ -name '*.cnf' -print0 \
		| xargs -0 grep -lZE '^(bind-address|log)' \
		| xargs -rt -0 sed -Ei 's/^(bind-address|log)/#&/' \
# don't reverse lookup hostnames, they are usually another container
	&& echo '[mysqld]\nskip-host-cache\nskip-name-resolve' > /etc/mysql/conf.d/docker.cnf

ENV MYSQL_ALLOW_EMPTY_PASSWORD=true \
    MYSQL_DATABASE=circle_test \
    MYSQL_HOST=127.0.0.1 \
    MYSQL_ROOT_HOST=% \
    MYSQL_USER=root

RUN echo '\n\
[mysqld]\n\
collation-server = utf8_unicode_ci\n\
init-connect="SET NAMES utf8"\n\
character-set-server = utf8\n\
innodb_flush_log_at_trx_commit=2\n\
sync_binlog=0\n\
innodb_use_native_aio=0\n' >> /etc/mysql/my.cnf

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

# Update chrome to the latest
USER root
RUN apt-get update && apt-get install -y lsb-release libappindicator3-1
RUN curl -L -o google-chrome.deb https://dl.google.com/linux/direct/google-chrome-stable_current_amd64.deb
RUN dpkg -i google-chrome.deb
RUN sed -i 's|HERE/chrome"|HERE/chrome" --no-sandbox|g' /opt/google/chrome/google-chrome
RUN rm google-chrome.deb

# Update git
RUN apt-get upgrade -y git

# Clean up
RUN rm ngrok.zip
RUN rm sample-data.tar.bz2
RUN rm -r magento-sample-data-1.9.2.4/media
RUN rm -r magento-sample-data-1.9.2.4/skin

USER circleci
WORKDIR /home/circleci
