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
	composer

RUN apk add minio minio-client --repository=http://dl-cdn.alpinelinux.org/alpine/edge/testing

ENV MINIO_ACCESS_KEY AWSACCESSKEY
ENV MINIO_SECRET_KEY AWSSECRETKEY

ENV S3_UPLOADS_KEY AWSACCESSKEY
ENV S3_UPLOADS_SECRET AWSSECRETKEY
ENV S3_UPLOADS_REGION us-east-1
ENV S3_UPLOADS_BUCKET tests

RUN mysql_install_db --user=mysql --ldata=/var/lib/mysql
RUN sh -c 'mysqld_safe --datadir=/var/lib/mysql &' && sleep 2 && mysql -u root -e "CREATE DATABASE wordpress"

RUN sh -c '/usr/bin/minio server /data &' && sleep 4 \
	&& mcli config host add local http://172.17.0.2:9000 AWSACCESSKEY AWSSECRETKEY \
	&& mcli mb local/tests \
	&& mcli policy set public local/tests

VOLUME ["/code"]
WORKDIR /code
COPY ./docker-entrypoint.sh /entrypoint.sh
RUN chmod 755 /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]
