# FROM php:8.4-apache AS busyboxbuilder
# RUN cd / \
#     && apt-get update -y \
#     && apt-get install -y build-essential curl libntirpc-dev  \
#     && curl -L https://busybox.net/downloads/busybox-1.37.0.tar.bz2 | tar -xjv \
#     && cd /busybox-1.37.0/
# COPY busybox.config /busybox-1.37.0/.config
# RUN cd /busybox-1.37.0/ && make install
FROM  ismogroup/busybox:1.37.0-php-8.4-apache AS busyboxbuilder

FROM php:8.4-apache AS builder
ARG TARGETARCH
LABEL maintainer="Ronan <ronan.le_meillat@ismo-group.co.uk>"
RUN echo "Run for $TARGETARCH" && \
    if [[ "$TARGETARCH" == "amd64" ]] ; then \
        curl -fLSs https://repo.mysql.com/mysql-apt-config_0.8.33-1_all.deb > /tmp/mysql-apt-config_0.8.33-1_all.deb && \
        DEBIAN_FRONTEND=noninteractive dpkg -i /tmp/mysql-apt-config_0.8.33-1_all.deb && \
        apt-get update -y &&\
        apt-get install -y --no-install-recommends mysql-client lsb-release wget gnupg ; \
    else \
        apt-get update -y &&\
        apt-get install -y --no-install-recommends default-mysql-client ; \
    fi

RUN apt-get update -y \
    && apt-get dist-upgrade -y \
    && apt-get install -y --no-install-recommends \
        git \
        libc-client-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libkrb5-dev \
        libldap2-dev \
        libpng-dev \
        libpq-dev \
        libxml2-dev \
        libzip-dev \
        libbz2-dev \
        libmemcached-dev \
        cron 

RUN docker-php-ext-install opcache
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) calendar intl mysqli pdo_mysql gd soap zip
RUN  docker-php-ext-configure ldap --with-libdir=lib/$(gcc -dumpmachine)/ \
    && docker-php-ext-install -j$(nproc) ldap 
# RUN  docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
#     && docker-php-ext-install imap 
RUN  docker-php-ext-configure bz2 \
    && docker-php-ext-install bz2
