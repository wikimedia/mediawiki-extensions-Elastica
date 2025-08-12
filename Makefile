MW_INSTALL_PATH ?= ../..

phpunit:
	${MW_INSTALL_PATH}/vendor/bin/phpunit ${MW_INSTALL_PATH}/extensions/Elastica/tests/unit/

