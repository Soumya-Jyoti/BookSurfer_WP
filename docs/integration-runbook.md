# Book Surfer — WordPress plugin ↔ backend integration runbook

**Audience:** whoever is wiring the plugin to the live SaaS.
**Status:** the backend contract is **fully implemented and deployed to production**. No
backend or web-app code change is required to ship this plugin — the remaining work is
*build → install → verify*, not integration.

Related: [`wordpress-connect-contract.md`](wordpress-connect-contract.md) (the contract this
codes against) · [`adr/0001-wp-plugin-architecture.md`](adr/0001-wp-plugin-architecture.md).

---

## 1. Bottom line

This plugin is a **thin client**. Every endpoint it calls already exists in the SaaS
backend (`BookSurfer`, Express) and web app (`BookSurfer_front`, React), and all of it is
committed to `origin/main` — i.e. already live on Railway. WordPress rides the **same**
OAuth handshake already used by Framer and Webflow; the backend `approve`/`exchange` flow
is host-agnostic (it maps `state → owner → token pair` and does not care who is calling).

**What that means:** you do not write integration code. You build the plugin, install it,
and confirm the live flow works.

---

## 2. The endpoint map (what the plugin calls → what the backend provides)

| Plugin call | Caller | Backend route | Notes |
|---|---|---|---|
| `POST /api/oauth/plugin/approve` `{state, origin}` | **browser** (consent page, after owner logs in) | `oauth.routes.js` — **auth-protected** | Records the grant for `state`. |
| `POST /api/oauth/plugin/exchange` `{state}` | **PHP** (polled every 2s) | `oauth.controller.js::exchange` | `202` pending · `200` `{access_token, refresh_token, expires_in}` · `410` expired/consumed. |
| `POST /api/oauth/plugin/refresh` `{refresh_token}` | **PHP** | `oauth.service.js::refresh` | Rotates the pair; `401` when the grant is dead. |
| `POST /api/oauth/plugin/revoke` `{refresh_token}` | **PHP** | `oauth.controller.js::revoke` | Best-effort; always `200 {ok:true}`. |
| `GET /api/widget/classes` (Bearer) | **PHP** | `widget.routes.js` → `widgetAuth` → `getClasses` | `widgetAuth` routes a `Bearer` header to the plugin-JWT verifier. Returns `{success, classes, studio}`. |
| `GET /api/{handle}/book[/{id}]`, `/schedule[/{id}]` | **browser** (iframe `src`) | `app.js` public widget routes | Public, no auth, keep working after disconnect. |
| `GET {APP_URL}/connect/wordpress?state&origin` | **browser** (consent popup) | `ConnectPlugin.tsx` — `wordpress` is in `HOST_LABELS` | The consent screen. Calls `approve` on click. |

The shapes match exactly, not approximately. `getClasses` returns
`studio { handle, name, location, bio, address, email }` — the same field set the plugin's
`StudioInfo` type expects — and the exchange status codes (`202` / `200` / `410`) are what
the plugin's `try_exchange()` branches on.

---

## 3. The connect flow, end to end

```
WP admin clicks Connect
  │
  │  PHP mints a 64-hex state, stores it in a 10-min transient
  ▼
browser popup → {APP_URL}/connect/wordpress?state=<hex>&origin=<site-url>
  │  owner signs in to Book Surfer, clicks Approve
  │  browser POST {API_URL}/oauth/plugin/approve {state, origin}   ← records grant (owner ↔ state)
  ▼
meanwhile, PHP polls  POST {API_URL}/oauth/plugin/exchange {state}   (every 2s, 120s cap)
  │  202 while pending … then 200 { access_token, refresh_token, expires_in }
  │  tokens are stored SERVER-SIDE (encrypted). The browser never sees a token.
  ▼
PHP GET {API_URL}/widget/classes  (Authorization: Bearer <access>)   → classes + studio.handle
  ▼
block / [booksurfer] shortcode renders a public iframe:
     {API_URL}/{handle}/book/{classId}?layout=…&primary=…
```

Token lifetimes: access = 30 min (JWT, stateless), refresh = 60 days and **rotates on
every use**, state = 10 min single-use. The plugin refreshes when `expires_at - 60s <= now`
and serialises refresh with a single-flight lock so two concurrent refreshes can't
invalidate the good token.

---

## 4. Configuration

The plugin **defaults to production** — for a normal install you set nothing.

| Host | Default | Defined in |
|---|---|---|
| `APP_URL` (browser / consent) | `https://booksurfer.in` | `includes/class-plugin.php::define_constants()` |
| `API_URL` (PHP, server-to-server; **includes `/api`**) | `https://api.booksurfer.in/api` | same |

Both are **constant + filter only** — never user-supplied. That is the SSRF guard.

**Point at staging** (e.g. from a site-specific mu-plugin or `wp-config.php`):

```php
add_filter( 'booksurfer_api_base', fn() => 'https://staging-api.booksurfer.in/api' );
add_filter( 'booksurfer_app_base', fn() => 'https://staging.booksurfer.in' );
```

**Point at the local stub** (`stub/server.js`, `npm run stub`, port 5199):

```php
add_filter( 'booksurfer_api_base', fn() => 'http://127.0.0.1:5199/api' );
add_filter( 'booksurfer_app_base', fn() => 'http://127.0.0.1:5199' );
```

Under `wp-env` this is already wired by `stub/mu-plugin-stub.php` when `BOOKSURFER_STUB` is
defined. Note the asymmetry: PHP runs in the container (`host.docker.internal`) while the
consent popup opens in the host browser (`localhost`).

