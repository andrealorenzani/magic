# Local run only: PHP + Apache with the MySQL PDO driver. The project is bind-mounted, not copied.
# Like on shared hosting, the web directory is the project root and the root .htaccess serves public/.
FROM php:8.3-apache
RUN docker-php-ext-install pdo_mysql \
 && a2enmod rewrite headers expires \
 && printf '<Directory /var/www/html/>\n  AllowOverride All\n  Require all granted\n</Directory>\nServerName localhost\n' > /etc/apache2/conf-available/magic.conf \
 && a2enconf magic
