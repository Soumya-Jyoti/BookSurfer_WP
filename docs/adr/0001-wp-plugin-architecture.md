# ADR 0001 — Book Surfer WordPress plugin architecture

- **Status:** Accepted
- **Date:** 2026-07-16
- **Deciders:** Plugin lead, SaaS backend owner
- **Ticket:** [BSWP-2](https://aivorntech.atlassian.net/browse/BSWP-2)
- **Supersedes:** nothing. **Blocks:** BSWP-3, BSWP-7, BSWP-42

## Context

We are shipping the existing Framer/Webflow Book Surfer plugin as a standalone
WordPress plugin. §11 of the conversion spec listed four open decisions, each of
which changes the shape of the codebase. Reversing any of them later means
rewriting the auth layer, the build config, or both — so they are settled here,
before scaffolding.

This is a **thin client**. It does not rebuild the Book Surfer dashboard: classes,
bookings and payments stay on booksurfer.in and the plugin links out to them.

---

## Decision 1 — Token storage: **server-side, encrypted in `wp_options`**

**Decision.** Tokens live server-side in a single `booksurfer_connection` option
(`autoload = no`). The refresh token is encrypted at rest with a key derived from
`AUTH_KEY`/`AUTH_SALT`. The browser never receives a token of any kind.

**Rationale.** The Framer plugin keeps tokens in per-user `localStorage` and calls
the SaaS directly from the browser. That model does not translate to WordPress and
reusing `auth.ts` would have been actively worse here:

- WordPress admin is multi-user. `localStorage` is per-browser, so every admin would
  have to connect separately — but the studio connection is a property of the *site*,
  not of the person looking at it.
- A token in browser JS is reachable by any other plugin's script on the same screen.
  WordPress admin screens routinely load third-party JS; Framer's iframe sandbox does not.
- `wp_options` is not public, but it is not encrypted either, and DB dumps leak. The
  refresh token is the durable credential — encrypting it means a dump alone is not
  enough to impersonate the studio.

**Consequences.**

- One connection per site, shared by all admins. This matches "one studio per site".
  Multi-author sites share a connection — accepted and documented.
- PHP must proxy every SaaS call (see the REST proxy epic), which is more code than
  calling the SaaS from JS, but it is the only way to keep secrets off the client.
- Rotating refresh tokens force a single-flight lock (BSWP-14); two concurrent
  refreshes could otherwise invalidate the good token and disconnect the site.
- Salt rotation must degrade to a "please reconnect" prompt, never a fatal — site
  owners do rotate salts.
- If neither libsodium nor OpenSSL is available we fall back to base64 and raise an
  admin notice, rather than fataling on a host we cannot control.

## Decision 2 — Distribution surface: **block + shortcode**

**Decision.** Ship a Gutenberg block (`booksurfer/booking-form`) *and* a
`[booksurfer]` shortcode.

**Rationale.** The block is the premium UX and where the effort goes. The shortcode
is the compatibility floor: Classic editor, Elementor, Divi and text widgets are a
large share of real WordPress sites, and they cannot use a block. The shortcode is
cheap because it shares one code path — `booksurfer_build_embed_url()` and
`booksurfer_render_embed()` — with the block's `render_callback`.

**Consequences.**

- Shortcode defaults must mirror block attribute defaults exactly, or the same
  selection would render differently through the two surfaces.
- The settings page and block sidebar both expose a copy-to-clipboard shortcode.
- Two entry points is *not* two implementations; any divergence is a bug.

## Decision 3 — Distribution channel: **WordPress.org public repository**

**Decision.** Target the public WordPress.org plugin directory.

**Rationale.** Discovery. Studio owners search the plugin directory; a self-hosted
zip has no distribution story and would need us to build an update server.

**Consequences — this decision has the widest blast radius, and all of it is IN scope:**

- `readme.txt` with all required headers, a ≤150-char short description, FAQ,
  changelog and screenshots (BSWP-42).
- A GPL dependency audit. Every dependency must be GPL-compatible. All current JS
  deps are MIT, which is compatible.
- Unminified sources must be available in the public repo; the shipped `.zip`
  excludes `node_modules/`, `src/` and dotfiles.
- The plugin is GPL-2.0-or-later while the Book Surfer SaaS remains proprietary. The
  readme must disclose that the plugin calls an external service, and link its
  privacy policy and terms — .org requires this.
- Plugin-review prep is in scope: the review checks prefixes, escaping, nonces,
  capability checks and text domain, which is why PHPCS/PHPStan are wired up from the
  empty repo rather than retrofitted (BSWP-10).

## Decision 4 — SaaS capability: **confirmed; we implement the routes ourselves**

**Decision.** booksurfer.in gains a `wordpress` variant of the existing
host-agnostic `/connect/:host` route, and `POST /api/oauth/plugin/exchange` is
confirmed to work server-to-server. We own that work rather than waiting on another
team.

**Rationale.** This is the only hard external dependency in the project. Per
`backend-connect-setup.md`, `/connect/:host` is already parameterised and the token
flow deliberately avoids cookies, so this should reduce to allowlisting `wordpress`
in the host validator plus hardening the polling contract — not a new auth system.

**Consequences.**

- BSWP-3 may re-point down to a config change if the host-agnostic route landed as
  designed.
- The exchange endpoint must tolerate a caller with no browser `Origin`, no CORS
  preflight and no session cookie: `state` is the only credential.
- Rate limiting must be tuned for a polling client (429 + `Retry-After`).
- **Until the SaaS work lands, all plugin development and testing runs against the
  BSWP-5 stub** (`stub/server.js`), which reproduces the contract exactly. This is
  what stops the WordPress side being blocked behind the SaaS side.

---

## Supporting decisions

### Admin/block UI: port the existing Tailwind, scoped

We keep the ported Tailwind UI rather than rebuilding on `@wordpress/components`,
because it lets `ClassesTab`/`LayoutSelector`/`ConnectTab` port with no behavioural
change. Tailwind is made safe for wp-admin with three settings (BSWP-27):
`preflight: false`, `prefix: 'bs-'`, and `important: '.booksurfer-root'`. Preflight
is the real hazard — its global reset would wreck wp-admin and the editor canvas.
The front end ships zero Tailwind; the booking form is an iframe.

We use Tailwind **v3**, not the v4 the Framer plugin uses: v3's classic config
exposes `prefix`, `important` and `corePlugins.preflight` directly, which is exactly
the scoping this decision needs. `@wordpress/components` is still used for genuinely
native controls (Inspector panels, Notice, Modal) so they inherit editor styling.

### Build: two independent entry points, no shared chunk (BSWP-39)

The build (`@wordpress/scripts`, replacing Vite) emits two entries — `build/block/index`
and `build/admin/index` — and **deliberately does not split `src/shared/` into its own
webpack chunk**, even though both entries import from it. The two entries never load on the
same screen (wp-admin settings vs. the block editor), so a shared chunk would save nothing
at runtime. More importantly, WordPress enqueues each entry from the script handles and
content hash in its own `*.asset.php`; a separate shared chunk would have no `*.asset.php`
entry, would never be enqueued, and the bundle would break at runtime with a missing module.
Each entry therefore inlines the shared code it uses. Shared *source* still lives in one
place (`src/shared/`, aliased `@shared`) — this is purely a bundling decision, not a
duplication of logic. Rationale is mirrored inline in `webpack.config.js`.

This amends the BSWP-39 acceptance criteria, which originally called for a shared chunk;
the AC was updated to match (see the plugin build plan).

### Connect polling target

The browser polls `POST /booksurfer/v1/connect` (with the state) every 2 seconds,
not `GET /status`. `/status` stays cheap and `edit_posts`-readable for editor boot;
*advancing* the exchange writes tokens and therefore requires `manage_options`.
Splitting them keeps the capability boundary honest. PHP makes one bounded exchange
attempt per poll and never long-polls — long-polling would pin a PHP-FPM worker per
connecting admin and exhaust the pool on shared hosting.

### Icons

`@wordpress/icons`, not `lucide-react` — one set, consistently, matching wp-admin.

### Embed-URL styling params

The URL builder emits `primary`, `text`, `bg`, `font`, `radius` — the names the SaaS
actually honours (per `BookingForm.tsx`). The Webflow adapter's
`primaryColor`/`textColor`/`fontFamily` are silently ignored by the backend; that is
a latent bug in the Framer/Webflow repo and is deliberately **not** reproduced here.

---

## Backlog impact

- BSWP-3 may re-point from 5 to a config change (verify against BOOK-18 first).
- BSWP-42 stays at full size: decision 3 puts `readme.txt`, the GPL audit and
  review prep in scope.
- BSWP-27 stays a scoping task rather than becoming a `@wordpress/components`
  migration.
