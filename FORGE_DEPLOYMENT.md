# Laravel WLMTR - Forge Deployment Guide

## 🚀 Deployment Commands for Laravel Forge

### Quick Deployment (Recommended)
```bash
# Run the automated deployment script
./deploy.sh
```

### Manual Step-by-Step Deployment

#### 1. Dependencies & Assets
```bash
# Install production dependencies
composer install --no-dev --optimize-autoloader --no-interaction

# Install frontend dependencies
npm install --production

# Build frontend assets
npm run build
```

#### 2. Database & Configuration
```bash
# Run migrations
php artisan migrate --force

# Cache configuration for performance
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

#### 3. Data Synchronization (if needed)
```bash
# Check current monument count
php artisan tinker --execute="echo 'Monuments: ' . App\Models\Monument::count();"

# If no monuments exist, sync from Wikidata:
php artisan monuments:sync-from-wikidata
php artisan monuments:hydrate-missing
php artisan monuments:sync-photos
```

#### 4. Permissions & Services
```bash
# Set proper permissions
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Restart services
sudo systemctl restart php8.3-fpm
sudo systemctl restart nginx
```

## 📊 Current Data Status

- **Total Monuments**: 27,474
- **Monuments with Coordinates**: 27,469
- **Monuments with Photos**: 3,081
- **Total Photos**: 3,354

## 🔧 Queue Workers (Production)

For background job processing, set up queue workers:

```bash
# Start queue worker
php artisan queue:work --daemon

# Or use Supervisor for automatic restart
# Create /etc/supervisor/conf.d/laravel-worker.conf
```

## 📅 Scheduled Tasks (Cron)

Add to crontab for automatic data sync:
```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## 🐛 Troubleshooting

### If monuments don't appear on map:
1. Check API endpoint: `curl http://your-domain.com/api/monuments/map-markers`
2. Verify database has monuments: `php artisan tinker --execute="echo App\Models\Monument::count();"`
3. Check browser console for JavaScript errors

### If sync commands return 0 monuments:
- This is normal if monuments already exist
- The SPARQL query may timeout on Wikidata
- Existing data is sufficient for the application

## 🌐 Environment Variables

Ensure these are set in your `.env`:
```env
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=mysql
QUEUE_CONNECTION=database
```

## ✅ Verification

After deployment, verify:
- [ ] Website loads at your domain
- [ ] Map shows monuments
- [ ] API endpoints respond correctly
- [ ] Photos load properly
- [ ] Search functionality works
