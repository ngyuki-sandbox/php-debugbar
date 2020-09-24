
commands:
	@grep -Po '^[a-z][^:\s]+' < Makefile | sed -e 's/^/make /'

dev:
	$(MAKE) -j up bs docker-compose-up

up:
	xdebug -S 0.0.0.0:9876 example/route.php

bs:
	browser-sync start --proxy localhost:9876 --config bs-config.js

docker-compose-up:
	docker-compose up

psalm: psalm-config
	vendor/bin/psalm.phar --threads 1 --php-version=7.0 --diff

psalm-config:
	rm -f psalm.xml
	vendor/bin/psalm.phar --init src/ 5

qa: psalm
