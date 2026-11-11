# GTA VI OS backend

Laravel 12 API for the GTA VI AI Companion app.

- Deployed in `public_html/qla.dev/dist/gta-vi-ai-companion/endpoints`, served as `https://qla.dev/gta-vi-ai-companion/endpoints` (qla.dev's root `.htaccess` maps it). `public/index.php` accepts both URL prefixes. Redeploy: push, then open `https://qla.dev/gta-vi-ai-companion/endpoints/redeploy.php`. Redeploy runs `php artisan migrate --force`, so every pushed migration reaches the live database on the next redeploy: keep migrations additive, and never push a migration whose `up()` drops, renames or rewrites existing data without the user's explicit approval.
- The host's MySQL defaults to MyISAM with a 1000-byte key limit; `config/database.php` forces InnoDB and `AppServiceProvider` sets `Schema::defaultStringLength(191)`. Keep both.

# Database safety

`.env` points at the live production database (`tagnetba_gta` on 65.21.224.37).

- Never run `migrate:fresh`, `migrate:reset`, `migrate:refresh`, `db:wipe`, destructive rollbacks, truncation or mass deletion unless the user explicitly asks for that exact operation.
- Run tests only with `DB_CONNECTION=sqlite DB_DATABASE=:memory:`.
- Never print `.env` secrets.
