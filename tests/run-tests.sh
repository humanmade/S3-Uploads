set -e

if [ -d "/tmp/s3-uploads-tests" ]; then
	rm -rf /tmp/s3-uploads-tests/*
else
	mkdir /tmp/s3-uploads-tests
fi

mkdir /tmp/s3-uploads-tests/tests

docker run --rm --name s3-uploads-tests-minio -d --rm -p 9000:9000 -e MINIO_ACCESS_KEY=AWSACCESSKEY -e MINIO_SECRET_KEY=AWSSECRETKEY -v /tmp/s3-uploads-tests:/data minio/minio server /data > /dev/null

docker run --rm -e S3_UPLOADS_BUCKET=tests -e S3_UPLOADS_KEY=AWSACCESSKEY -e S3_UPLOADS_SECRET=AWSSECRETKEY -e S3_UPLOADS_REGION=us-east-1 -v $PWD:/code humanmade/plugin-tester $@
docker kill s3-uploads-tests-minio > /dev/null

echo "Running Psalm..."
docker run --rm -v $PWD:/code -e TRAVIS=$TRAVIS -e TRAVIS_JOB_ID=$TRAVIS_JOB_ID -e TRAVIS_REPO_SLUG=$TRAVIS_REPO_SLUG -e TRAVIS_BRANCH=$TRAVIS_BRANCH --entrypoint='/code/vendor/bin/psalm' humanmade/plugin-tester --shepherd
