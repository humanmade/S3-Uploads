set -e

S3_CONTAINER=s3-uploads-tests-s3

cleanup() {
	docker rm -f "$S3_CONTAINER" > /dev/null 2>&1 || true
}
trap cleanup EXIT

docker run --rm --name "$S3_CONTAINER" -d -p 9000:9000 \
	-e MINIO_ROOT_USER=AWSACCESSKEY \
	-e MINIO_ROOT_PASSWORD=AWSSECRETKEY \
	humanmade/altis-local-s3:RELEASE.2021-09-18T18-09-59Z server /data > /dev/null

# Wait for the S3 service to be ready, then create the 'tests' bucket.
echo "Waiting for the S3 service to start..."
ready=false
for i in $(seq 1 30); do
	if docker run --rm --link "$S3_CONTAINER":s3 --entrypoint=/bin/sh \
		humanmade/altis-local-s3-client:RELEASE.2021-09-02T09-21-27Z \
		-c "mc alias set local http://s3:9000 AWSACCESSKEY AWSSECRETKEY && mc mb --ignore-existing local/tests" > /dev/null 2>&1; then
		echo "S3 service ready, 'tests' bucket created."
		ready=true
		break
	fi
	sleep 1
done

if [ "$ready" != true ]; then
	echo "S3 service failed to start." >&2
	exit 1
fi

docker run --rm -e AWS_SUPPRESS_PHP_DEPRECATION_WARNING=1 -e S3_UPLOADS_BUCKET=tests -e S3_UPLOADS_KEY=AWSACCESSKEY -e S3_UPLOADS_SECRET=AWSSECRETKEY -e S3_UPLOADS_REGION=us-east-1 --link "$S3_CONTAINER":s3 -v $PWD:/code --entrypoint=/bin/sh humanmade/plugin-tester:wp-6.8-php8.3 -c "apk add --no-cache libjpeg-turbo ghostscript imagemagick-pdf > /dev/null 2>&1 && /entrypoint.sh $*"

echo "Running Psalm..."
docker run --rm -v $PWD:/code --entrypoint=/bin/sh humanmade/plugin-tester:wp-6.8-php8.3 -c "apk add --no-cache --upgrade php83 php83-common php83-dom php83-phar php83-simplexml php83-tokenizer php83-xml > /dev/null 2>&1 && /code/vendor/bin/psalm"
