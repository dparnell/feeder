FROM alpine:3.5
MAINTAINER Daniel Parnell <me@danielparnell.com>

RUN apk update && \
    apk add apache2 bash openrc php5-apache2 php5-json php5-sqlite3 && \
    rm -rf /var/cache/apk/*

RUN mkdir -p /run/apache2

RUN ln -sf /dev/stdout /var/log/apache2/access.log
RUN ln -sf /dev/stderr /var/log/apache2/error.log

WORKDIR /var/www/localhost/htdocs
VOLUME ["/var/www/localhost/htdocs"]
EXPOSE 80

CMD ["/usr/sbin/httpd", "-D", "FOREGROUND"]
