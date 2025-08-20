#!/usr/bin/env bash
# Generate roro-core.pot using WP-CLI i18n make-pot.
set -e

wp i18n make-pot . languages/roro-core.pot --slug=roro-core \
  --exclude=vendor,tests,node_modules,blocks/*/build
