# Selective flush, key prefix sanitation and bundled translations

## Context

The Redis tab exposed `WP_REDIS_PREFIX`/`WP_REDIS_SELECTIVE_FLUSH` only as
`wp-config.php` constants. `wp_cache_flush()` therefore ran `FLUSHDB` unless the
site opted in to selective flush, which is unsafe on a Redis database shared by
several WordPress installations. Hosting providers also inject prefixes that
contain Redis glob metacharacters, so the prefix could not be interpolated into
`SCAN MATCH` patterns safely.

## Goal

- Make selective flush the default for `wp_cache_flush()` and the plugin's
  Predis flush path (enable/disable and WP-CLI).
- Expose a Redis-tab setting, defaulting to `true`, that is read-only when
  `WP_REDIS_SELECTIVE_FLUSH` is defined in `wp-config.php` or the environment.
- Sanitize the effective prefix (remove whitespace and glob metacharacters) and
  generate a stable per-site prefix when none is configured.
- Keep group flushes scoped to the current site.
- Bundle translations for `en-US`, `pt-BR`, `es-MX`, `fr-FR`, `zh-CN`, `hi-IN`
  and `ar-SA`, including RTL handling.

## Steps

1. Add `ICC_GG_WordPress_Super_Cache_Key_Prefix` with `sanitize()`, `generate()`,
   `resolve()`, `source()`, `selective_flush_enabled()`, `glob_quote()` and
   `escape_lua_string()`.
2. Update the drop-in to use the sanitized prefix, default selective flush to
   `true`, parse environment booleans correctly, escape Lua strings and quote
   group patterns in a single pass.
3. Update `ICC_GG_WordPress_Super_Cache_Predis::flush()` to use `SCAN` + `DEL`
   when selective flush is enabled.
4. Add the Redis-tab form, option storage (`'1'`/`'0'`), read-only state and the
   `plugins_loaded` bridge that exposes the setting to the drop-in.
5. Show the effective prefix and a warning when the configured value had to be
   sanitized.
6. Add the textdomain loader, locale mapping and RTL filters, then generate the
   POT/PO/MO catalogs.
7. Update the README and testing documentation.

## Risks

- Sanitizing or generating a prefix changes the key namespace once; existing
  keys are ignored and expire according to their TTL.
- Disabling selective flush still runs `FLUSHDB`; the UI shows a red warning and
  the constant should only be set to `false` on a dedicated Redis database.
- Multisite prefixes are network-wide, so a selective flush clears the object
  cache for every blog in the network (group flushes remain per blog).
- Automatic translations should be reviewed by native speakers before a major
  release; technical names and placeholders are protected by the generator and
  validated with `msgfmt --check`.
