.PHONY: help build rebuild start stop clean cli xdebug-enable xdebug-disable

help:
	@echo "Available targets:"
	@echo "  build           - Build Docker containers"
	@echo "  rebuild         - Rebuild Docker containers without cache"
	@echo "  start           - Start Docker containers in detached mode"
	@echo "  stop            - Stop Docker containers"
	@echo "  clean           - Stop and remove Docker containers"
	@echo "  cli             - Open a bash shell in the app container"
	@echo "  xdebug-enable   - Enable Xdebug in the app container"
	@echo "  xdebug-disable  - Disable Xdebug in the app container"

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
	docker compose exec app bash

xdebug-enable:
	docker compose exec app sh -c "/enable-xdebug.sh && service apache2 reload"

xdebug-disable:
	docker compose exec app sh -c "/disable-xdebug.sh && service apache2 reload"
