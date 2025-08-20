set -e

# Allow specifying a plugin-tester image tag
PLUGIN_TESTER_TAG=${PLUGIN_TESTER_TAG:-latest}

# Clean up any existing test containers
docker kill s3-uploads-tests-minio 2>/dev/null || true
docker rm s3-uploads-tests-minio 2>/dev/null || true

if [ -d "/tmp/s3-uploads-tests" ]; then
	rm -rf /tmp/s3-uploads-tests/*
else
	mkdir /tmp/s3-uploads-tests
fi

mkdir /tmp/s3-uploads-tests/tests

echo "Running tests with humanmade/plugin-tester:${PLUGIN_TESTER_TAG}"

docker run --rm --name s3-uploads-tests-minio -d -p 9000:9000 -e MINIO_ACCESS_KEY=AWSACCESSKEY -e MINIO_SECRET_KEY=AWSSECRETKEY -v /tmp/s3-uploads-tests:/data minio/minio server /data > /dev/null

# Ensure cleanup happens even if tests fail
trap 'docker kill s3-uploads-tests-minio 2>/dev/null || true' EXIT

docker run --rm -e AWS_SUPPRESS_PHP_DEPRECATION_WARNING=1 -e S3_UPLOADS_BUCKET=tests -e S3_UPLOADS_KEY=AWSACCESSKEY -e S3_UPLOADS_SECRET=AWSSECRETKEY -e S3_UPLOADS_REGION=us-east-1 -v $PWD:/code humanmade/plugin-tester:${PLUGIN_TESTER_TAG} $@
docker kill s3-uploads-tests-minio > /dev/null

echo "Running Psalm with humanmade/plugin-tester:${PLUGIN_TESTER_TAG}..."
docker run --rm -v $PWD:/code -e TRAVIS=$TRAVIS -e TRAVIS_JOB_ID=$TRAVIS_JOB_ID -e TRAVIS_REPO_SLUG=$TRAVIS_REPO_SLUG -e TRAVIS_BRANCH=$TRAVIS_BRANCH --entrypoint='/code/vendor/bin/psalm' humanmade/plugin-tester:${PLUGIN_TESTER_TAG} --shepherd
