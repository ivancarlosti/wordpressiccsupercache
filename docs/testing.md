# Testing

## Static checks

Every PHP file must lint cleanly:

```bash
find . -name '*.php' -not -path './dependencies/*' -not -path './vendor/*' -exec php -l {} \;
```

The packaged tree must also not look like several plugins. WordPress reads every `*.php` file in the plugin
root **and one directory below it** as a potential plugin, so any file carrying a `Plugin Name:` header is
listed as its own plugin and the uploader offers it for activation ("The plugin does not have a valid
header."). Drop-in templates therefore live in `includes/dropins/`. Both packaging workflows run the guard
before zipping and fail on a second header:

```bash
sh tests/validate-plugin-layout.sh
```

## Page cache engine (no WordPress needed)

`includes/local-cache-engine.php` is standalone, so the pure helpers can be tested with a plain PHP
script:

```php
<?php
define( 'ABSPATH', '/tmp/e2e/wordpress/' );
define( 'ICC_GG_PAGE_CACHE_DIR', '/tmp/e2e/runtime' );
$_SERVER['HTTP_HOST'] = 'example.com';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/blog/post/';
$_SERVER['QUERY_STRING'] = '';

require __DIR__ . '/includes/local-cache-engine.php';

// Cache file path for the current request.
var_dump( icc_gg_page_cache_file( icc_gg_page_cache_config() ) );

// Store and serve.
icc_gg_page_cache_store( '<html><body>' . str_repeat( 'x', 400 ) . '</body></html>' );
icc_gg_page_cache_bootstrap(); // prints the cached page and exits
```

Covered this way: path sanitization (traversal, unsafe characters), wildcard and `regex:` exclusions,
cookie/user-agent/method/path blocking, gzip storage and gzip serving.

## End-to-end stack

`docker/docker-compose.yml` starts MariaDB, WordPress (PHP-FPM), Nginx, Varnish and a WP-CLI container:

```bash
cp docker/.env.example docker/.env
docker compose -f docker/docker-compose.yml up -d
docker compose -f docker/docker-compose.yml run --rm cli \
  wp plugin activate icc-gg-wordpress-super-cache
```

Then, through `http://localhost:8080`:

1. **Page Cache** — enable it, load the home page twice and check the `X-ICC-Page-Cache: HIT` header and
   `wp-content/cache/icc-gg-page-cache`. Use **Purge URLs** for a single post.
2. **Varnish** — enable it, run **Test Connection** and confirm the probe finds `X-Varnish`. The VCL in
   `tests/e2e/varnish.vcl` already accepts `PURGE` and `BAN` with the key from `docker/.env`.
3. **nginx FastCGI** — enable it, set the cache zone to `/var/cache/nginx/wordpress` and purge.
4. **Exclusivity** — try to enable a second backend and confirm the error message names the backend that
   is already enabled.
5. **Optimize Images** — upload a large JPEG, run the optimizer and compare the file sizes in
   `wp-content/uploads` with `wp-content/icc-gg-image-backups`.
6. **Loader recovery** — with the page cache enabled, replace `wp-content/advanced-cache.php` with a
   one-line PHP file (a "foreign" drop-in) and add `Test-Nope:` to its header to simulate a damaged file.
   The **Page Cache** tab must report *Installed by another plugin* / *Damaged or unreadable*, and
   **Write / Rewrite advanced-cache.php** must restore a working loader and leave
   `advanced-cache.icc-gg-backup.php` behind.
7. **Exclusions** — confirm the URL list is prefilled, add `/cart/`, request `/cart/` and check the page
   is not cached; remove every line and confirm `/wp-admin/options-general.php` is still skipped.
   **Restore default exclusions** must bring the list back.
8. **Snippets** — with `/cart/` saved as an exclusion, the **Varnish Cache** tab must show
   `req.url ~ "…^/cart…"` and the **nginx FastCGI** tab an `if ($request_uri ~* "^/cart")` line.

## Update checks

The plugin proxies `api.github.com/repos/ivancarlosti/wordpressiccsupercache/releases/latest` into the
`update_plugins` transient. Force a check without waiting for the 12 hour cache:

```bash
# Drop the release cache plus the failure back-off, then run a forced check.
wp eval 'ICC_GG_WordPress_Super_Cache_Github_Updater::flush_cache(); var_export( ICC_GG_WordPress_Super_Cache_Github_Updater::check_for_updates() );'

# Inspect what WordPress now believes about the plugin.
wp eval '$t = get_site_transient( "update_plugins" ); var_export( array(
    "response"  => isset( $t->response[ ICC_GG_WORDPRESS_SUPER_CACHE_BASENAME ] ) ? $t->response[ ICC_GG_WORDPRESS_SUPER_CACHE_BASENAME ] : null,
    "no_update" => isset( $t->no_update[ ICC_GG_WORDPRESS_SUPER_CACHE_BASENAME ] ),
) );'
```

`check_for_updates()` returns `state=update` (newer release found), `state=current` (already latest) or
`state=error` (GitHub unreachable — the failure is cached for one hour and cleared by `flush_cache()`).
Exactly one of `response` / `no_update` must be set for the plugin, and after upgrading the stale entry must
be gone.

In the browser:

1. **Plugins → Installed Plugins → Check for updates** redirects back to the plugin list with a notice;
   with an older version installed the row also gains WordPress' own "new version available" link.
2. **Update now** from the notice (or from the settings footer) upgrades in place and does not create a
   second plugin folder — confirm with `ls wp-content/plugins`.
3. Set `WP_HTTP_BLOCK_EXTERNAL` in `wp-config.php` (or block `api.github.com`) and check again: the notice
   must report that GitHub could not be reached instead of silently doing nothing.
4. Upgrade the release asset by hand and confirm the extracted folder is `icc-gg-wordpress-super-cache/`:

```bash
unzip -l wp-content/upgrade/icc-gg-wordpress-super-cache.zip | head
```

## WP-CLI smoke tests

```bash
wp icc-gg-wordpress-super-cache status
wp icc-gg-wordpress-super-cache page-cache-status
wp icc-gg-wordpress-super-cache page-cache-enable
wp icc-gg-wordpress-super-cache page-cache-purge
wp icc-gg-wordpress-super-cache page-cache-preload
wp cron event run --due-now
wp icc-gg-wordpress-super-cache varnish-status
wp icc-gg-wordpress-super-cache varnish-test
wp icc-gg-wordpress-super-cache images-optimize --limit=2
wp plugin update --dry-run icc-gg-wordpress-super-cache
```

## Screenshots

The admin screens in [`docs/screenshots`](screenshots/README.md) are captured from this stack, so they can be
regenerated after a UI change:

1. Start the end-to-end stack and add a few posts plus a handful of media library images.
2. Enable the backend you want to document. Only one page cache can be active, so capture the **Page Cache**,
   **Varnish Cache** and **nginx FastCGI** tabs in separate passes; force a conflict by defining
   `ICC_GG_SUPER_CACHE_NGINX_ENABLED` in `wp-config.php` if you need the warning screenshot.
3. Warm the cache by requesting a few front-end pages, then flush the one minute stats transient
   (`wp eval 'ICC_GG_WordPress_Super_Cache_Local_Cache::instance()->flush_stats_cache();'`) so the tab shows
   current numbers.
4. Capture at 1440 CSS pixels wide with a device scale factor of 2, following
   [`docs/screenshots/README.md`](screenshots/README.md).

Varnish reports its own headers only when the site URL is reachable from PHP. Inside Docker the published
port is not, so run the probe through a bridge that forwards the site URL to the Varnish service (for
example `socat TCP-LISTEN:<site-port>,fork,reuseaddr TCP:varnish:80` in the WordPress network namespace),
otherwise **Test Connection** correctly falls back to "purge accepted, headers not visible".

## Manual checks before a release

- Enable the page cache on a site where `wp-config.php` is **not** writable: the must-use plugin loader
  must be installed and `bootstrap_mode()` must report `mu-plugin`.
- Delete the backup with **Revert wp-config.php Backup** and confirm the site still serves pages.
- Truncate `advanced-cache.php` (keep the header) and confirm `loaders_are_stale()` is true, the tab reports
  *Damaged or unreadable* and the loader is rewritten on the next admin request.
- Keep a foreign `advanced-cache.php` in place and confirm enabling the page cache still refuses to replace
  it, while **Write / Rewrite advanced-cache.php** does replace it and keeps a backup.
- Disable the page cache and confirm `advanced-cache.php` and the must-use plugin loader are removed.
- Trigger a post update and confirm exactly one purge per enabled backend (`wp-content/debug.log`).
- Run **Check for updates** twice in a row: both runs must reach GitHub (no 12 hour cache) and the second
  run must not leave a stale "update available" row behind after the plugin was upgraded.
- Upload the `icc-gg-wordpress-super-cache.zip` asset on a clean site: after "Plugin installed successfully" the
  **Activate Plugin** link must point at `icc-gg-wordpress-super-cache/icc-gg-wordpress-super-cache.php` and
  activation must succeed. The drop-in templates live in `includes/dropins/`, so WordPress's plugin scanner
  must never offer *"The plugin does not have a valid header."* for `includes/advanced-cache.php`.
- Check the **Diagnostics** tab for the page cache, Varnish Cache, nginx FastCGI and image optimizer sections.
