FROM alpine:3.11

RUN apk add -u --no-cache \
	php7 \
	php7-pecl-imagick \
	php7-dom \
	php7-mysqli \
	php7-xml \
	php7-exif \
	php7-simplexml \
	mysql \
	mysql-client \
	imagemagick \
	composer \
	subversion

RUN apk add minio minio-client --repository=http://dl-cdn.alpinelinux.org/alpine/edge/testing

ENV MINIO_ACCESS_KEY AWSACCESSKEY
ENV MINIO_SECRET_KEY AWSSECRETKEY

ENV S3_UPLOADS_KEY AWSACCESSKEY
ENV S3_UPLOADS_SECRET AWSSECRETKEY
ENV S3_UPLOADS_REGION us-east-1
ENV S3_UPLOADS_BUCKET tests

RUN wget -nv -O /tmp/wordpress.tar.gz https://wordpress.org/wordpress-5.4.tar.gz \
	&& mkdir /wordpress \
	&& tar --strip-components=1 -zxmf /tmp/wordpress.tar.gz -C /wordpress \
	&& rm /tmp/wordpress.tar.gz

RUN mkdir /wordpress-testing-framework && cd /wordpress-testing-framework \
	&& svn co --quiet https://develop.svn.wordpress.org/trunk/tests/phpunit/includes/ \
	&& wget -nv -O wp-tests-config.php https://develop.svn.wordpress.org/trunk/wp-tests-config-sample.php \
	&& sed -i "s:dirname( __FILE__ ) . '/src/':'/wordpress/':" wp-tests-config.php \
	&& sed -i "s/youremptytestdbnamehere/wordpress/" wp-tests-config.php \
	&& sed -i "s/yourusernamehere/root/" wp-tests-config.php \
	&& sed -i "s/yourpasswordhere//" wp-tests-config.php

RUN mysql_install_db --user=mysql --ldata=/var/lib/mysql
RUN sh -c 'mysqld_safe --datadir=/var/lib/mysql &' && sleep 2 && mysql -u root -e "CREATE DATABASE wordpress"

RUN sh -c '/usr/bin/minio server /data &' && sleep 4 \
	&& mcli config host add local http://172.17.0.2:9000 AWSACCESSKEY AWSSECRETKEY \
	&& mcli mb local/tests \
	&& mcli policy set public local/tests

ENV WP_DEVELOP_DIR=/wordpress-testing-framework/

VOLUME ["/code"]
WORKDIR /code
COPY ./docker-entrypoint.sh /entrypoint.sh
RUN chmod 755 /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]
