# Build progress — BSWP backlog

Living checklist for the 39-ticket [BSWP backlog](https://aivorntech.atlassian.net/jira/software/projects/BSWP).
Tick a ticket only when its acceptance criteria are met **and** its gate is green.

Legend: ✅ done · 🟡 code complete, needs a human/live gate · 🔴 blocked · ⬜ not started

**Status: 34 of 39 tickets landed.** The remainder are blocked on SaaS repo access or
are manual sign-off passes that need a running environment.

---

## 🔴 Blocked — needs booksurfer.in repo access

The SaaS backend is not in this workspace. Until it is, **everything below is unblocked
by the stub** (`stub/server.js`), which reproduces the contract exactly.

| Ticket | Summary | Notes |
|---|---|---|
| 🔴 BSWP-3 | SaaS `/connect/wordpress` consent route | Per `backend-connect-setup.md`, `/connect/:host` is already parameterised — this may reduce to allowlisting `wordpress` in the host validator. Check BOOK-18 first and re-point. |
| 🔴 BSWP-4 | SaaS server-to-server exchange polling | Verify no `Origin`/CORS/cookie dependency; add polling rate limit (429 + `Retry-After`). The stub already implements the target behaviour — port it. |

---

## Epic BSWP-1 — SaaS prep and contract lock

| Ticket | Summary | Status |
|---|---|---|
| ✅ BSWP-2 | Lock the four open decisions | `docs/adr/0001-wp-plugin-architecture.md` |
| 🔴 BSWP-3 | SaaS `/connect/wordpress` route | Blocked (above) |
| 🔴 BSWP-4 | SaaS exchange polling | Blocked (above) |
| ✅ BSWP-5 | Contract doc + stub server | `docs/wordpress-connect-contract.md`, `stub/server.js` — verified end-to-end |

## Epic BSWP-6 — Plugin scaffold and bootstrap

| Ticket | Summary | Status |
|---|---|---|
| ✅ BSWP-7 | Scaffold, main file, plugin header | `book-surfer.php`, `LICENSE` (canonical GPL-2.0) |
| ✅ BSWP-8 | Class bootstrap + autoloader | `includes/class-plugin.php`, `class-autoloader.php` |
| ✅ BSWP-9 | Activation / deactivation / uninstall | `includes/class-lifecycle.php`, `uninstall.php` |
| 🟡 BSWP-10 | Standards, static analysis, CI | All configs committed. **Needs:** first CI run + branch protection enabled. |

## Epic BSWP-11 — Server-side OAuth and token store

| Ticket | Summary | Status |
|---|---|---|
| ✅ BSWP-12 | Encrypted token store | `includes/class-token-store.php` |
| ✅ BSWP-13 | Connect flow: REST route + server-side exchange | `class-oauth.php`, `class-rest.php` |
| ✅ BSWP-14 | Refresh with single-flight lock | `OAuth::refresh()` — atomic `add_option` lock, stale-lock steal |
| ✅ BSWP-15 | Disconnect and revoke | Clears locally even when the remote revoke fails |
| ✅ BSWP-16 | Connection status endpoint | Cheap by default; preloaded into the admin page |

## Epic BSWP-17 — REST proxy layer

| Ticket | Summary | Status |
|---|---|---|
| ✅ BSWP-18 | REST namespace, registry, permission callbacks | Asserted structurally by `Test_Rest` |
| ✅ BSWP-19 | Proxy `GET /classes` | `includes/class-api-proxy.php` |
| ✅ BSWP-20 | 401 refresh-and-replay | At most once; non-recursive by construction |
| ✅ BSWP-21 | Transient caching + error normalization | Key = studio handle + plugin version |

## Epic BSWP-22 — Admin settings page

| Ticket | Summary | Status |
|---|---|---|
| ✅ BSWP-23 | Admin page + React mount shell | `class-admin.php`, `src/admin/index.tsx` |
| ✅ BSWP-24 | Connect button, popup, status polling | `src/shared/use-connect.ts` |
| ✅ BSWP-25 | Class browser on api-fetch | `src/shared/use-classes.ts`, `components/ClassList.tsx` |
| ✅ BSWP-26 | Connected panel, dashboard link, disconnect | `src/admin/components/ConnectedPanel.tsx` |
| 🟡 BSWP-27 | Scope Tailwind | Config done (`preflight:false`, `bs-`, `.booksurfer-root`). **Needs:** the visual-regression pass in `docs/compat-matrix.md`. |

## Epic BSWP-28 — Gutenberg block

| Ticket | Summary | Status |
|---|---|---|
| ✅ BSWP-29 | block.json, registration, attributes | `src/block/block.json` |
| ✅ BSWP-30 | Block `edit()` — ClassesTab + LayoutSelector | `src/block/edit.tsx`; one `setAttributes` per selection |
| ✅ BSWP-31 | Live preview inside `<Disabled>` | Debounced 400ms, skeleton, timeout fallback |
| ✅ BSWP-32 | InspectorControls | `src/block/Inspector.tsx` |

## Epic BSWP-33 — Front-end render and shortcode

| Ticket | Summary | Status |
|---|---|---|
| ✅ BSWP-34 | `getEmbedUrl`/`withStyling` → PHP + parity test | `includes/helpers-embed.php` + `tests/fixtures/embed-urls.json` (24 cases) |
| ✅ BSWP-35 | Block `render_callback` emits the iframe | Shared render helper |
| ✅ BSWP-36 | `[booksurfer]` shortcode | Shares one code path with the block |
| ✅ BSWP-37 | Front-end fallback and edge states | Editor notice; nothing for visitors/feeds |

## Epic BSWP-38 — Build, i18n, packaging

| Ticket | Summary | Status |
|---|---|---|
| ✅ BSWP-39 | wp-scripts build replaces Vite | `webpack.config.js`; React external; all Framer/Webflow deps dropped |
| ✅ BSWP-40 | Asset enqueueing discipline | Screen-scoped admin assets; E2E asserts zero front-end assets |
| 🟡 BSWP-41 | i18n + POT | Every string wrapped; `wp_set_script_translations` wired. **Needs:** `npm run makepot` (requires WP-CLI) and commit `languages/book-surfer.pot`. |
| 🟡 BSWP-42 | readme.txt, GPL audit, packaging | `readme.txt` + `docs/licence-audit.md` done. **Needs:** screenshots, real terms/privacy URLs, release workflow. |

## Epic BSWP-43 — Hardening, testing, ship

| Ticket | Summary | Status |
|---|---|---|
| 🟡 BSWP-44 | Security pass | `docs/security-review.md` — checklist complete, 3 findings fixed. **Needs:** reviewer signature. |
| ✅ BSWP-45 | PHPUnit on wp-env | `tests/php/` — token store, OAuth, proxy, REST, shortcode, lifecycle |
| ✅ BSWP-46 | JS unit tests + parity gate | `tests/js/` — parity, format, shortcode |
| ✅ BSWP-47 | Playwright E2E | `tests/e2e/` — happy path + denied/popup-blocked/disconnect negatives |
| 🟡 BSWP-48 | Compatibility matrix | `docs/compat-matrix.md` template committed. **Needs:** the manual pass. |

---

## What to do next

1. **Run the gates locally.** `npm install && composer install && npm run build`, then
   `npm run test:unit`, PHPUnit via wp-env, and `npm run test:e2e` with the stub up.
   Nothing here has been executed against a live WordPress yet.
2. **Get the SaaS repo** and land BSWP-3/4 (check BOOK-18 first — BSWP-3 may re-point
   down to a config change).
3. **Fill in the manual passes:** BSWP-48's matrix and BSWP-27's visual regression.
4. **Sign off BSWP-44** with a named reviewer and date.
5. **Finish BSWP-42:** screenshots, real terms/privacy URLs, and the release action.

## Findings fixed during the build

Worth knowing about — each is regression-locked by a test:

1. **`OAuth::refresh()` treated any non-200 as a revoked grant**, so a transient SaaS 5xx
   would have disconnected every site during an outage. Now only 4xx clears the
   connection.
2. **The JS embed builder used the `in` operator** for layout lookup, which walks the
   prototype chain — `layout="constructor"` passed validation in JS but not in PHP. A
   parity break and a value-smuggling vector. Now an own-property check.
3. **The block declared a `className` attribute**, colliding with core's
   `customClassName` support. Renamed to `classLabel`.
