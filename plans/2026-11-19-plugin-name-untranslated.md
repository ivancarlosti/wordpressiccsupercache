# Plugin name is never translated

## Context

`ICC.gg WordPress Super Cache` is a brand, but it reaches the admin through the text domain in three
places: the Settings page heading (`includes/ui/settings.php`), the page title of the submenu callback
(`includes/class-plugin.php`) and the GitHub update notices. The bundled catalogs translated it as a
normal phrase, so sites running `es-MX`, `fr-FR` or `ar-SA` listed the plugin as *Súper caché de
WordPress de ICC.gg* / *Super Cache WordPress ICC.gg* / *ICC.gg ووردبريس ذاكرة التخزين المؤقتة الفائقة*,
and `hi-IN` and `zh-CN` used translated variants (`ICC.gg वर्डप्रेस सुपर कैश`, `ICC.gg WordPress 超级缓存`)
inside the update banner and the footer description too. The name therefore changed with the site
language, which breaks support screenshots, documentation and the directory listing.

## Goal

- Render `ICC.gg WordPress Super Cache` verbatim in every locale, both as a standalone string and
  inside longer messages.
- Keep the surrounding text translated, including the short **Super Cache** admin menu label, which is
  a label and not the product name.
- Fail packaging instead of shipping a catalog that translates the name again.

## Steps

1. Set `msgstr "ICC.gg WordPress Super Cache"` for the `msgid "ICC.gg WordPress Super Cache"` entry in
   `ar`, `es_MX`, `fr_FR`, `hi_IN`, `pt_BR` and `zh_CN` (`en_US` was already correct).
2. Restore the brand inside the update banner
   (`<strong>ICC.gg WordPress Super Cache %1$s is available.</strong> …`), the "is up to date" notice and
   the settings footer description for `fr_FR`, `hi_IN` and `zh_CN`.
3. Recompile every `.mo` with `msgfmt --check -o "${po%.po}.mo" "$po"`.
4. Add `tests/validate-i18n.sh`: it parses each `.po`, requires the exact name when the `msgid` is the
   name and requires the name as a substring when it appears inside a longer `msgid`.
5. Run the guard from `build.yml` and `wordpress-deploy.yml` next to the plugin layout guard and
   document it in `docs/testing.md`.

## Risks

- The guard only inspects `msgid`/`msgstr` pairs; a catalog that renames the brand outside a string
  carrying the brand (for example in a comment) is not detected, which is acceptable because comments
  never reach the UI.
- Editing `msgstr` values without recompiling the `.mo` files would leave the shipped catalogs stale,
  so the `.po` and `.mo` files must be committed together.
- Automatic translations of the remaining copy still need a native review; this change only pins the
  brand.
