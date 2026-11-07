# Fix "The plugin does not have a valid header." on upload

## Context

Uploading the release ZIP of v3.2.0 (`icc-gg-wordpress-super-cache.zip`) through **Plugins → Add New → Upload
Plugin** installs the files but the screen then offers an **Activate Plugin** link that points at

```
wp-admin/plugins.php?action=activate&plugin=icc-gg-wordpress-super-cache%2Fincludes%2Fadvanced-cache.php
```

Clicking it fails with *"The plugin does not have a valid header."* (`no_plugin_header`).

Cause: `wp-admin/includes/plugin.php` `get_plugins()` scans **one level inside** a plugin folder and treats
every `*.php` file that carries a `Plugin Name:` header as a plugin. Three drop-in templates lived in
`includes/` and declared a plugin header:

| File | Header |
| --- | --- |
| `includes/advanced-cache.php` | `Plugin Name: ICC.gg Page Cache` |
| `includes/mu-plugin-loader.php` | `Plugin Name: ICC.gg Page Cache Loader` |
| `includes/object-cache.php` | `Plugin Name: ICC.gg WordPress Super Cache Drop-In` |

Reproduced on WordPress 7.1.4:

```php
get_plugins( '/icc-gg-wordpress-super-cache' );
// includes/advanced-cache.php, includes/mu-plugin-loader.php,
// icc-gg-wordpress-super-cache.php, includes/object-cache.php

get_plugins(); // only icc-gg-wordpress-super-cache/icc-gg-wordpress-super-cache.php
```

`Plugin_Upgrader::plugin_info()` calls `get_plugins( '/' . $destination_name )`, sorts by name and returns the
**first** entry, so the upgrader hands out `includes/advanced-cache.php` (name *"ICC.gg Page Cache"* sorts before
*"ICC.gg WordPress Super Cache"*). `validate_plugin()` then calls `get_plugins()` **without** a folder, which
never lists files inside sub-folders, so the key is missing and activation returns `no_plugin_header`.

## Goal

The release ZIP must install and activate normally, the upgrader must resolve the real plugin file, and the
drop-in templates must keep their headers (they are read back with `get_plugin_data()` for `PluginURI` and
`Version`, and the must-use loader wants a name in the Must-Use tab).

## Steps

1. Move the three templates to `includes/dropins/` — nested one level deeper than `get_plugins()` scans, so the
   headers stay and WordPress never sees them as plugins.
2. `class-local-cache.php` — `loader_contents()` reads templates from `includes/dropins/`; docblocks updated.
   Call sites still pass `advanced-cache.php` / `mu-plugin-loader.php`.
3. `class-plugin.php` and `class-cli-commands.php` — copy/compare `object-cache.php` from
   `includes/dropins/object-cache.php`.
4. Point `README.md` and `dependencies/PROVENANCE.md` at the new paths.
5. Add `tests/validate-plugin-layout.sh` and run it from `build.yml` and `wordpress-deploy.yml` before the ZIP
   is created: it fails when any file in the two levels WordPress scans (besides the main plugin file) declares
   a `Plugin Name:` header, so the next release breaks CI instead of breaking uploads.
6. Document both the guard (`docs/testing.md` → *Static checks*) and the manual upload check (*before a
   release*): after "Plugin installed successfully" the **Activate Plugin** link must point at
   `icc-gg-wordpress-super-cache/icc-gg-wordpress-super-cache.php` and activation must succeed.

## Risks

- **Existing installs.** The template *contents* are unchanged, so `dropin_is_valid()` / `mu_plugin_is_valid()`
  keep reporting the installed loaders as valid; only the read path inside the plugin changes.
- **Third-party references.** Anything that hard-codes `wp-content/plugins/icc-gg-wordpress-super-cache/includes/object-cache.php`
  (docs, deployment scripts) must use `includes/dropins/object-cache.php`.
- **Already published releases.** `v3.2.0` still ships the broken layout; the fix reaches users on the next
  release. The `icc-gg-wordpress-super-cache.zip` asset of an existing release can be rebuilt from the tag with
  the fixed tree if a hot fix is needed.
- **Build ignores.** `build.yml` / `wordpress-deploy.yml` exclude paths by name, none of them match
  `includes/dropins/`, so the folder ships in both archives.
