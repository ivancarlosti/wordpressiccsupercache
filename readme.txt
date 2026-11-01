=== ICC.gg WordPress Super Cache ===
Contributors: ivancarlosti
Tags: redis, object cache, nginx cache, caching, performance, relay
Requires at least: 5.0
Tested up to: 7.0
Stable tag: 2.0.0
Requires PHP: 8.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

A persistent object cache backend powered by Redis®¹, plus an independently configurable Nginx page cache layer. Supports Predis, PhpRedis, Relay, replication, sentinels, clustering and WP-CLI.

== Description ==

A persistent object cache backend powered by Redis®¹. Supports [Predis](https://github.com/predis/predis/), [PhpRedis (PECL)](https://github.com/phpredis/phpredis), [Relay](https://relaycache.com), replication, sentinels, clustering and [WP-CLI](https://wp-cli.org/).

The plugin also ships an independent Nginx page cache module that purges the directory used by `fastcgi_cache_path`, `proxy_cache_path` or `uwsgi_cache_path`, either automatically when content changes or manually from the admin area.

Settings are grouped into three tabs:

* **Redis** – object cache status, connection details, metrics chart, enable/disable and flush
* **Nginx** – cache zone path, auto purge, cache size, manual purge and an example Nginx configuration
* **Diagnostics** – a copyable dump of the Redis and Nginx configuration

To adjust the connection parameters, prefix cache keys or configure replication/clustering, see the [configuration options](https://github.com/ivancarlosti/wordpressiccsupercache#configuration).

This plugin is a fork of [Redis Object Cache](https://github.com/rhubarbgroup/redis-cache), with all functional free features preserved. The Nginx module is derived from the [Nginx Cache](https://wordpress.org/plugins/nginx-cache/) plugin by Till Krüss.

¹ Redis is a registered trademark of Redis Ltd. Any rights therein are reserved to Redis Ltd. Any use by ICC.gg WordPress Super Cache is for referential purposes only and does not indicate any sponsorship, endorsement or affiliation between Redis and ICC.gg WordPress Super Cache.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to **Settings > Super Cache** and click **Enable Object Cache**.
4. (Optional) Open the **Nginx** tab to enable the Nginx page cache purge and set the cache zone path.

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

== WP CLI commands ==

ICC.gg WordPress Super Cache provides various WP CLI commands. For more information run `wp help icc-gg-wordpress-super-cache` after installing the plugin.

* `wp icc-gg-wordpress-super-cache status`
* `wp icc-gg-wordpress-super-cache enable`
* `wp icc-gg-wordpress-super-cache disable`
* `wp icc-gg-wordpress-super-cache update-dropin`
* `wp icc-gg-wordpress-super-cache nginx-status`
* `wp icc-gg-wordpress-super-cache nginx-purge`

== Changelog ==

= 2.0.0 =

* Renamed the plugin to "ICC.gg WordPress Super Cache" (it was previously focused on the Redis object cache only).
* Added an independent Nginx page cache module with its own settings tab, automatic purges and a manual purge action.
* Added the `nginx-status` and `nginx-purge` WP-CLI commands.
* Added Nginx information to the diagnostics output and the admin bar.
* Merged the Overview and Metrics tabs into a single Redis tab.
* Relicensed to GPL-3.0-or-later to remain compatible with the bundled Nginx cache code.
* Existing `object-cache.php` drop-ins from earlier versions are detected and updated automatically.

