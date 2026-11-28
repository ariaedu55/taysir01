#!/usr/bin/env bash
# Mise à jour du site sur le VPS en une commande (voir docs/deploiement-vps.md).
#
# Usage, depuis le dossier du site :
#   bash bin/deploy.sh
#
# Variables (valeurs par défaut entre parenthèses) :
#   BRANCH           branche à déployer (main)
#   WEB_USER         propriétaire du site et utilisateur de PHP-FPM (www-data)
#   PHP_BIN          PHP en ligne de commande (php)
#   PHP_FPM_SERVICE  service à recharger pour vider l'OPcache (php8.4-fpm)
#
# Sur le VPS Webuzo de taysir.goo-bridge.com, utiliser bin/deploy-goo-bridge.sh, qui fixe ces valeurs.
#
# Étapes : récupère la branche depuis GitHub (avance rapide uniquement), vérifie la configuration locale,
# applique les migrations, prépare storage/, puis recharge PHP-FPM.
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BRANCH="${BRANCH:-main}"
WEB_USER="${WEB_USER:-www-data}"
PHP_BIN="${PHP_BIN:-php}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.4-fpm}"

# Lancé en root : Git et PHP s'exécutent sous l'utilisateur du site (droits et propriété des fichiers).
as_site_user() {
    if [ "$(id -u)" -eq 0 ] && [ "$WEB_USER" != "root" ]; then
        sudo -u "$WEB_USER" -H "$@"
    else
        "$@"
    fi
}

cd "$APP_DIR"
echo "→ Site : $APP_DIR (branche $BRANCH, utilisateur $WEB_USER)"

# 1. Code
as_site_user git fetch --prune origin
as_site_user git checkout "$BRANCH"
as_site_user git merge --ff-only "origin/$BRANCH"
echo "→ Version : $(as_site_user git log -1 --format='%h %s')"

# 2. Configuration propre au serveur (jamais versionnée)
for file in app database mail; do
    if [ ! -f "config/$file.local.php" ]; then
        echo "✗ config/$file.local.php manquant : copier config/$file.local.example.php et le compléter." >&2
        exit 1
    fi
done

# 3. Base de données (chaque migration n'est appliquée qu'une fois)
as_site_user "$PHP_BIN" database/migrate.php

# 4. Dossiers d'exécution : écrits par PHP, jamais servis par le web
as_site_user mkdir -p storage/{cache,logs,mails,sessions,uploads/requests}
if [ "$(id -u)" -eq 0 ]; then
    chown -R "$WEB_USER":"$WEB_USER" storage
fi
# Le stockage des clés conserve ses modes privés (répertoire 0700 et fichiers 0600).
find storage -path storage/secrets -prune -o -type d -exec chmod 750 {} +
find storage -path storage/secrets -prune -o -type f ! -name .gitkeep ! -name .htaccess -exec chmod 640 {} +

# 5. OPcache : prise en compte immédiate du nouveau code
if systemctl list-unit-files --type=service 2>/dev/null | grep -q "^$PHP_FPM_SERVICE"; then
    if [ "$(id -u)" -eq 0 ]; then
        systemctl reload "$PHP_FPM_SERVICE" || systemctl restart "$PHP_FPM_SERVICE"
    else
        sudo systemctl reload "$PHP_FPM_SERVICE"
    fi
fi

echo "✓ Déploiement terminé."
