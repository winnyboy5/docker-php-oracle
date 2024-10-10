FROM php:7.4-apache

# Install dependencies
RUN apt-get update && apt-get install -y \
    libaio1 \
    wget \
    unzip \
    libcurl4-openssl-dev

# Download and install Oracle Instant Client and SDK
RUN mkdir /opt/oracle \
    && cd /opt/oracle \
    && wget https://download.oracle.com/otn_software/linux/instantclient/211000/instantclient-basic-linux.x64-21.1.0.0.0.zip \
    && wget https://download.oracle.com/otn_software/linux/instantclient/211000/instantclient-sdk-linux.x64-21.1.0.0.0.zip \
    && unzip instantclient-basic-linux.x64-21.1.0.0.0.zip \
    && unzip instantclient-sdk-linux.x64-21.1.0.0.0.zip \
    && rm instantclient-basic-linux.x64-21.1.0.0.0.zip \
    && rm instantclient-sdk-linux.x64-21.1.0.0.0.zip \
    && echo /opt/oracle/instantclient_21_1 > /etc/ld.so.conf.d/oracle-instantclient.conf \
    && ldconfig

# Set environment variables
ENV LD_LIBRARY_PATH /opt/oracle/instantclient_21_1:$LD_LIBRARY_PATH
ENV ORACLE_HOME /opt/oracle/instantclient_21_1
ENV PATH $PATH:/opt/oracle/instantclient_21_1

# Install and enable OCI8 and curl extensions
RUN docker-php-ext-configure oci8 --with-oci8=instantclient,/opt/oracle/instantclient_21_1 \
    && docker-php-ext-install oci8 \
    && docker-php-ext-install curl

# Copy PHP scripts and sample data
COPY index.php /var/www/html/
COPY api.php /var/www/html/
COPY sample_data.json /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html