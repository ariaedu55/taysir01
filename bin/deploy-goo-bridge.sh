#!/usr/bin/env bash
# Mise à jour de https://taysir.goo-bridge.com (VPS Webuzo, utilisateur jomas, PHP 8.4).
# À lancer en root sur le serveur :
#   bash /home/jomas/taysir/bin/deploy-goo-bridge.sh
# Voir docs/deploiement-vps.md.
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

export WEB_USER=jomas
export PHP_BIN=/usr/local/apps/php84/bin/php
export PHP_FPM_SERVICE=php-fpm84

bash "$APP_DIR/bin/deploy.sh"

# Seul public/ est lisible par Nginx (utilisateur nobody) ; le reste n'est accessible qu'à jomas.
cd "$APP_DIR"
chmod 711 .
chmod 750 app bin config database deploy docs routes tests storage .git
find public -type d -exec chmod 755 {} +
find public -type f -exec chmod 644 {} +
echo "✓ Droits appliqués."
