COMPOSER ?= composer
CONSOLE ?= bin/console
PHPSTAN ?= vendor/bin/phpstan
DOCKER ?= no

ifeq ($(DOCKER),yes)
RUN := docker compose exec app
else
RUN :=
endif

composer-install:
	$(RUN) $(COMPOSER) install

composer-update:
	$(RUN) $(COMPOSER) update

phpstan:
	$(RUN) $(PHPSTAN) analyse -c phpstan.neon src/ --memory-limit=1024M

frontend-install:
	cd assets && npm install

frontend-build:
	cd assets && npm run build

frontend-dev:
	cd assets && npm run dev-server
