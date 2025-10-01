# Wiki Loves Monuments Turkey (wlmtr)
[![Laravel Forge Site Deployment Status](https://img.shields.io/endpoint?url=https%3A%2F%2Fforge.laravel.com%2Fsite-badges%2F47629dd3-031e-45f5-97d4-4593d1fd2c7c%3Fdate%3D1%26label%3D1%26commit%3D1&style=plastic)](https://forge.laravel.com/servers/724426/sites/2862777)
![GitHub License](https://img.shields.io/github/license/ooguz/wlmtr)
![Contributors](https://img.shields.io/github/contributors/ooguz/wlmtr?color=dark-green) ![Stargazers](https://img.shields.io/github/stars/ooguz/wlmtr?style=social) ![Issues](https://img.shields.io/github/issues/ooguz/wlmtr) ![GitHub Sponsors](https://img.shields.io/github/sponsors/ooguz) <a href="https://www.buymeacoffee.com/ooguz" target="_blank"><img src="https://www.buymeacoffee.com/assets/img/custom_images/orange_img.png" alt="Buy Me A Coffee" style="height: 20px !important;width: 85px !important;" ></a>


A web platform for discovering and photographing heritage monuments across Turkey. Created for the [Wiki Loves Monuments 2025](https://commons.wikimedia.org/wiki/Commons:Wiki_Loves_Monuments_2025_in_Turkey) competition.

## What is this?

This is basically a prettier, faster way to find monuments that need photos for Wikimedia Commons. Instead of digging through Wikidata queries or category pages, you get:

- **Interactive map** showing all monuments curated by [Kültür Envanteri](https://kulturenvanteri.com) and queried from Wikidata
- **Quick upload** - take a photo with your phone, add basic info, done
- **Smart filters** - find monuments near you or in specific cities
- **Direct integration** with Wikimedia Commons via OAuth

The whole thing syncs monument data from Wikidata/Structured Data on Commons daily, so it stays fresh.


## Getting Started

### Requirements

- PHP 8.2+ (running on 8.4)
- Composer
- Node.js & npm
- SQLite (or swap to MySQL/PostgreSQL if you prefer on prod)

### Installation

```bash
# Clone and install
git clone https://github.com/ooguz/wlmtr.git
cd wlmtr
composer install
npm install

# Setup environment
cp .env.example .env
php artisan key:generate

# Create database (SQLite by default)
touch database/database.sqlite

# Run migrations
php artisan migrate

# Build assets
npm run build
```

### Wikimedia OAuth Setup

You'll need to register an OAuth application on Wikimedia:

1. Go to https://meta.wikimedia.org/wiki/Special:OAuthConsumerRegistration/propose
2. Create a new OAuth 2.0 client
3. Request these grants:
   - Basic rights
   - High-volume editing
   - Edit existing pages
   - Create, edit, and move pages
   - Upload new files
   - Upload, replace, and move files
4. **NEVER FORGET YOU CANNOT CHANGE YOUR CALLBACK URI.** I needed to apply for several times.

5. Add those to `.env`:
```
WIKIMEDIA_CLIENT_ID=your_client_id
WIKIMEDIA_CLIENT_SECRET=your_client_secret
WIKIMEDIA_REDIRECT_URI=http://localhost:8000/auth/wikimedia/callback
```

### Running Locally

```bash
# Quick start (runs server, queue, logs, and vite)
composer run dev

# Or manually:
php artisan serve
php artisan queue:listen
npm run dev
```

## Syncing Monument Data

The app pulls monument data from Wikidata. Initial sync takes a while (we're talking hours for 466k monuments), but after that it's incremental updates.

```bash
# Sync all Turkish monuments from Wikidata
php artisan monuments:sync-unified

# Update photos from Commons
php artisan monuments:sync-photos

# Update locations/coordinates
php artisan monuments:sync-locations

# Just sync descriptions
php artisan monuments:sync-descriptions
```

These commands queue jobs in Horizon, so make sure `php artisan queue:listen` is running.


## Deployment

TODO

For Docker deployments, check out `docker-compose.yml` 

## Key Features

- Map with markers of monuments, retrieved via a SPARQL Wikidata query
- List of all monuments, searchable and location-based sortable (monuments near me)
- Upload directly via WLMtr or use Commons advanced wizard

## File Structure Worth Knowing

```
app/
├── Services/
│   ├── WikidataSparqlService.php    # SPARQL queries to Wikidata
│   ├── WikimediaCommonsService.php  # Upload & photo fetching
│   └── WikimediaAuthService.php     # User permission checks
├── Jobs/
│   ├── SyncMonumentsUnifiedJob.php  # Main sync job (batched)
│   └── WarmTurkeyMarkersJob.php     # Pre-caches map data
└── Models/
    ├── Monument.php                  # Core model with relationships
    └── Photo.php                     # Commons photo metadata
```

## Testing

```bash
# Run all tests
composer test

# Or use Pest directly
vendor/bin/pest

# Watch mode for development
vendor/bin/pest --watch
```

## Common Issues

**Sync jobs timing out?**
- Increase `max_execution_time` in php.ini
- Or sync in smaller batches using `--batch-size=100`

**Upload failing with CSRF error?**
- User probably needs to re-authenticate
- Check if their token expired (4-hour default)

**Map not loading monuments?**
- Run `php artisan cache:warm-turkey-markers` to pre-cache
- Check if `monuments` table has data

**`readapierror`?**
- Check OAuth2 consumer permissions.

## Contributing

PRs welcome! Just:
1. Run `vendor/bin/pint` before committing (code style)
2. Add tests for new features
3. Keep the quick upload UX smooth

## License

    wlmtr - Wiki Loves Monuments Turkey
    Copyright (C) 2025 Özcan Oğuz 

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with this program.  If not, see <https://www.gnu.org/licenses/>.


## Credits

This project is created for Wiki Loves Monuments Turkey 2025 within a really limited time window. Monument data is from [Kültür Envanteri](https://kulturenvanteri.com), an amazing project. Photos from [Wikimedia Commons](https://commons.wikimedia.org), uploaded by people like us. Map tiles from [OpenStreetMap](https://osm.org), drawn by people like us. This project become real because of [free software](https://www.gnu.org/philosophy/free-sw.en.html), free culture and a group of convinced people who are indefatigably contributing to these projects.

