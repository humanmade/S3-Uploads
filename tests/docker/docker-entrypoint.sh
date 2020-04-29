#!/bin/sh
mysqld_safe --datadir=/var/lib/mysql &>/dev/null  &
/usr/bin/minio server /data &>/dev/null &
sleep 1
/code/vendor/bin/phpunit "$@"
