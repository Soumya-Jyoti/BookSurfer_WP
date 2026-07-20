# Dependency licence audit

**Ticket:** [BSWP-42](https://aivorntech.atlassian.net/browse/BSWP-42) · **Driven by:** ADR 0001 decision 3 (WordPress.org public repo)

The plugin is **GPL-2.0-or-later**. WordPress.org requires every bundled dependency to
be GPL-compatible. This audit covers what actually ships in the `.zip`.

## What ships

The distributed `.zip` contains: `book-surfer.php`, `uninstall.php`, `readme.txt`,
`LICENSE`, `includes/`, `build/`, `languages/`. It does **not** contain `node_modules/`,
`src/`, `tests/`, `stub/`, `vendor/`, or dotfiles.

So the only third-party code that ships is whatever webpack bundles into `build/`.

## Runtime dependencies bundled into `build/`

| Package | Licence | GPL-compatible | Notes |
|---|---|---|---|
| `@wordpress/element` | GPL-2.0-or-later | ✅ | **Not bundled** — externalised to the `wp.element` global. |
| `@wordpress/components` | GPL-2.0-or-later | ✅ | **Not bundled** — externalised to `wp.components`. |
| `@wordpress/block-editor` | GPL-2.0-or-later | ✅ | **Not bundled** — externalised. |
| `@wordpress/blocks` | GPL-2.0-or-later | ✅ | **Not bundled** — externalised. |
| `@wordpress/api-fetch` | GPL-2.0-or-later | ✅ | **Not bundled** — externalised. |
| `@wordpress/i18n` | GPL-2.0-or-later | ✅ | **Not bundled** — externalised. |
| `@wordpress/icons` | GPL-2.0-or-later | ✅ | Externalised where WordPress registers `wp-icons`; GPL either way. |
| React / ReactDOM | MIT | ✅ | **Not bundled** — resolves to WordPress's own copy via `wp.element`. Shipping a second React would also cause hook errors. |
| Tailwind CSS | MIT | ✅ | Build-time only. The *generated CSS* ships; Tailwind itself does not. Generated CSS is not a derivative requiring attribution. |

`@wordpress/scripts`' `DependencyExtractionWebpackPlugin` maps every `@wordpress/*`
import to a `wp.*` global and records it in `build/*/index.asset.php`, so these are
loaded from WordPress core rather than bundled.

**Net result: the shipped JavaScript contains only our own first-party code.**

## Build/dev-only dependencies (never shipped)

These live in `devDependencies` and do not enter the `.zip`. Listed for completeness.

| Package | Licence |
|---|---|
| `@wordpress/scripts`, `@wordpress/env`, `@wordpress/e2e-test-utils-playwright` | GPL-2.0-or-later |
| `@playwright/test` | Apache-2.0 |
| `typescript` | Apache-2.0 |
| `tailwindcss`, `postcss`, `autoprefixer` | MIT |
| `copy-webpack-plugin` | MIT |
| `@types/*` | MIT |

Apache-2.0 is one-way compatible with GPL-3.0 but **not** with GPL-2.0. That is not a
problem here: these are build tools, not linked or distributed with the plugin. Nothing
Apache-licensed ships in the `.zip`.

## PHP dependencies

**None ship.** `composer.json` has no runtime `require` beyond `php >= 7.4` — there is
no `vendor/` in the distributed plugin. Everything under `require-dev` (PHPCS, PHPStan,
WPCS, phpunit-polyfills) is tooling.

## Dropped from the Framer/Webflow plugin

The following were dependencies of the source plugin and are **not** carried over, which
removes them from the audit surface entirely:

| Package | Why dropped |
|---|---|
| `framer`, `framer-plugin`, `framer-plugin-tools`, `vite-plugin-framer` | Framer-host specific; the entire `src/host/` adapter layer is deleted, not ported. |
| `@webflow/designer-extension-typings`, `@webflow/webflow-cli` | Webflow-host specific. |
| `vite`, `@vitejs/plugin-react`, `@vitejs/plugin-basic-ssl`, `mkcert`, `@tailwindcss/vite` | Replaced by `@wordpress/scripts` (BSWP-39). |
| `axios` | Replaced by `@wordpress/api-fetch` (browser) and `wp_remote_*` (PHP). |
| `@radix-ui/*`, `@dnd-kit/*`, `react-hook-form`, `@hookform/resolvers`, `zod`, `clsx`, `tailwind-merge`, `sonner`, `lucide-react`, `@fontsource-variable/sora` | All MIT and therefore compatible, but **unused** — verified by content search of the source plugin. Not carried over rather than shipped dead. |

> Note: the Framer plugin listed `zod` and Radix as dependencies but imported neither.
> BSWP-19's "Zod schema still validates the proxied payload" acceptance criterion
> described a defence that did not exist in the source. Runtime validation here is the
> explicit guard in `useClasses` (`response.success && Array.isArray(response.classes)`)
> plus PHP-side sanitization. If Zod is added later it is MIT and remains compatible.

## Conclusion

✅ **No GPL-incompatible dependency ships.** The plugin is distributable under
GPL-2.0-or-later on WordPress.org.

The one thing that is *not* GPL is the **Book Surfer SaaS itself**, which is a separate,
proprietary external service the plugin talks to over HTTP. That is permitted, and is
disclosed in `readme.txt` under "External service" with links to the terms and privacy
policy, as WordPress.org requires.

**Re-run this audit whenever a dependency is added.** `npm ls --prod` should stay empty.
