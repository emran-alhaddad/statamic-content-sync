# Statamic Content Sync (UI + Diff)

Export/import **Collections, Navigation, Taxonomies, Assets, Globals** with a Statamic CP UI and Git-style diff preview.

![Status](https://img.shields.io/packagist/v/emran-alhaddad/statamic-content-sync.svg)

## Requirements
- PHP ^8.1
- Laravel ^10|^11
- Statamic ^5

## Install
```bash
composer require "emran-alhaddad/statamic-content-sync:^0.1"
php artisan vendor:publish --tag=content-sync-config
npm i && npm run build
php artisan statamic:stache:refresh && php artisan cache:clear
Open Utilities → Content Sync in the CP.

.env (optional)
ini
Copy
Edit
CONTENT_SYNC_DISK=local
CONTENT_SYNC_FOLDER=sync
CONTENT_SYNC_CHUNK=500
CLI (optional)
bash
Copy
Edit
php artisan content-sync:export --type=collections --handles=pages,news --sites=english
php artisan content-sync:import storage/app/sync/collections-export-20250101-120000.json --apply
License
MIT © Emran Alhaddad