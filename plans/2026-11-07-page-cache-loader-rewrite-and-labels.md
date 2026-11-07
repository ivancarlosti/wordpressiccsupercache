# Page cache loader rewrite, clearer labels and exclusion defaults

## Context

The three page cache backends were labelled **Redis**, **Varnish** and **Nginx**, which hides what each tab
actually controls: an object cache, an HTTP cache and a FastCGI cache that behave very differently.

Two user-facing problems came with it:

1. **`advanced-cache.php` dead end.** `install()` refuses to touch a drop-in installed by another plugin and
   returns `foreign_dropin`. Because `handle_local_cache_action()` enables the Page Cache *before* calling
   `install()`, a site could end up enabled with a broken or missing loader, and the only offered fix
   (**Regenerate Page Cache Loader**) re-ran the same failing path. `bootstrap_mode()` also trusted the
   header alone, so a truncated drop-in that still carried `Plugin URI`/`Version` was reported as healthy.
2. **Invisible exclusions.** The URL exclusions looked empty while the engine silently hard-coded
   `/wp-admin`, `/wp-login.php`, `wp-cron.php`, feeds and trackbacks. Nothing explained that Varnish and
   nginx FastCGI never see those exclusions, because they answer before PHP runs.

## Goal

- Rename the labels to **Redis Object Cache**, **Varnish Cache** and **nginx FastCGI** (labels only: slugs,
  option keys, constants and WP-CLI commands stay unchanged).
- Add a **Write / Rewrite advanced-cache.php** action that installs a working loader even when the file is
  missing, damaged, outdated or owned by another plugin, backing the current file up first.
- Report the real state of both loaders and self-heal only our own damaged files.
- Prefill the exclusion list with the WordPress defaults, expose them in a filter and add a **Restore
  default exclusions** action.
- Clarify, in the UI and in the docs, that exclusions must be repeated for Varnish (`vcl_recv`) and
  nginx (`fastcgi_cache_bypass`), and render snippets generated from the saved list.

## Steps

1. **Labels** — `class-plugin.php` (tab labels, admin bar shortcuts, admin messages), `class-page-cache.php`
   (backend labels), `tabs/varnish.php`, `tabs/nginx.php`, `class-nginx-cache.php`, `class-cli-commands.php`,
   `diagnostics.php`, `ui/settings.php`, plugin header, `README.md`, `readme.txt`, `docs/*.md` and
   `docs/screenshots/README.md` captions.
2. **Loader API** — `Local_Cache::loader_state()`, `dropin_is_valid()`, `mu_plugin_is_valid()`,
   `loader_is_valid()`, `loaders_are_stale()`, `loader_backup_path()`, `dropin_backup_path()`,
   `backup_loader_file()`, `get_last_backup_path()`, `force_write_loader()` and a shared
   `loader_contents()` renderer used by `write_loader_file()` and by the integrity check.
3. **Action + UI** — `write-page-cache-loader` in `$actions`, `tab_fragment_for_action()`,
   `handle_extra_actions()` and `handle_local_cache_action()`; a `advanced-cache.php` status row, state
   specific notices and the Maintenance button in `tabs/local.php`; `maybe_sync_runtime()` also repairs our
   own damaged loader.
4. **Exclusions** — `default_exclude_urls()`, `exclusion_regexes()`, a one-time add-only migration
   (`ICC_GG_SUPER_CACHE_LOCAL_EXCLUSIONS_MIGRATED`), `reset_exclusions()` and the `reset-local-exclusions`
   action, plus the filter `icc_gg_wordpress_super_cache_local_default_exclude_urls`.
5. **Varnish / nginx** — `Varnish_Cache::vcl_bypass_snippet()` and a new section on its tab; the nginx tab
   expands the exclusions into `if ($request_uri ~* …) { set $icc_gg_skip_cache 1; }` lines; new
   `docs/nginx.md` and an **Exclusions** section in `docs/varnish.md`.
6. **Validation** — `php -l` on every changed file, a standalone harness exercising the new API (defaults,
   compilation, migration, states, forced rewrite, backup, self-heal, snippets) and the manual checks in
   `docs/testing.md`.

## Risks

- **Replacing another plugin loader.** The forced write can overwrite a foreign `advanced-cache.php`. It is
  only reachable through an explicit, nonce-protected action and the previous file is always copied to
  `advanced-cache.icc-gg-backup.php` first; `install()` keeps refusing foreign files.
- **Strict content comparison.** A file edited by hand (line endings, comments) is reported as *Damaged or
  unreadable* and rewritten. This is the intended behaviour, but it makes the state row noisy on manually
  patched installs.
- **Migration is add-only.** A site that deliberately removed `/wp-admin/*` from the list gets the line back
  once. The engine keeps enforcing it anyway, so the behaviour does not change.
- **Generated snippets drift.** VCL and nginx configurations are copies: editing the exclusions requires
  copying the snippets again. Documented on all three tabs.
- **Purges ignore exclusions.** Purging still removes every stored object for the requested path/host; the
  snippets only keep dynamic requests out of the cache.

## Verification

- `php -l` on every changed PHP file: clean.
- Standalone harness (35 assertions): defaults prefilled, PCRE compilation, one-time migration, loader
  states (`missing`/`foreign`/`invalid`/`outdated`/`valid`), `install()` refusing a foreign drop-in,
  forced rewrite + backup, automatic repair of a truncated loader and both generated snippets.
- Docker end-to-end checklist items 6–8 in `docs/testing.md` (loader recovery, exclusions, snippets).
