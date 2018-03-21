default: run-unit-tests

.PHONY: \
	clean \
	default \
	run-unit-tests \
	test-dependencies

clean:
	rm -rf vendor

composer.lock: | composer.json
	composer install

vendor: composer.lock
	composer install
composer.lock: composer.json
	composer install

test-dependencies: vendor

run-unit-tests: test-dependencies
	vendor/bin/phpsimplertest --colorful-output --bootstrap vendor/autoload.php test