RUN mkdir -p /usr/src/php/ext/memcached && \
    git clone https://github.com/php-memcached-dev/php-memcached /usr/src/php/ext/memcached && \
    docker-php-ext-configure /usr/src/php/ext/memcached --disable-memcached-sasl \
    && docker-php-ext-install /usr/src/php/ext/memcached \
    && rm -rf /usr/src/php/ext/memcached \
    && mv ${PHP_INI_DIR}/php.ini-production ${PHP_INI_DIR}/php.ini \
    && rm -rf /var/lib/apt/lists/*
RUN cd / && apt-get update -y &&\
    apt-get install -y --no-install-recommends p7zip-full &&\
    git clone https://github.com/highcanfly-club/DoliMods.git && \
    cd /DoliMods/dev/build && rm -f makepack-HelloAsso.conf && echo "all" | perl makepack-dolibarrmodule.pl && \
    mkdir -p /custom && for ZIP in *.zip; do 7z x -y -o/custom $ZIP; done
RUN apt-get update -y &&\
    apt-get install -y --no-install-recommends curl git libsodium-dev p7zip-full &&\
    curl -LsS https://github.com/phpstan/phpstan/releases/download/2.1.11/phpstan.phar -o /usr/local/bin/phpstan.phar &&\
    chmod +x /usr/local/bin/phpstan.phar &&\
    curl -LsS https://github.com/humbug/php-scoper/releases/download/0.18.16/php-scoper.phar -o /usr/local/bin/php-scoper.phar &&\
    chmod +x /usr/local/bin/php-scoper.phar &&\
    git clone https://inligit.fr/cap-rel/dolibarr/plugin-facturx.git &&\
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" &&\
    php composer-setup.php &&\
    php -r "unlink('composer-setup.php');" &&\
    mv composer.phar //usr/local/bin/composer.phar &&\
    cd plugin-facturx &&\
    composer.phar install &&\
    php build/buildzip.php &&\
    cp /tmp/module_facturx-*.zip . &&\
    mkdir -p /custom/htdocs && for ZIP in *.zip; do 7z x -y -o/custom/htdocs $ZIP; done


# Get Dolibarr
FROM php:8.4-apache
LABEL maintainer="Ronan <ronan.le_meillat@ismo-group.co.uk>"
COPY --from=builder /usr/local/etc/php/conf.d /usr/local/etc/php/conf.d/
COPY --from=builder /usr/local/lib/php/extensions /usr/local/lib/php/extensions/
COPY --from=busyboxbuilder /busybox-1.37.0/_install/bin/busybox /bin/busybox
ENV DOLI_VERSION 21.0.1
ENV DOLI_INSTALL_AUTO 1

ENV DOLI_DB_TYPE mysqli
ENV DOLI_DB_HOST mysql
ENV DOLI_DB_HOST_PORT 3306

ENV DOLI_URL_ROOT 'http://localhost'
ENV DOLI_NOCSRFCHECK 0

ENV DOLI_AUTH dolibarr
ENV DOLI_LDAP_HOST 127.0.0.1
ENV DOLI_LDAP_PORT 389
ENV DOLI_LDAP_VERSION 3
ENV DOLI_LDAP_SERVER_TYPE openldap
ENV DOLI_LDAP_LOGIN_ATTRIBUTE uid
ENV DOLI_LDAP_DN 'ou=users,dc=my-domain,dc=com'
ENV DOLI_LDAP_FILTER ''
ENV DOLI_LDAP_BIND_DN ''
ENV DOLI_LDAP_BIND_PASS ''
ENV DOLI_LDAP_DEBUG false

ENV DOLI_CRON 0

ENV WWW_USER_ID 33
ENV WWW_GROUP_ID 33

ENV PHP_INI_DATE_TIMEZONE 'UTC'
ENV PHP_INI_MEMORY_LIMIT 256M

RUN echo "Run for $TARGETARCH" && \
    if [[ "$TARGETARCH" == "amd64" ]] ; then \
        apt-get update -y \
        && apt-get dist-upgrade -y \
        && apt-get install -y --no-install-recommends && \
        curl -fLSs https://repo.mysql.com/mysql-apt-config_0.8.33-1_all.deb > /tmp/mysql-apt-config_0.8.33-1_all.deb && \
        DEBIAN_FRONTEND=noninteractive dpkg -i /tmp/mysql-apt-config_0.8.33-1_all.deb && \
        apt-get update -y &&\
        apt-get install -y --no-install-recommends mysql-client lsb-release wget gnupg xz-utils ; \
    else \
        apt-get update -y &&\
        apt-get install -y --no-install-recommends default-mysql-client xz-utils ; \
    fi
RUN apt-get update -y \
    && apt-get dist-upgrade -y \
    && apt-get install -y --no-install-recommends \
        curl cron libzip4 libc-client2007e postgresql-client libpng16-16 \
        libjpeg62-turbo libfreetype6 vim libmemcached11
COPY docker-run.sh /usr/local/bin/
COPY autobackup /usr/local/bin/
COPY --chmod=0755 upgrade-helper.sh /upgrade-helper.sh
RUN mkdir -p /var/www/dolidock/html/custom && \
    # curl -fLSs https://github.com/Dolibarr/dolibarr/archive/${DOLI_VERSION}.tar.gz |\
    curl -fLSs https://sourceforge.net/projects/dolibarr/files/Dolibarr%20ERP-CRM/${DOLI_VERSION}/dolibarr-${DOLI_VERSION}.tgz/download  |\
    tar -C /tmp -xz && \
    cp -r /tmp/dolibarr-${DOLI_VERSION}/htdocs/* /var/www/dolidock/html/ && \
    cp -r /tmp/dolibarr-${DOLI_VERSION}/scripts /var/www/ && \
    rm -rf /tmp/* && \
    chown -R www-data:www-data /var/www && \
    chmod ugo+x /usr/local/bin/docker-run.sh && \
    chmod ugo+x /usr/local/bin/autobackup && \
    ln -svf /bin/busybox /usr/sbin/sendmail
RUN a2dissite 000-default &&\
    echo "<VirtualHost *:80>" >> /etc/apache2/sites-available/dolibarr.conf &&\
    echo "ServerAdmin webmaster@localhost" >> /etc/apache2/sites-available/dolibarr.conf &&\
    echo "DocumentRoot /var/www/dolidock/html" >> /etc/apache2/sites-available/dolibarr.conf &&\
    echo "ErrorLog ${APACHE_LOG_DIR}/error.log" >> /etc/apache2/sites-available/dolibarr.conf &&\
    echo "CustomLog ${APACHE_LOG_DIR}/access.log combined" >> /etc/apache2/sites-available/dolibarr.conf &&\
    echo "php_value error_reporting 0" >> /etc/apache2/sites-available/dolibarr.conf &&\
    echo "php_value session.save_path /var/www/dolidock/documents/sessions" >> /etc/apache2/sites-available/dolibarr.conf &&\
    echo "</VirtualHost>" >> /etc/apache2/sites-available/dolibarr.conf &&\
    a2ensite dolibarr
#COPY patchs/fileconf-enable-dot-in-db-name.diff /var/www/dolidock/
COPY patchs/bug-mod-user-unavailable.diff /var/www/dolidock/
COPY patchs/pgsql-enable-ssl.diff /var/www/dolidock/
#COPY patchs/bug-fk-soc-tier.diff /var/www/dolidock/
COPY patchs/bug-margin-pdf.diff /var/www/dolidock/
COPY patchs/bug-saphir.diff /var/www/dolidock/
RUN cd /var/www/dolidock/ &&\
    #patch --fuzz=12 -p0 < fileconf-enable-dot-in-db-name.diff &&\
    patch --fuzz=12 -p0 < bug-mod-user-unavailable.diff &&\
    patch --fuzz=12 -p0 < pgsql-enable-ssl.diff &&\
    #patch --fuzz=12 -p0 < bug-fk-soc-tier.diff &&\
    #patch --fuzz=12 -p0 < bug-margin-pdf.diff &&\
    rm -f *.diff
COPY --from=builder /custom/htdocs /var/www/dolidock/html/custom/
RUN curl -L https://dl.min.io/client/mc/release/linux-$(dpkg --print-architecture)/mc > /usr/local/bin/mc && chmod +x /usr/local/bin/mc
COPY --chmod=0755 scripts/initfrom-s3.sh /usr/local/bin/initfrom-s3
COPY --chmod=0755 migrate2.sh /usr/local/bin/migrate2
RUN echo ". /usr/local/bin/migrate2" >> /root/.bashrc &&\
    chmod +x /root/.bashrc
EXPOSE 80
VOLUME /var/www/dolidock/documents
WORKDIR /var/www/dolidock

ENTRYPOINT ["/usr/local/bin/docker-run.sh"]

CMD ["apache2-foreground"]