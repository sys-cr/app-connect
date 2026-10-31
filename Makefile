# SPDX-License-Identifier: GPL-3.0-or-later
# SPDX-FileCopyrightText: 2026 Bodo Steffen
# Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt
.PHONY: help lint test-plugin test-contract test dist

VERSION := $(shell sed -n 's/^version=//p' plugin/plugin.manifest)

help:
	@echo "make lint              – php -l over every plugin file"
	@echo "make test-plugin       – PHPUnit unit tests (plugin/tests/Unit)"
	@echo "make test-contract     – schema and shared test vectors (plugin/tests/Contract)"
	@echo "make test              – both test suites"
	@echo "make dist              – plugin ZIP of the committed plugin/ (without tests/) in dist/"

plugin/vendor:
	cd plugin && composer install --no-interaction --quiet

lint:
	@find plugin -path plugin/vendor -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null && echo "php -l: ok"

test-plugin: plugin/vendor
	cd plugin && vendor/bin/phpunit --testsuite unit

test-contract: plugin/vendor
	cd plugin && vendor/bin/phpunit --testsuite contract

test: plugin/vendor
	cd plugin && vendor/bin/phpunit

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
	git archive --format=zip --mtime="$$(git log -1 --format=%cI HEAD)" -o dist/app-connect-$(VERSION).zip HEAD:plugin -- . ':(exclude)tests'
	@[ "$$(unzip -l dist/app-connect-$(VERSION).zip | grep -cE ' (LICENSE|SUPPLEMENTAL-TERMS\.txt)$$')" = 2 ] \
		|| { echo "license texts missing from the archive (not committed yet?)"; rm -f dist/app-connect-$(VERSION).zip; exit 1; }
	@echo "dist/app-connect-$(VERSION).zip"
