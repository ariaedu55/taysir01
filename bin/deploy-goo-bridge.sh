#!/usr/bin/env bash
# Mise à jour de https://taysir.goo-bridge.com (VPS Webuzo, utilisateur jomas, PHP 8.4).
# À lancer en root sur le serveur :
#   bash /home/jomas/taysir/bin/deploy-goo-bridge.sh
# Voir docs/deploiement-vps.md.
set -euo pipefail

export WEB_USER=jomas
export PHP_BIN=/usr/local/apps/php84/bin/php
export PHP_FPM_SERVICE=php-fpm84

exec bash "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/deploy.sh"
