# abraflexi-contract-invoices

FROM php:8.2-cli
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions && install-php-extensions gettext intl zip
COPY src /usr/src/abraflexi-contract-invoices/src
RUN sed -i -e 's/..\/.env//' /usr/src/abraflexi-contract-invoices/src/*.php
COPY composer.json /usr/src/abraflexi-contract-invoices
WORKDIR /usr/src/abraflexi-contract-invoices
RUN curl -s https://getcomposer.org/installer | php
RUN ./composer.phar install -o -a
WORKDIR /usr/src/abraflexi-contract-invoices/src
CMD [ "php", "./GenerujFakturyZeSmluv.php" ]
