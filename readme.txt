=== ICC.gg WordPress Super Cache ===
Contributors: ivancarlosti
Tags: redis, object cache, nginx cache, caching, performance, relay
Requires at least: 5.0
Tested up to: 7.1
Stable tag: 2.0.0
Requires PHP: 8.2
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

A persistent object cache backend powered by Redis®¹, with three mutually exclusive page cache backends (local PHP page cache, Varnish and Nginx) and a media library image optimizer. Supports Predis, PhpRedis, Relay, replication, sentinels, clustering and WP-CLI.

== Description ==

A persistent object cache backend powered by Redis®¹. Supports [Predis](https://github.com/predis/predis/), [PhpRedis (PECL)](https://github.com/phpredis/phpredis), [Relay](https://relaycache.com), replication, sentinels, clustering and [WP-CLI](https://wp-cli.org/).

The plugin also ships an independent local PHP page cache and a Varnish integration, plus a media library image optimizer. Only one page cache backend can be enabled at a time, and each one has its own tab with its own settings and clear cache actions.

Settings are grouped into six tabs:

* **Redis** – object cache status, connection details, metrics chart, enable/disable and flush
* **Page Cache** – local PHP page cache: status, expiry, exclusions, clear cache and preload
* **Varnish** – purge endpoint, purge method and key, connection test, VCL snippet and purge
* **Nginx** – cache zone path, auto purge, cache size, manual purge and an example Nginx configuration
* **Optimize Images** – compression quality, maximum dimensions and file size, WebP conversion and a batch optimizer
* **Diagnostics** – a copyable dump of the object cache, every page cache backend and the image optimizer configuration

Screenshots of every tab are available in the [project README](https://github.com/ivancarlosti/wordpressiccsupercache#screenshots).

To adjust the connection parameters, prefix cache keys or configure replication/clustering, see the [configuration options](https://github.com/ivancarlosti/wordpressiccsupercache#configuration).

This plugin is a fork of [Redis Object Cache](https://github.com/rhubarbgroup/redis-cache), with all functional free features preserved. The Nginx module is derived from the [Nginx Cache](https://wordpress.org/plugins/nginx-cache/) plugin by Till Krüss.

¹ Redis is a registered trademark of Redis Ltd. Any rights therein are reserved to Redis Ltd. Any use by ICC.gg WordPress Super Cache is for referential purposes only and does not indicate any sponsorship, endorsement or affiliation between Redis and ICC.gg WordPress Super Cache.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to **Settings > Super Cache** and click **Enable Object Cache**.
4. (Optional) Open the **Page Cache** tab to enable the local PHP page cache. The plugin adds `WP_CACHE` to `wp-config.php` when it can, and falls back to a must-use plugin loader when it cannot.
5. (Optional) Open the **Varnish** tab to enable and test the Varnish integration, or the **Nginx** tab to enable the Nginx cache purge and set the cache zone path.
6. (Optional) Open the **Optimize Images** tab to compress the media library.

== Troubleshooting ==

Answers to common questions and troubleshooting of common errors can be found in the upstream [FAQ](https://github.com/rhubarbgroup/redis-cache/blob/develop/FAQ.md).

== Configuration ==

The plugin comes with a vast set of configuration options that can be defined in `wp-config.php`:

* `WP_REDIS_HOST`, `WP_REDIS_PORT`, `WP_REDIS_DATABASE`, `WP_REDIS_PASSWORD`, `WP_REDIS_USERNAME`
* `WP_REDIS_SCHEME`, `WP_REDIS_PATH`, `WP_REDIS_TIMEOUT`, `WP_REDIS_READ_TIMEOUT`, `WP_REDIS_RETRY_INTERVAL`
* `WP_REDIS_SERVERS`, `WP_REDIS_SHARDS`, `WP_REDIS_CLUSTER`, `WP_REDIS_SENTINEL`
* `WP_REDIS_PREFIX`, `WP_REDIS_SELECTIVE_FLUSH`, `WP_REDIS_MAXTTL`
* `WP_REDIS_GLOBAL_GROUPS`, `WP_REDIS_IGNORED_GROUPS`, `WP_REDIS_UNFLUSHABLE_GROUPS`
* `WP_REDIS_DISABLED`, `WP_REDIS_GRACEFUL`, `WP_REDIS_CLIENT`

The Nginx module can be configured with:

* `ICC_GG_SUPER_CACHE_NGINX_ENABLED` – force the module on or off
* `ICC_GG_SUPER_CACHE_NGINX_PATH` – absolute path of the cache zone directory
* `ICC_GG_SUPER_CACHE_NGINX_AUTO_PURGE` – purge the zone automatically when content changes
* `NGINX_CACHE_PATH` – legacy alias for the cache zone path

The page cache backends can be configured with:

* `ICC_GG_SUPER_CACHE_PAGE_CACHE` – force the active backend: `local`, `varnish` or `nginx`
* `ICC_GG_SUPER_CACHE_LOCAL_ENABLED`, `ICC_GG_SUPER_CACHE_LOCAL_AUTO_PURGE` – force the local page cache on or off and control its automatic purges
* `ICC_GG_SUPER_CACHE_LOCAL_PATH` – directory holding the cached pages
* `ICC_GG_SUPER_CACHE_ALLOW_WP_CONFIG_EDIT` – set to `false` to never touch `wp-config.php` (the must-use plugin loader is used instead)
* `ICC_GG_SUPER_CACHE_VARNISH_ENABLED`, `ICC_GG_SUPER_CACHE_VARNISH_AUTO_PURGE` – force Varnish on or off and control its automatic purges
* `ICC_GG_SUPER_CACHE_VARNISH_HOST`, `ICC_GG_SUPER_CACHE_VARNISH_PORT`, `ICC_GG_SUPER_CACHE_VARNISH_KEY` – purge endpoint and shared key

== WP CLI commands ==

ICC.gg WordPress Super Cache provides various WP CLI commands. For more information run `wp help icc-gg-wordpress-super-cache` after installing the plugin.

* `wp icc-gg-wordpress-super-cache status`
* `wp icc-gg-wordpress-super-cache enable`
* `wp icc-gg-wordpress-super-cache disable`
* `wp icc-gg-wordpress-super-cache update-dropin`
* `wp icc-gg-wordpress-super-cache page-cache-status`
* `wp icc-gg-wordpress-super-cache page-cache-enable`
* `wp icc-gg-wordpress-super-cache page-cache-disable`
* `wp icc-gg-wordpress-super-cache page-cache-purge [<url>...]`
* `wp icc-gg-wordpress-super-cache page-cache-preload`
* `wp icc-gg-wordpress-super-cache varnish-status`
* `wp icc-gg-wordpress-super-cache varnish-test`
* `wp icc-gg-wordpress-super-cache varnish-purge [<url>]`
* `wp icc-gg-wordpress-super-cache images-optimize [--limit=<n>] [--loop]`
* `wp icc-gg-wordpress-super-cache nginx-status`
* `wp icc-gg-wordpress-super-cache nginx-purge`

== Changelog ==

= Unreleased =

* Added a local PHP page cache backend with its own **Page Cache** tab: page cache settings, exclusions, clear cache, purge by URL and preload.
* `WP_CACHE` is added to `wp-config.php` when that is safe (backup, temporary file, verification and revert action); when it is not, a must-use plugin loader is installed instead.
* Added a **Varnish** tab with purge endpoint, PURGE/BAN method, shared purge key, connection test, VCL snippet and manual purge.
* The local page cache, Varnish and Nginx backends are now mutually exclusive: only one page cache can be enabled at a time.
* Added an **Optimize Images** tab that compresses the media library, caps image dimensions and file size, and can convert new uploads to WebP. Supported formats are detected at runtime.
* Reordered the tabs to Redis, Page Cache, Varnish, Nginx, Optimize Images and Diagnostics.
* Added clear cache/purge entries for every enabled page cache to the admin bar.
* Added `page-cache-*`, `varnish-*` and `images-optimize` WP-CLI commands.
* Added the page cache and image optimizer state to the diagnostics output.
* Added screenshots of every admin tab to the documentation.
* Fixed the page cache directory never being created on a first enable when `wp-content/cache` did not exist yet.
* Fixed the image optimizer ignoring the media library: the selected output formats were used as the library filter, so a WebP-only configuration found nothing to optimize.
* Fixed optimizing an existing image converting it to WebP and leaving the original behind; in-place optimization now keeps the source format.
* Fixed the "more than one page cache is enabled" warning being printed once per page cache tab and the Varnish connection test being reported twice.
* The **Page Cache** tab now reports pages and files separately.
* Requires PHP 8.2 or later (previously 8.1). The plugin now deactivates itself with an admin notice when the site runs an older PHP version.

= 2.0.0 =

* Renamed the plugin to "ICC.gg WordPress Super Cache" (it was previously focused on the Redis object cache only).
* Added an independent Nginx page cache module with its own settings tab, automatic purges and a manual purge action.
* Added the `nginx-status` and `nginx-purge` WP-CLI commands.
* Added Nginx information to the diagnostics output and the admin bar.
* Merged the Overview and Metrics tabs into a single Redis tab.
* Relicensed to GPL-3.0-or-later to remain compatible with the bundled Nginx cache code.
* Existing `object-cache.php` drop-ins from earlier versions are detected and updated automatically.

