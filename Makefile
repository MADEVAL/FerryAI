.PHONY: test check lint coverage

test:
	composer test

check:
	composer check

lint:
	composer lint

coverage:
	composer coverage

integration:
	composer test-integration
