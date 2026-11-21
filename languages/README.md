# Translations

**Ticket:** [BSWP-41](https://aivorntech.atlassian.net/browse/BSWP-41)

This directory holds the translation template and any bundled translations. It is
declared by the plugin header's `Domain Path: /languages`.

## Regenerating the template — a release step

`book-surfer.pot` must be regenerated whenever user-facing strings change, and
committed. It requires [WP-CLI](https://wp-cli.org/):

```bash
npm run makepot
# -> wp i18n make-pot . languages/book-surfer.pot --exclude=node_modules,build,tests,stub,vendor
```

Run it **after** `npm run build`, so JS strings are picked up from source rather than
being missed.

## How strings reach the browser

- **PHP:** `__()` / `esc_html__()` / `esc_attr__()` with the `book-surfer` text domain.
  PHPCS's `WordPress.WP.I18n` rule enforces the domain and fails on a wrong or missing one.
- **JS:** `@wordpress/i18n`, with `wp_set_script_translations()` called for both bundles
  — `Admin::enqueue()` for the settings app and `Block::bootstrap_editor_script()` for
  the editor. Without those calls, translated JS strings silently fall back to English.
- **block.json:** `"textdomain": "book-surfer"` lets WordPress translate the block's
  title, description and keywords.

`load_plugin_textdomain()` is deliberately **not** called. Since WordPress 4.6, core
loads plugin translations automatically from `wp-content/languages/plugins/`, which is
where translate.wordpress.org language packs are installed, and Plugin Check warns
about the call for WordPress.org-hosted plugins (it was removed on 5 Sep 2026 during
the directory review). Consequently, `.mo` files placed in this directory are not
loaded; translations come from language packs.
