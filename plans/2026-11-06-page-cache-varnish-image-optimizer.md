# Page cache backends, Varnish and image optimizer

## Context

The plugin shipped a Redis object cache and a single page cache integration (Nginx). Three page cache
layers were possible on a real stack — a local PHP page cache, Varnish in front of the web server, and
the Nginx FastCGI cache — but only the Nginx one existed, and nothing prevented a site from enabling
several of them at the same time, which double-purges and hides cache bugs.

The media library also shipped every original image untouched, even when a WordPress site only ever
needs JPEG/PNG/WebP files below a given weight.

## Goal

1. Add a local PHP page cache backend (WPFC-style) with its own settings, exclusions, clear cache,
   purge-by-URL and preload.
2. Add a Varnish backend with settings, connection test, VCL snippet and purge.
3. Enforce mutual exclusivity between the three page cache backends (local, Varnish, Nginx).
4. Add an Optimize Images tool: compression quality, maximum width/height, maximum file size, WebP
   conversion for new uploads, and a batched optimizer for the existing library.
5. Keep clear cache/flush actions inside each page cache tab and in the admin bar for the caches that
   are enabled.
6. Reorder the tabs to Redis → Page Cache → Varnish → Nginx → Optimize Images → Diagnostics.

## Steps

1. **Registry** — `includes/class-page-cache.php` stores the active backend in
   `icc_gg_wordpress_super_cache_page_cache`, exposes `can_activate()`/`set_active()`, migrates the old
   Nginx `enabled` flag, and owns the shared automatic purge hooks (once per request).
2. **Nginx refactor** — `is_enabled()` delegates to the registry (constants still win), the class no
   longer registers its own purge hooks, and saving the Nginx tab asks the registry to enable/disable
   the backend.
3. **Local page cache** — standalone engine (`includes/local-cache-engine.php`) plus two generated
   loaders (`includes/advanced-cache.php`, `includes/mu-plugin-loader.php`). The controller
   (`includes/class-local-cache.php`) writes the engine plus a compiled `config.php` under
   `wp-content/icc-gg-page-cache/`, installs the loader, edits `wp-config.php` when safe, purges, stats
   and preloads.
4. **Varnish** — `includes/class-varnish-cache.php` builds the endpoint, sends PURGE/BAN requests with
   the site host header and optional key, probes response headers and renders the VCL snippet.
5. **Image optimizer** — `includes/class-image-optimizer.php` detects formats at runtime, optimizes
   one attachment at a time (resize → quality → target size), keeps backups, regenerates sub-sizes when
   dimensions change, and exposes a batch API used by AJAX and WP-CLI.
6. **UI** — three new tabs (`includes/ui/tabs/local.php`, `varnish.php`, `images.php`), admin bar
   entries per enabled page cache, exclusivity notices on every page cache tab, and an AJAX runner in
   `assets/js/admin.js`.
7. **Tooling** — WP-CLI commands, diagnostics entries, `docker/docker-compose.yml` end-to-end stack,
   docs and this plan.

## Risks

- **`wp-config.php` editing** is the riskiest part. Mitigations: `is_file_mod_allowed()`, standard
  `WP_CONTENT_DIR` requirement, anchor detection with a `wp-settings.php` fallback, backup in
  `wp-content/wp-config.icc-gg-backup.php`, temporary file + verification + rename, a revert action, an
  opt-out constant, and a must-use plugin loader fallback that cannot fatal when the plugin is deleted.
- **Output buffering conflicts** with other page caches or `ob_*` consumers. Mitigations: the engine
  only buffers cacheable requests, skips admin/AJAX/REST/cron/CLI, stores only complete 200 HTML
  responses, skips `Set-Cookie`, and only writes when the buffer is large enough.
- **Double compression** when `zlib.output_compression` is on. Mitigation: the engine disables zlib when
  serving its own gzip copy and omits `Content-Length` when PHP compresses the body.
- **Exclusivity surprises** on sites that intentionally run Varnish in front of Nginx. Mitigation: the
  `icc_gg_wordpress_super_cache_page_cache_exclusive` filter lifts the rule.
- **Image quality loss**. Mitigations: backups are on by default, quality never drops below 30 (or 40
  when the target size is enforced), and sub-sizes are regenerated so the media library stays coherent.
- **Long image batches** on shared hosting. Mitigation: small default batch size, AJAX loop with a pause
  button, and per-request execution limits.
