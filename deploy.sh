#!/bin/bash

# Laravel WLMTR Deployment Script for Forge
# This script handles the complete deployment process

set -e  # Exit on any error

echo "🚀 Starting Laravel WLMTR deployment..."

# 1. Install/Update Dependencies
echo "📦 Installing dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction
npm install --production

# 2. Build Frontend Assets
echo "🎨 Building frontend assets..."
npm run build

# 3. Database Migrations
echo "🗄️ Running database migrations..."
php artisan migrate --force

# 4. Cache Configuration
echo "⚡ Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Optimize Application
echo "🔧 Optimizing application..."
php artisan optimize

# 6. Import Monument Data (if needed)
echo "🏛️ Importing monument data..."
# Only import if no monuments exist
MONUMENT_COUNT=$(php artisan tinker --execute="echo App\Models\Monument::count();" | tail -1)
if [ "$MONUMENT_COUNT" -eq 0 ]; then
    echo "No monuments found, checking for import files..."
    if [ -f "monuments_export.json" ] && [ -f "photos_export.json" ] && [ -f "categories_export.json" ]; then
        echo "Found export files, importing data..."
        php artisan import:monuments
    else
        echo "No export files found, attempting Wikidata sync..."
        echo "Note: Wikidata SPARQL may timeout, consider using export/import method"
        php artisan monuments:sync-all-from-wikidata --batch-size=500 --max-batches=5
        php artisan monuments:hydrate-missing --limit=500
        php artisan monuments:sync-photos
    fi
else
    echo "Found $MONUMENT_COUNT monuments, skipping import"
fi

# 7. Set Permissions
echo "🔐 Setting permissions..."
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# 8. Restart Services
echo "🔄 Restarting services..."
sudo systemctl restart php8.3-fpm
sudo systemctl restart nginx

echo "✅ Deployment completed successfully!"
echo "📊 Monument count: $MONUMENT_COUNT"
echo "🌐 Application is ready at: $(php artisan route:list | grep 'GET.*/' | head -1)"
