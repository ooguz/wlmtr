#!/bin/bash

# Export monuments from local database for Forge deployment
# This script exports all monuments and photos to JSON files

set -e

echo "📤 Exporting monuments from local database..."

# Export monuments
php artisan tinker --execute="
\$monuments = App\Models\Monument::all();
file_put_contents('monuments_export.json', json_encode(\$monuments->toArray(), JSON_PRETTY_PRINT));
echo 'Exported ' . \$monuments->count() . ' monuments to monuments_export.json';
"

# Export photos
php artisan tinker --execute="
\$photos = App\Models\Photo::all();
file_put_contents('photos_export.json', json_encode(\$photos->toArray(), JSON_PRETTY_PRINT));
echo 'Exported ' . \$photos->count() . ' photos to photos_export.json';
"

# Export categories
php artisan tinker --execute="
\$categories = App\Models\Category::all();
file_put_contents('categories_export.json', json_encode(\$categories->toArray(), JSON_PRETTY_PRINT));
echo 'Exported ' . \$categories->count() . ' categories to categories_export.json';
"

echo "✅ Export completed!"
echo "📁 Files created:"
echo "   - monuments_export.json"
echo "   - photos_export.json" 
echo "   - categories_export.json"
echo ""
echo "🚀 Next steps:"
echo "   1. Upload these files to your Forge server"
echo "   2. Run: php artisan import:monuments"
