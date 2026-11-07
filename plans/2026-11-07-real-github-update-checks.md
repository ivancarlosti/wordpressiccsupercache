# Real GitHub update checks

## Context

The plugin ships its own updater (`includes/class-github-updater.php`) that proxies
`api.github.com/repos/ivancarlosti/wordpressiccsupercache/releases/latest` into the WordPress
`update_plugins` transient, so updates can be installed from the Plugins screen.

Two problems made that feature hard to trust:

- The **Check for updates** link rendered by `ICC_GG_WordPress_Super_Cache_Plugin::add_plugin_row_meta()`
  was a plain `target="_blank"` link to the GitHub releases page. It never triggered a check, so it always
  looked like nothing happened, while the real check was throttled by the 12 hour release cache (1 hour
  back-off after a failure) and by WordPress' own hourly `wp_update_plugins()` throttle.
- Latent bugs in the updater: `get_package_url()` returned the *first* `.zip` asset, which can be
  `wordpressiccsupercache-<version>.zip` (extracts into a versioned folder and installs a second copy
  instead of updating), `check_update()` never removed the opposite bucket (`response` vs `no_update`), so a
  stale "update available" row survived an update, and the updater was only bootstrapped for
  `manage_options` instead of `update_plugins`/`is_admin()`.

## Goal

- **Check for updates** performs a real, forced GitHub Release lookup.
- The result is reported in the admin: "update available" (with **Update now** and release notes),
  "already up to date", or "GitHub unreachable".
- Updates keep working through the native WordPress flow (Plugins screen, Dashboard → Updates, WP-CLI,
  auto-updates).
- No duplicate plugin folder after upgrading, no stale update rows.

## Steps

1. `includes/class-github-updater.php`
   - Add `PLUGIN_SLUG` and `RESULT_CACHE_KEY` constants.
   - Turn the helpers `get_latest_release()`, `get_package_url()`, `normalize_version()`,
     `request_recently_failed()` and `log_failed_request()` into `static` methods so they can be used
     without an instance.
   - `get_package_url()`: prefer the `icc-gg-wordpress-super-cache.zip` asset, fall back to the first `.zip`
     asset, then to the tag archive.
   - `check_update()`: `unset()` the opposite bucket before writing `response` / `no_update`.
   - New statics: `flush_cache()` (drops the release cache **and** the failure marker), `check_for_updates()`
     (forced lookup + refresh of the `update_plugins` transient, returns `state`/`version`/`installed`/`url`),
     `get_cached_update()` (cache-only, for the settings footer), `check_url()`, `update_url()`.
2. `includes/class-plugin.php`
   - Register `admin_post_icc_gg_wordpress_super_cache_check_updates`.
   - `handle_check_updates()`: `update_plugins` capability + `check_admin_referer()`, store the result in a
     five minute per-user transient, redirect to `plugins.php`.
   - Row meta: `Check for updates` now points to `check_url()`; the plain GitHub link becomes
     `Release notes`.
   - `show_admin_notices()` renders `show_update_check_notice()` (warning with **Update now** + release
     notes, success, or error) and deletes the transient after rendering.
3. `includes/ui/settings.php` — footer shows `Check for updates` plus, when the cached release is newer,
   `Update available: vX · Update now · Release notes` (cache only, no HTTP request).
4. `icc-gg-wordpress-super-cache.php` — bootstrap the updater for
   `is_admin() || DOING_CRON || WP_CLI` instead of `manage_options`.
5. Documentation — `README.md` "Updating" section, `docs/testing.md` forced-check recipe.

## Risks

- **Extra GitHub API calls.** A forced check bypasses the cache, so it can hit the unauthenticated rate
  limit (60 requests/hour per IP) if clicked repeatedly. The link is nonce-protected and only rendered for
  users with `update_plugins`.
- **Failure handling.** `flush_cache()` clears the one hour failure marker as well; a user can therefore
  retry immediately, but a broken connection still results in a single request per click, not a loop.
- **WP-CLI / cron.** Refreshing the `update_plugins` transient during an admin request is safe, but running
  `check_for_updates()` from the CLI while another request writes the same transient may briefly race;
  the next `wp_update_plugins()` regenerates it.
- **Asset naming.** The preference relies on the release asset being named exactly
  `icc-gg-wordpress-super-cache.zip`; if `semantic-release` renames it, the code falls back to the first
  `.zip` asset, which is exactly the old (unsafe) behaviour.
- **Version drift.** The working tree reports `2.0.0` while the latest release is `v3.2.0`, so the plugin
  will correctly report an update until `semantic-release` stamps a new version. Do not hand-bump the
  version.
