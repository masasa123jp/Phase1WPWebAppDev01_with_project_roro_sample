#!/usr/bin/env sh
# Automatically called by npm postinstall to enable Git hooks & WP-CS.
npx husky install
chmod +x .husky/pre-commit
