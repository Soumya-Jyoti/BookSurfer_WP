# Book Surfer — WordPress plugin

A standalone WordPress thin client for the Book Surfer SaaS. Connect a studio, browse
its classes, and render live booking-form iframes via a Gutenberg block or the
`[booksurfer]` shortcode.

> This is a **separate, WordPress-only codebase** — not a third host adapter inside the
> Framer/Webflow plugin. The `src/host/` adapter layer, `src/entries/` and all Vite
> config from that repo are deleted, not ported.

## Quick start

```bash
npm install
composer install
npm run build          # -> build/block/, build/admin/

npm run stub           # terminal 1: the SaaS stub on :5199
npm run env:start      # terminal 2: WordPress on :8888 (wired to the stub)
```

Log in at http://localhost:8888/wp-admin (`admin` / `password`), open **Book Surfer**,
and click Connect. The stub's consent screen has an Approve button.

| Command | What it does |
|---|---|
| `npm run start` | Rebuild on change |
| `npm run build` | Production build |
| `npm run stub` | Run the SaaS stub (BSWP-5) |
| `npm run env:start` / `env:stop` | wp-env |
| `npm run lint:js` / `npm run typecheck` | JS gates |
| `npm run test:unit` | Jest (includes the JS half of the parity gate) |
| `npm run test:e2e` | Playwright (needs the stub + wp-env running) |
| `composer run lint` / `lint:fix` | PHPCS |
| `composer run analyse` | PHPStan |
| `composer run test` | PHPUnit (run inside wp-env, see below) |
| `npm run makepot` | Regenerate `languages/book-surfer.pot` (release step) |
| `npm run plugin-zip` | Build the distributable `.zip` |

PHPUnit runs against wp-env:

```bash
npx wp-env run tests-cli --env-cwd=wp-content/plugins/book-surfer -- vendor/bin/phpunit
```

## Architecture

The call chain is inverted from the Framer plugin, and this is the whole point:

```
Framer/Webflow:  browser ──────────────────────────────► SaaS   (token in browser JS)
WordPress:       browser ──► WordPress REST ──► SaaS           (Bearer attached in PHP)
                            (nonce-protected)   (token never leaves the server)
```

| File | Responsibility |
|---|---|
| `book-surfer.php` | Plugin header, constants, autoloader, lifecycle hooks |
| `includes/class-plugin.php` | Singleton bootstrap; wires subsystems onto the right hooks |
| `includes/class-token-store.php` | Encrypted server-side token storage |
| `includes/class-oauth.php` | Connect, single-flight refresh, revoke |
| `includes/class-api-proxy.php` | Authenticated SaaS proxy, 401 replay, caching |
| `includes/class-rest.php` | `booksurfer/v1` routes + permission callbacks |
| `includes/helpers-embed.php` | Embed-URL builder + iframe renderer (shared by block & shortcode) |
| `includes/class-block.php` / `class-shortcode.php` | The two render surfaces |
| `includes/class-admin.php` | Settings screen + React mount |
| `src/shared/` | Modules imported by **both** the admin app and the block |

Decisions and their rationale: [`docs/adr/0001-wp-plugin-architecture.md`](docs/adr/0001-wp-plugin-architecture.md).
The SaaS contract: [`docs/wordpress-connect-contract.md`](docs/wordpress-connect-contract.md).

## Things that look wrong but are deliberate

Read this before "fixing" any of them.

**REST nonces are not verified by hand.** `@wordpress/api-fetch` sends `X-WP-Nonce`
automatically, and WordPress's REST cookie authentication rejects any
cookie-authenticated request without a valid nonce *before* our `permission_callback`
runs. Adding a manual `wp_verify_nonce()` would be redundant; removing the capability
check because "the nonce covers it" would be a vulnerability. Both layers stay.

**The connect flow polls `POST /connect`, not `GET /status`.** Advancing the exchange
writes tokens, so it needs `manage_options`. `/status` is deliberately `edit_posts` and
does no outbound call, because the block editor hits it on every load. Splitting them
keeps the capability boundary honest.

**PHP never long-polls the exchange endpoint.** Each browser poll triggers exactly one
bounded (`timeout => 10`) attempt. Long-polling would pin a PHP-FPM worker per
connecting admin and exhaust the pool on shared hosting.

**A 5xx from the refresh endpoint does not disconnect the site.** Only a 4xx
(`invalid_grant`) means the grant is dead. Treating a transient SaaS blip as a
revocation would disconnect every site during an outage.

**The URL builder exists twice** — `includes/helpers-embed.php` (front-end render) and
`src/shared/embed.ts` (editor preview). This is intentional (the front end must render
with zero JS), and it is the highest-risk drift in the project. A golden fixture
(`tests/fixtures/embed-urls.json`) is asserted byte-for-byte by **both** suites. If one
goes red, fix the builder that diverged — **never** edit the fixture to match.

**Styling params are `primary`/`text`/`bg`/`font`/`radius`.** These are the names the
SaaS actually honours. The Framer repo's Webflow adapter sends
`primaryColor`/`textColor`/`fontFamily`, which the backend silently ignores — a latent
bug there, deliberately not reproduced here.

**Query values encode spaces as `+`, not `%20`.** `urlencode()` in PHP and
`URLSearchParams` in JS agree; `rawurlencode()` does not and would break parity on
`font=Open+Sans`.

**Tailwind has `preflight: false`, a `bs-` prefix, and is scoped to `.booksurfer-root`.**
Preflight's global reset would wreck wp-admin and the editor canvas. The front end ships
zero Tailwind — the booking form is an iframe.

**React is never bundled.** It resolves to `wp.element`. Shipping a second copy is both
wasteful and a source of hook errors.

**Deactivate keeps tokens; uninstall deletes them.** People deactivate to debug and
expect their connection back. Leaving an encrypted refresh token in the DB after
*uninstall* is a security issue (and a .org violation).

## Pointing at a different backend

Both hosts are constant + filter only — this is also the SSRF guard, so never make them
user-supplied.

```php
add_filter( 'booksurfer_api_base', fn() => 'https://staging-api.booksurfer.in/api' );
add_filter( 'booksurfer_app_base', fn() => 'https://staging.booksurfer.in' );
```

Other filters: `booksurfer_classes_cache_ttl` (default 300s).

## Contributing

CI runs PHPCS, PHPStan, ESLint, `tsc --noEmit`, Jest, PHPUnit (PHP 7.4/8.1/8.3) and
Playwright on every PR. A red build blocks merge.

Progress against the BSWP backlog: [`docs/BUILD-TODO.md`](docs/BUILD-TODO.md).

## Licence

GPL-2.0-or-later. See [`LICENSE`](LICENSE) and
[`docs/licence-audit.md`](docs/licence-audit.md).

The Book Surfer SaaS is a separate proprietary service; see the "External service"
section of `readme.txt`.
