# Statamic Content Sync (UI + Diff)

[![Packagist Version](https://img.shields.io/packagist/v/emran-alhaddad/statamic-content-sync.svg)](https://packagist.org/packages/emran-alhaddad/statamic-content-sync)
[![Total Downloads](https://img.shields.io/packagist/dt/emran-alhaddad/statamic-content-sync.svg)](https://packagist.org/packages/emran-alhaddad/statamic-content-sync)
[![Monthly Downloads](https://img.shields.io/packagist/dm/emran-alhaddad/statamic-content-sync.svg)](https://packagist.org/packages/emran-alhaddad/statamic-content-sync/stats)
[![License](https://img.shields.io/packagist/l/emran-alhaddad/statamic-content-sync.svg)](LICENSE)
[![PHP](https://img.shields.io/packagist/php-v/emran-alhaddad/statamic-content-sync.svg)](https://www.php.net/)
![Laravel](https://img.shields.io/badge/Laravel-10%2F11-FF2D20?logo=laravel&logoColor=white)
![Statamic](https://img.shields.io/badge/Statamic-v5-161F6D)
[![GitHub stars](https://img.shields.io/github/stars/emran-alhaddad/statamic-content-sync?style=social)](https://github.com/emran-alhaddad/statamic-content-sync)

Export/import **Collections, Navigation, Taxonomies, Assets, Globals** with a Statamic Control Panel UI. See diffs like Git, choose **Accept incoming** or **Keep current** per item, then commit the import safely.

---

## ✨ Features

- **One UI for everything**: Collections, Taxonomies, Navigation trees, Globals, Asset meta.
- **Smart pickers**: Select the type; then pick specific handles (multi-select).
- **Delta-friendly**: Filter by sites and optional `since` (ISO8601) for narrow exports.
- **Git-style diff**: Side-by-side preview of current vs incoming.
- **Granular decisions**: Choose per item to keep current or accept incoming.
- **CLI included**: Mirror UI flows via Artisan commands.
- **Statamic v5 / Laravel 10–11 / PHP ≥ 8.1**.

> Roadmap: field-level merge decisions, queueable commits for large imports, binary asset copy helpers, rollback snapshots.

---

## 📦 Install

```bash
composer require "emran-alhaddad/statamic-content-sync:^0.1"
php artisan vendor:publish --tag=content-sync-config
php artisan statamic:stache:refresh && php artisan cache:clear
````

**No frontend build required** – compiled CP assets are included.
(If you’re developing the addon locally, run `npm i && npm run build`.)

**zsh tip**: when installing dev branches, quote constraints (to avoid globbing):

```bash
composer require "emran-alhaddad/statamic-content-sync:dev-main"
```

---

## ⚙️ Configuration

Publish config → `config/content-sync.php`:

```php
return [
    'disk'   => env('CONTENT_SYNC_DISK', 'local'),
    'folder' => env('CONTENT_SYNC_FOLDER', 'sync'),
    'chunk'  => env('CONTENT_SYNC_CHUNK', 500),
];
```

Optional `.env`:

```
CONTENT_SYNC_DISK=local
CONTENT_SYNC_FOLDER=sync
CONTENT_SYNC_CHUNK=500
```

---

## 🖥️ Control Panel Usage

Open **Utilities → Content Sync**.

### Export

1. Choose **Type**: Collections / Taxonomies / Navigation / Globals / Assets.
2. Pick one or more **handles** from the list (multi-select).
3. (Optional) Set **Sites** (comma-separated) and/or **Since** (ISO8601).
4. Set an output filename (e.g. `my-export.json`) and click **Export**.
5. The JSON is written to `<disk>/<folder>/...` (e.g. `storage/app/sync`).

### Import

1. Click **Import** tab and upload a previously exported JSON.
2. Review the **diff** for each item (Current vs Incoming).
3. For each item, choose **Accept incoming** or **Keep current**.
4. Click **Complete Import** to apply decisions.

---

## 🧰 CLI (Optional)

Export:

```bash
php artisan content-sync:export \
  --type=collections \
  --handles=pages,news \
  --sites=english,arabic \
  --since="2025-01-01T00:00:00+03:00" \
  --out=staging-delta.json
```

Import (preview only):

```bash
php artisan content-sync:import storage/app/sync/staging-delta.json
```

Import (apply all as “incoming”):

```bash
php artisan content-sync:import storage/app/sync/staging-delta.json --apply
```

---

## 📤 JSON Format (Export)

```json
{
  "exported_at": "2025-08-25T10:12:34+03:00",
  "type": "collections",
  "handles": ["pages", "news"],
  "sites": ["english"],
  "since": "2025-01-01T00:00:00+03:00",
  "items": [
    {
      "uuid": "a0e42072-fddc-476e-8a92-ae2e2be4b902",
      "collection": "pages",
      "site": "english",
      "slug": "partnerships",
      "published": true,
      "updated_at": "2025-05-04T10:33:53+03:00",
      "data": { "...": "..." }
    }
  ]
}
```

Other types (`taxonomies`, `navigation`, `globals`, `assets`) export their relevant fields (e.g., taxonomy terms, nav trees, global localizations, asset meta).

---

## 🔐 Permissions & Security

* All routes are under `web` with `statamic.cp.authenticated`.
* If you need to restrict to **super users** or a specific role/permission, wrap the routes with your preferred middleware (or add a Gate).

---

## 🧪 Local Development

```bash
git clone git@github.com:emran-alhaddad/statamic-content-sync.git
cd statamic-content-sync
composer install
npm i && npm run build
vendor/bin/pint
```

Optionally, link into a Statamic app with a path repository for rapid iteration.

---

## 🐞 Troubleshooting

* **“Package not found”**: Ensure the repo is on Packagist and a tag exists (e.g. `v0.1.0`).
* **CP JS not loading**: Confirm `resources/dist/js/content-sync.js` is committed and provider registers:

  ```php
  Statamic::script('content-sync', __DIR__.'/../resources/dist/js/content-sync.js');
  ```
* **zsh eats `*`**: Quote your Composer constraint (`"vendor/pkg:*@dev"`) or use `noglob`.

---

## 🗺️ Roadmap

* Per-field merge decisions in the diff UI
* Queueable import & progress
* Binary asset copy helpers (between disks)
* Rollback & audit logs
* Tests + fixtures (Pest)

---

## 🤝 Contributing

PRs welcome! Please:

1. Run `vendor/bin/pint --test`
2. Build assets via `npm run build`
3. Keep commits scoped & conventional (e.g., `feat:`, `fix:`, `chore:`)

---

## 📜 License

MIT © Emran Alhaddad

---

## 🔗 Links

* Packagist: [https://packagist.org/packages/emran-alhaddad/statamic-content-sync](https://packagist.org/packages/emran-alhaddad/statamic-content-sync)
* GitHub: [https://github.com/emran-alhaddad/statamic-content-sync](https://github.com/emran-alhaddad/statamic-content-sync)
* Issues: [https://github.com/emran-alhaddad/statamic-content-sync/issues](https://github.com/emran-alhaddad/statamic-content-sync/issues)
* Actions: [https://github.com/emran-alhaddad/statamic-content-sync/actions](https://github.com/emran-alhaddad/statamic-content-sync/actions)

