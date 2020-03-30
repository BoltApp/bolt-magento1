FROM debian:jessie

USER root
RUN apt-get update && \
    apt-get install -y locales apache2 php5 php5-cli libapache2-mod-php5 php5-gd php5-json php5-ldap php5-mysql php5-pgsql && \
    apt-get install -y git openssh-server
RUN echo "deb [check-valid-until=no] http://archive.debian.org/debian jessie-backports main" > /etc/apt/sources.list.d/jessie-backports.list && \
    sed -i '/deb http:\/\/deb.debian.org\/debian jessie-updates main/d' /etc/apt/sources.list
RUN apt-get -o Acquire::Check-Valid-Until=false update && \
    apt-get -y install curl php5-curl mysql-client php5-mcrypt php5-xdebug && \
    php5enmod mcrypt