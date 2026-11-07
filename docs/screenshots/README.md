# Screenshots

All images are real captures of **Settings → Super Cache**, not mockups. They were taken on
WordPress 7.1.4 with PHP 8.3.36, MariaDB 10.11, six images in the media library, Varnish 7.5 in front of
the site and the local page cache warmed by real traffic.

| File | Screen | What it demonstrates |
|---|---|---|
| [`admin-overview.png`](admin-overview.png) | Full admin page | The tab bar in order: Redis Object Cache, Page Cache, Varnish Cache, nginx FastCGI, Optimize Images, Diagnostics |
| [`admin-page-cache.png`](admin-page-cache.png) | Page Cache tab | Loader, `advanced-cache.php` state, `WP_CACHE`, cached page count and size, expiry, behaviour, prefilled exclusions, **Clear Cache** and the maintenance actions (**Regenerate Page Cache Loader**, **Write / Rewrite advanced-cache.php**) |
| [`admin-varnish.png`](admin-varnish.png) | Varnish Cache tab | Purge endpoint, a passing **Test Connection** with the `X-Varnish`, `Via`, `Age` and `X-Cache` headers it received, the purge VCL snippet and the exclusions snippet |
| [`admin-nginx.png`](admin-nginx.png) | nginx FastCGI tab | Cache zone path, auto purge, the exclusivity hint and the FastCGI configuration, including the generated exclusion bypasses |
| [`admin-images.png`](admin-images.png) | Optimize Images tab | `3 of 6 images optimized`, supported formats detected at runtime, quality, dimensions, size cap and the batch optimizer |
| [`admin-conflict.png`](admin-conflict.png) | Page cache conflict | Two backends forced on by a `wp-config.php` constant: the page-level warning plus the per-tab override hint |
| [`admin-bar.png`](admin-bar.png) | Admin bar | The **Clear Page Cache** entry for the enabled backend, the settings shortcuts (including **Varnish Cache Settings** and **nginx FastCGI Settings**) and **Not enabled** for the disabled ones |

## Regenerating

The screenshots are produced from the Docker end-to-end stack, so the state they show can be reproduced:

1. Start the stack described in [testing.md](../testing.md) and open the site.
2. Create a handful of posts, upload a few images and enable the page cache backend you want to document.
3. Warm the cache by requesting a few pages, then open **Settings → Super Cache**.
4. Capture the browser window, or automate it with Playwright:

```js
const { chromium } = require( 'playwright-core' );

const browser = await chromium.launch( { executablePath: '/usr/bin/google-chrome' } );
const page = await browser.newPage( { viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 2 } );

// Log in, then open a tab through its hash.
await page.goto( 'http://localhost:8080/wp-login.php' );
await page.fill( '#user_login', 'admin' );
await page.fill( '#user_pass', 'admin' );
await Promise.all( [ page.waitForNavigation(), page.click( '#wp-submit' ) ] );
await page.goto( 'http://localhost:8080/wp-admin/options-general.php?page=icc-gg-wordpress-super-cache#local' );

await page.locator( '#icc-gg-wordpress-super-cache' ).screenshot( { path: 'admin-page-cache.png' } );
```

Keep the browser viewport at 1440 CSS pixels wide with a device scale factor of 2 so new captures match the
existing ones. Store new files in this directory only, and add them to the table above and to the README.
