#!/usr/bin/env bash
# Mise à jour du site sur le VPS en une commande (voir docs/deploiement-vps.md).
#
# Usage, depuis le dossier du site (par défaut /var/www/taysir) :
#   bash bin/deploy.sh
#
# Étapes : récupère main depuis GitHub (avance rapide uniquement), vérifie la configuration locale,
# applique les migrations, prépare storage/, puis recharge PHP-FPM pour vider l'OPcache.
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BRANCH="${BRANCH:-main}"
WEB_USER="${WEB_USER:-www-data}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.4-fpm}"

cd "$APP_DIR"
echo "→ Site : $APP_DIR (branche $BRANCH)"

# 1. Code
git fetch --prune origin
git checkout "$BRANCH"
git merge --ff-only "origin/$BRANCH"
echo "→ Version : $(git log -1 --format='%h %s')"

# 2. Configuration propre au serveur (jamais versionnée)
for file in app database mail; do
    if [ ! -f "config/$file.local.php" ]; then
        echo "✗ config/$file.local.php manquant : copier config/$file.local.example.php et le compléter." >&2
        exit 1
    fi
done

# 3. Base de données (chaque migration n'est appliquée qu'une fois)
php database/migrate.php

# 4. Dossiers d'exécution : écrits par PHP, jamais servis par le web
mkdir -p storage/{cache,logs,mails,sessions,uploads/requests}
chown -R "$WEB_USER":"$WEB_USER" storage
find storage -type d -exec chmod 750 {} +
find storage -type f -exec chmod 640 {} +

# 5. OPcache : prise en compte immédiate du nouveau code
if systemctl list-units --type=service --all | grep -q "$PHP_FPM_SERVICE"; then
    sudo systemctl reload "$PHP_FPM_SERVICE"
fi

echo "✓ Déploiement terminé."
