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

`load_plugin_textdomain()` is still called from `Plugin::load_textdomain()`. It is
redundant on WordPress 6.5+ (which loads translations automatically) but the plugin
declares 6.4 as its minimum, where it is not.
