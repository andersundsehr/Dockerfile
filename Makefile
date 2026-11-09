MAKEFLAGS += --silent
.PHONY: setup structure-test

# Installs the gems of the serverspec tests; webdevops/dockerfile-build-env runs it while it is built
setup:
	cd tests/serverspec && bundle install --path=vendor

structure-test:
	cd tests/structure-test && ./run.sh
