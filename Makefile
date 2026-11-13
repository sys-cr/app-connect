# SPDX-License-Identifier: GPL-3.0-or-later
# SPDX-FileCopyrightText: 2026 Bodo Steffen
# Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt
.PHONY: help lint locale test-plugin test-contract test test-integration test-e2e dist

VERSION := $(shell sed -n 's/^version=//p' plugin/plugin.manifest)

help:
	@echo "make lint              – php -l over every plugin and test file"
	@echo "make test-plugin       – PHPUnit unit tests (plugin/tests/Unit)"
	@echo "make test-contract     – schema and shared test vectors (plugin/tests/Contract)"
	@echo "make test              – unit and contract tests (integration tests are skipped without a database)"
	@echo "make test-integration  – integration tests against a throwaway MariaDB in Docker (plugin/tests/Integration)"
	@echo "make test-e2e          end-to-end tests against Stud.IP in Docker, STUDIP=6.0 to 6.3, default 6.3 (plugin/tests/E2E)"
	@echo "make locale            – compile the translations (plugin/locale/*/LC_MESSAGES/app-connect.po) with msgfmt"
	@echo "make dist              – plugin ZIP of the committed plugin/ (without tests and Composer/PHPUnit files) in dist/"

plugin/vendor:
	cd plugin && composer install --no-interaction --quiet

lint:
	@find plugin tests -path plugin/vendor -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null && echo "php -l: ok"

# The compiled catalogues are committed, so the plugin ZIP needs no gettext
# tools; LocaleTest fails when a .mo lags behind its .po.
locale:
	@for po in plugin/locale/*/LC_MESSAGES/app-connect.po; do msgfmt -o "$${po%.po}.mo" "$$po" || exit 1; done
	@echo "msgfmt: ok"

test-plugin: plugin/vendor
	cd plugin && vendor/bin/phpunit --testsuite unit

test-contract: plugin/vendor
	cd plugin && vendor/bin/phpunit --testsuite contract

test: plugin/vendor
	cd plugin && vendor/bin/phpunit

# Starts MariaDB 10.11 in Docker on a free local port, runs the integration
# tests against it and stops the container (started with --rm, so it goes
# away). With APP_CONNECT_TEST_DB already set, as in CI, that database is used.
IT_DB_PASSWORD := app-connect-it
test-integration: plugin/vendor
	@if [ -n "$$APP_CONNECT_TEST_DB" ]; then \
		cd plugin && vendor/bin/phpunit --testsuite integration; \
	else \
		id=$$(docker run -d --rm -e MARIADB_ROOT_PASSWORD=$(IT_DB_PASSWORD) -e MARIADB_DATABASE=app_connect_it -p 127.0.0.1::3306 mariadb:10.11) || exit 1; \
		port=$$(docker port $$id 3306/tcp | head -1 | sed 's/.*://'); \
		for i in $$(seq 1 60); do docker exec $$id mariadb-admin ping -uroot -p$(IT_DB_PASSWORD) --silent >/dev/null 2>&1 && break; sleep 1; done; \
		sleep 2; \
		cd plugin && APP_CONNECT_TEST_DB="mysql:host=127.0.0.1;port=$$port;dbname=app_connect_it" \
			APP_CONNECT_TEST_DB_USER=root APP_CONNECT_TEST_DB_PASSWORD=$(IT_DB_PASSWORD) \
			vendor/bin/phpunit --testsuite integration; status=$$?; \
		docker stop $$id >/dev/null; exit $$status; \
	fi

# Builds the Stud.IP image of version STUDIP from the core sources (or reuses
# it), starts it with the plugin from plugin/ and MariaDB, runs the e2e suite
# and removes everything again; see tests/e2e/run.sh.
STUDIP ?= 6.3
test-e2e: plugin/vendor
	tests/e2e/run.sh all $(STUDIP)

# The plugin is installed from a ZIP of plugin/, so the license texts must be
# inside it: plugin/LICENSE and plugin/SUPPLEMENTAL-TERMS.txt are copies of the
# texts at the repository root. Archiving a tree stamps the current time on
# every entry; --mtime uses the commit time, so a tag always yields the same ZIP.
dist:
	@cmp -s LICENSES/GPL-3.0-or-later.txt plugin/LICENSE \
		|| { echo "plugin/LICENSE differs from LICENSES/GPL-3.0-or-later.txt"; exit 1; }
	@cmp -s SUPPLEMENTAL-TERMS.txt plugin/SUPPLEMENTAL-TERMS.txt \
		|| { echo "plugin/SUPPLEMENTAL-TERMS.txt differs from SUPPLEMENTAL-TERMS.txt"; exit 1; }
	@mkdir -p dist
	git archive --format=zip --mtime="$$(git log -1 --format=%cI HEAD)" -o dist/app-connect-$(VERSION).zip HEAD:plugin -- . ':(exclude)tests' ':(exclude)composer.json' ':(exclude)composer.lock' ':(exclude)phpunit.xml'
	@[ "$$(unzip -l dist/app-connect-$(VERSION).zip | grep -cE ' (LICENSE|SUPPLEMENTAL-TERMS\.txt)$$')" = 2 ] \
		|| { echo "license texts missing from the archive (not committed yet?)"; rm -f dist/app-connect-$(VERSION).zip; exit 1; }
	@echo "dist/app-connect-$(VERSION).zip"