Other filter: `booksurfer_classes_cache_ttl` (default `300`s).

---

## 5. Build & install

Nothing here has been built yet (no `build/`, `node_modules/`, `vendor/`).

```bash
# from D:\BookSurfer_WP
npm install
composer install
npm run build            # → build/block/, build/admin/
npm run plugin-zip       # → the distributable book-surfer.zip
```

Then in WordPress: **Plugins → Add New → Upload Plugin → book-surfer.zip → Activate**, open
the **Book Surfer** admin screen, and click **Connect**.

Optional gates before shipping (all wired in CI):

```bash
npm run lint:js && npm run typecheck
npm run test:unit                 # Jest (includes the JS half of the PHP↔JS parity gate)
composer run lint && composer run analyse
# PHPUnit runs inside wp-env:
npx wp-env run tests-cli --env-cwd=wp-content/plugins/book-surfer -- vendor/bin/phpunit
# E2E needs the stub + wp-env running:
npm run stub            # terminal 1
npm run env:start       # terminal 2
npm run test:e2e        # terminal 3
```

---

## 6. Verification checklist (against prod or staging)

Run this once after install; it exercises the whole contract.

- [ ] **Connect** — admin → Connect opens `…/connect/wordpress`; the consent screen shows
      your site URL as the origin.
- [ ] **Approve** — sign in as the studio owner, click Approve; popup closes; the admin
      screen flips to **Connected** and shows the studio name.
- [ ] **Classes load** — the admin classes panel populates (proves `Bearer → widget/classes`
      works and `studio.handle` came back).
- [ ] **Render** — add the Book Surfer block *or* a `[booksurfer]` shortcode, pick a class +
      layout, view the page; the live booking iframe renders.
- [ ] **Styling passes through** — set a primary colour / layout and confirm the iframe
      reflects it (params: `layout, primary, text, bg, font, radius`).
- [ ] **Refresh** — access tokens are 30 min; after expiry the next classes fetch should
      `401 → refresh → replay` transparently (no reconnect prompt).
- [ ] **Disconnect** — click Disconnect; admin returns to the connect screen. **Already-
      published iframes keep working** (they're public URLs).
- [ ] **Reconnect** — Connect again; because disconnect adds `?prompt=login`, it forces a
      fresh sign-in rather than silently reusing the old session.

---

## 7. Two things to confirm (verify, don't build)

1. **CORS on `api.booksurfer.in`.** The consent page is served from `booksurfer.in` but the
   `approve` POST goes to `api.booksurfer.in` — cross-subdomain, with the owner's session.
   This already works for the Framer/Webflow consent flow (same page, same call), so it is
   almost certainly configured; confirm the API's CORS allows the `booksurfer.in` origin.
   *(The exchange/refresh/revoke calls are server-to-server from PHP with no browser Origin,
   so CORS never applies to them.)*

2. **Exchange rate-limiting.** The backend **excludes** `/api/oauth/plugin/exchange` from the
   global rate limiter (so 2s polling isn't throttled) but does not implement a *per-state*
   `429 + Retry-After`. The plugin tolerates its absence (it treats any non-`200`/`410` as
   "keep polling"), so this is **optional hardening**, not a blocker.

---

## 8. Troubleshooting

The plugin never echoes the raw upstream body to the browser — it normalises everything to a
stable machine `code`. Map the code you see to the cause:

| Code (HTTP) | Meaning | Fix |
|---|---|---|
| `booksurfer_not_connected` (403) | No connection stored. | Click Connect. |
| `booksurfer_reauth_required` (401) | Grant revoked/expired (refresh got a 4xx). | Reconnect. |
| `booksurfer_upstream_error` (502) | SaaS unreachable or 5xx. | Transient — retry; check `api.booksurfer.in` health. A 5xx here does **not** disconnect the site. |
| `booksurfer_rate_limited` (429) | Too many connect attempts. | Wait and retry. |
| `booksurfer_link_expired` (400) | Consent link expired (`410` upstream). | Start Connect again. |
| `booksurfer_unknown_state` (400) | State wasn't minted by this site. | Start Connect again (don't reuse an old popup). |
| `booksurfer_bad_state` (400) | State failed format validation. | Start Connect again. |

Other quick checks:

- **Consent page says "Bad host"** → the host segment isn't `wordpress`/`framer`/`webflow`.
  For WordPress it is `wordpress` and is already accepted; if you see this, the `app_base`
  filter is pointing somewhere unexpected.
- **Popup shows "Link incomplete"** → `state` missing/short in the URL; the plugin didn't
  mint it (check the admin screen actually initiated Connect).
- **Classes empty but connected** → the owner's studio has no classes yet, or no `handle`/
  `studio_slug` set (the universal embed URL is null without one).
- **Front end renders nothing after disconnect for a *shortcode*** → a shortcode without an
  explicit `handle=` relies on the stored studio, which disconnect clears. Blocks embed the
  handle and are unaffected. Pin `handle=` in shortcodes you publish.

---

## 9. Environments at a glance

| | `api_base` (PHP) | `app_base` (browser) |
|---|---|---|
| **Production** (default, no filter) | `https://api.booksurfer.in/api` | `https://booksurfer.in` |
| **Staging** | `https://staging-api.booksurfer.in/api` | `https://staging.booksurfer.in` |
| **Local stub** (`npm run stub`) | `http://127.0.0.1:5199/api` | `http://127.0.0.1:5199` |
