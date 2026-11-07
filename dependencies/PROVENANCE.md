# Vendored dependencies

These libraries are **copied into the plugin, not installed by Composer**. The
root `composer.json` neither requires nor autoloads them: the runtime loads them
directly with `require_once`, so the release ZIP works on a WordPress site with
no Composer toolchain.

| Path | Version | Loaded by |
| --- | --- | --- |
| `predis/predis/src/`, `predis/predis/autoload.php` | Predis 2.4.0 (`Client::VERSION` in `src/Client.php`) | `includes/class-predis.php`, `includes/dropins/object-cache.php` |
| `colinmollenhour/credis/` (`Client.php`, `Cluster.php`, `Sentinel.php`, `Module.php`) | upstream tree vendored with the plugin | `includes/dropins/object-cache.php` |

## Local modifications

Applied after copying from upstream, so they are not present in upstream
tarballs:

- `predis/predis/composer.json` and `colinmollenhour/credis/composer.json`:
  `require.php` raised to `>=8.2`. Upstream advertises `^7.2 || ^8.0` and
  `>=7.4.0`. Composer never reads these files here, but leaving them untouched
  makes the distribution advertise a PHP floor lower than the one this plugin
  enforces in its plugin header and its activation guard.

Nothing else in these trees is edited — keep it that way, so an upstream refresh
stays a straight copy plus a reapply of the change above.

## Packaging

The release ZIP ships the library code, both `composer.json` files (licence and
provenance metadata) and the upstream `LICENSE` files. Upstream dev tooling is
dropped by `.github/workflows/wordpress-deploy.yml`: `*/testenv/`, `*/bin/`,
`*/phpunit_local.sh`, `*/.editorconfig` and `*/*.markdown`.

## Refreshing

There is no automated update path: a stale copy stays stale silently, so an
upstream security fix will not arrive on its own. Refresh by downloading the
upstream release, replacing the tree, reapplying the `require.php` change, and
replaying the deploy packaging step (`rsync` with the workflow's exclude list)
to confirm the ZIP still contains everything it needs.
