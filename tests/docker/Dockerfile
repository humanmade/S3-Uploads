FROM humanmade/plugin-tester:latest

RUN apk add minio minio-client --repository=http://dl-cdn.alpinelinux.org/alpine/edge/testing

ENV MINIO_ACCESS_KEY AWSACCESSKEY
ENV MINIO_SECRET_KEY AWSSECRETKEY

ENV S3_UPLOADS_KEY AWSACCESSKEY
ENV S3_UPLOADS_SECRET AWSSECRETKEY
ENV S3_UPLOADS_REGION us-east-1
ENV S3_UPLOADS_BUCKET tests

RUN sh -c '/usr/bin/minio server /data &' && sleep 4 \
	&& mcli config host add local http://172.17.0.2:9000 AWSACCESSKEY AWSSECRETKEY \
	&& mcli mb local/tests \
	&& mcli policy set public local/tests

COPY ./docker-entrypoint.sh /entrypoint.sh
RUN chmod 755 /entrypoint.sh
