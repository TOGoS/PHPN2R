default: run-unit-tests

.PHONY: \
	default \
	run-unit-tests

vendor: composer.json
	composer update

run-unit-tests: vendor
	vendor/bin/phpunit --bootstrap vendor/autoload.php test
