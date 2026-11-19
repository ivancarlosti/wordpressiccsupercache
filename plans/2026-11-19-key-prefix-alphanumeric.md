# Alphanumeric key prefixes

## Context

Two sites reported key prefixes made of 40+ random punctuation characters, for
example `<$iIZ^o2xAd4-a>Ub3Vp4mYL0%oMZ1Xlo6dOCzJ:azzz_y>7GkN~Ms:D5`. Every
character belongs to the `wp_generate_password( $length, true, true )` charset,
so the values are random salts injected by the host (`WP_REDIS_PREFIX` in the
environment, `WP_CACHE_KEY_SALT`, or the Cloudways `HTTP_X_APP_USER` header),
not the plugin's md5 generator. The previous sanitizer only removed Redis glob
metacharacters and whitespace, so `<`, `>`, `^`, `%`, `&`, `=`, `~`, `;`, `:`,
`#`, `$`, `` ` ``, `-`, `_` and `.` still reached Redis keys.

## Goal

- Keep only `[A-Za-z0-9]` in every key prefix, whatever source it comes from.
- Generate an alphanumeric prefix (`icc` + 12 hexadecimal characters) when no
  prefix is configured.
- Never show a punctuation prefix as the effective value in the admin.
- Report the real origin (`WP_REDIS_PREFIX`, environment, `WP_CACHE_KEY_SALT`,
  Cloudways or generated) on the Redis tab and in Diagnostics.
- Keep the plugin helper and the standalone drop-in in lockstep.

## Steps

1. Replace the blacklist in `Key_Prefix::sanitize()` and in the drop-in's
   `sanitize_key_prefix()` with `preg_replace( '/[^A-Za-z0-9]/', '', $prefix )`;
   add `is_safe()` and `has_glob()` helpers.
2. Remove the trailing `-` from `generate()`/`generate_key_prefix()` and pass the
   generated value through the sanitizer as defense in depth.
3. Refactor source detection into `Key_Prefix::detect()` and add
   `source_detail()`/`source_label()`; the drop-in records the origin in
   `ICC_GG_SUPER_CACHE_PREFIX_ORIGIN` before plugins load.
4. Compare the raw configured value with the effective prefix in
   `is_redis_prefix_sanitized()` so environment and `WP_CACHE_KEY_SALT` values
   are covered.
5. Show `(from <source>)` next to the prefix, update the warning message and
   print the sanitized constant (plus its original length) in Diagnostics
   instead of the raw punctuation value.
6. Bump the plugin and drop-in to `2.1.1` so `maybe_update_dropin()` refreshes
   installed drop-ins.
7. Add `tests/key-prefix.php` (allow-list cases, the two reported salts, drop-in
   parity, generated shape and source detection) and update `README.md` and
   `docs/testing.md`.
8. Update the POT/PO catalogs for the new warning and the `(from %s)` string and
   rebuild every `.mo` file.

## Risks

- The effective prefix changes once for sites whose prefix contains punctuation
  and for sites that used the 2.1.0 `icc…-` generated prefix. Old keys are
  ignored and expire by TTL, so expect one round of cache misses.
- A very short configured prefix (for example `a*b`, sanitized to `a`) scans a
  wider key range. A minimum-length fallback was deliberately not implemented
  because it would silently replace legitimate short prefixes.
- Keys written by another application that shares the Redis database are not
  rewritten. The new `Key Prefix Source` row and the `(sanitized; original
  length N)` marker make a host-injected value obvious.
- The sanitizer is duplicated in the drop-in because it must run before plugins
  load; `tests/key-prefix.php` fails when the copies diverge.
- New translations should be reviewed by native speakers before a major
  release.
