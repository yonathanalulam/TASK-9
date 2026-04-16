#!/bin/bash
set -e

cd /var/www/backend

# Ensure Symfony runtime dotenv file exists in cold clones.
if [ ! -f .env ]; then
    echo "[entrypoint] backend/.env missing; creating fallback defaults..."
    cat > .env <<'EOF'
APP_ENV=dev
APP_SECRET=meridian_default_dev_secret_do_not_use_in_prod
APP_SHARE_DIR=var/share
DEFAULT_URI=http://localhost
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$'
DATABASE_URL="mysql://meridian_app:meridian_secret@mysql:3306/meridian?serverVersion=8.0&charset=utf8mb4"
EOF
fi

# Install dependencies if vendor/ is missing (cold start)
if [ ! -d vendor ] || [ ! -f vendor/autoload.php ]; then
    echo "[entrypoint] Installing Composer dependencies..."
    composer install --no-interaction --optimize-autoloader
fi

# Wait for MySQL to be truly ready
echo "[entrypoint] Waiting for MySQL..."
for i in $(seq 1 30); do
    if php -r "
        try {
            new PDO('mysql:host=mysql;port=3306;dbname=meridian', 'meridian_app', 'meridian_secret');
            exit(0);
        } catch (\Throwable \$e) {
            exit(1);
        }
    " 2>/dev/null; then
        echo "[entrypoint] MySQL is ready."
        break
    fi
    echo "[entrypoint] MySQL not ready yet (attempt $i/30)..."
    sleep 2
done

# Run migrations and schema update
echo "[entrypoint] Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration 2>&1 || true
php bin/console doctrine:schema:update --force 2>&1 || true

# Seed data if roles table is empty (first run only)
ROLE_COUNT=$(php -r "
    try {
        \$pdo = new PDO('mysql:host=mysql;port=3306;dbname=meridian', 'meridian_app', 'meridian_secret');
        \$stmt = \$pdo->query('SELECT COUNT(*) FROM roles');
        echo \$stmt->fetchColumn();
    } catch (\Throwable \$e) {
        echo '0';
    }
" 2>/dev/null)

if [ "$ROLE_COUNT" = "0" ] || [ -z "$ROLE_COUNT" ]; then
    echo "[entrypoint] Seeding demo data (first run)..."
    php bin/console app:seed:roles --no-interaction 2>&1 || true
    php bin/console app:seed:demo --no-interaction 2>&1 || true
fi

echo "[entrypoint] Starting PHP-FPM..."
exec php-fpm
