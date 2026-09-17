.PHONY: help build rebuild start stop clean cli cli-root xdebug-enable xdebug-disable phpstan phpcs phpcbf

help:
	@echo "Available targets:"
	@echo "  build           - Build Docker containers"
	@echo "  rebuild         - Rebuild Docker containers without cache"
	@echo "  start           - Start Docker containers in detached mode"
	@echo "  stop            - Stop Docker containers"
	@echo "  clean           - Stop and remove Docker containers"
	@echo "  cli             - Open a bash shell in the app container (as www-data)"
	@echo "  cli-root        - Open a root bash shell in the app container"
	@echo "  xdebug-enable   - Enable Xdebug in the app container"
	@echo "  xdebug-disable  - Disable Xdebug in the app container"
	@echo "  phpstan         - Run PHPStan static analysis"
	@echo "  phpcs           - Run PHP_CodeSniffer code style checks"
	@echo "  phpcbf          - Auto-fix PHP_CodeSniffer violations where possible"

build:
	docker compose build

rebuild:
	docker compose build --no-cache

start:
	docker compose up -d

stop:
	docker compose stop

clean:
	docker compose stop
	docker compose down --remove-orphans

cli:
	docker compose exec -u www-data app bash

cli-root:
	docker compose exec app bash

xdebug-enable:
	docker compose exec app sh -c "/enable-xdebug.sh && service apache2 reload"

xdebug-disable:
	docker compose exec app sh -c "/disable-xdebug.sh && service apache2 reload"

phpstan:
	docker compose exec -u www-data app vendor/bin/phpstan analyse

phpcs:
	docker compose exec -u www-data app vendor/bin/phpcs

phpcbf:
	docker compose exec -u www-data app vendor/bin/phpcbf
