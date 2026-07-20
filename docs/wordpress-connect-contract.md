# WordPress connect contract

**Ticket:** [BSWP-5](https://aivorntech.atlassian.net/browse/BSWP-5) · **Related:** BOOK-18, `backend-connect-setup.md`

This is the contract the WordPress plugin codes against. A runnable stub implementing
all of it lives at [`stub/server.js`](../stub/server.js) — start it with `npm run stub`
so plugin work proceeds while the real SaaS routes are in flight.

## Hosts

| Name | Value | Called by |
|---|---|---|
| `APP_URL` | `https://booksurfer.in` | the **browser** (consent popup) |
| `API_URL` | `https://api.booksurfer.in/api` — **already includes `/api`** | **PHP**, server-to-server |

Both are constant + filter only (`booksurfer_app_base`, `booksurfer_api_base`). No
user-supplied host ever reaches `wp_remote_*` — that is the SSRF guard.

## Flow

```
browser                    WordPress (PHP)                booksurfer.in / api
   |                             |                                |
   |-- POST /connect {} -------->|                                |
   |<-- { state } ---------------|  mints 64-hex state, stores    |
   |                             |  it in a 10-min transient      |
   |                                                              |
   |-- window.open(APP_URL/connect/wordpress?state&origin) ------>|
   |                                              user approves --|
   |                                                              |
   |-- POST /connect {state} --->|                                |
   |    (every 2s, 120s cap)     |-- POST /oauth/plugin/exchange->|
   |                             |<-- 202 | 200 {tokens} | 410 ---|
   |<-- {pending} | {connected, studio} ---------------------------|
   |     never a token           |  stores tokens server-side     |
```

**PHP never long-polls.** One bounded attempt (`timeout => 10`) per browser poll.
Long-polling would pin a PHP-FPM worker per connecting admin.

## Endpoints

### `GET {APP_URL}/connect/wordpress`

The consent page. Opened in a 520×680 browser popup.

| Param | Required | Notes |
|---|---|---|
| `state` | yes | 64 lowercase hex chars, minted **server-side** by the plugin. Opaque, length-bounded, single-use. |
| `origin` | yes | The requesting site URL. Displayed on the consent screen so the studio owner can see who is asking. |
| `prompt` | no | `login` forces a fresh sign-in (used after an explicit disconnect). |

- Unknown host values → `400`, never a fall-through to a default.
- On approve, the pending grant is keyed by `state` and becomes retrievable by the
  exchange endpoint.
- Deny → clear terminal screen; the `state` is burned.
- `state` TTL: **10 minutes** (the stub and plugin both use this).

### `POST {API_URL}/oauth/plugin/exchange`

Polled from PHP. **No auth header** — `state` is the only credential.

Request: `{ "state": "<64-hex>" }`

| Status | Meaning | Body |
|---|---|---|
| `202` | Pending — not yet approved. Keep polling. | `{ "pending": true }` |
| `200` | Approved. | `{ "access_token", "refresh_token", "expires_in" }` |
| `410` | Link expired. Terminal. | `{ "error": "expired" }` |
| `429` | Rate limited. | `{ "error": "slow_down" }` + `Retry-After` |
| `400` | Missing/malformed state. | `{ "error": "missing_state" }` |

**Pending / approved / expired are distinguished by status code, never by parsing a
message string.**

Requirements for the server-side caller:

- Succeeds with **no browser `Origin` header** and **no CORS preflight**.
- Does **not** depend on a session cookie.
- `state` is **burned on first successful exchange**. A replay returns `202`/`410`/`400`
  and **never a second token pair**.
- Rate limit tuned for a polling client: ~1 request / 2s per `state`, hard cap ~60
  attempts, `429` + `Retry-After`.

### `POST {API_URL}/oauth/plugin/refresh`

Request: `{ "refresh_token": "<rt>" }` · No auth header.

| Status | Meaning | Plugin behaviour |
|---|---|---|
| `200` | New token set: `{ access_token, refresh_token, expires_in }` | Store both; the new refresh token replaces the old one atomically. |
| `4xx` | `invalid_grant` / revoked — the grant is genuinely dead. | Clear the connection, set `needs_reconnect`. |
| `5xx` / network | The SaaS is having a bad day. | **Keep the connection**, surface a transient error. A blip must not disconnect every site. |

> **Refresh tokens rotate on every use.** This is why the plugin serialises refresh
> with a single-flight lock (BSWP-14): two concurrent refreshes would invalidate the
> good token and disconnect the site.

### `POST {API_URL}/oauth/plugin/revoke`

Request: `{ "refresh_token": "<rt>" }` · Response `200`, best effort.

The plugin clears local state **even if this call fails or times out** — a user who
clicks Disconnect must never be stuck connected because booksurfer.in was briefly down.

### `GET {API_URL}/widget/classes`

Header: `Authorization: Bearer <access_token>`

| Status | Body |
|---|---|
| `200` | `{ "success": true, "classes": ClassInfo[], "studio": StudioInfo }` |
| `401` | `{ "error": "unauthorized" }` → the plugin refreshes once and replays once. |

```ts
ClassInfo  { id, name, class_type, instructor, price, currency?, location, embed_url }
StudioInfo { handle, name, location?, bio?, address?, email? }
```

The proxy passes this through **unchanged in shape**.

## Token lifetimes

| Token | Lifetime | Rotates? |
|---|---|---|
| `access_token` | `expires_in` seconds; **30 min** (1800) in practice, and the plugin's fallback when `expires_in` is absent | no |
| `refresh_token` | long-lived, durable | **yes — on every refresh** |
| `state` | 10 min, single-use | n/a (burned) |

The plugin refreshes when `expires_at - 60s <= now`.

## Error body shape

All errors: `{ "error"?: string, "message"?: string }`. The plugin **never echoes the
raw upstream body** to the browser — it normalizes to `{ code, message, status }`
with stable machine codes:

| Code | HTTP | Meaning |
|---|---|---|
| `booksurfer_not_connected` | 403 | No connection stored. |
| `booksurfer_reauth_required` | 401 | Grant revoked/expired; reconnect needed. |
| `booksurfer_upstream_error` | 502 | SaaS unreachable or 5xx. |
| `booksurfer_rate_limited` | 429 | Too many connect attempts. |
| `booksurfer_link_expired` | 400 | Consent link expired (410 upstream). |
| `booksurfer_unknown_state` | 400 | State was not minted by this site. |
| `booksurfer_bad_state` | 400 | State failed format validation. |

## Iframe URL contract — **no SaaS change needed**

The front end renders a plain public iframe. These URLs require no auth and keep
working after a disconnect.

| Variant | URL |
|---|---|
| Single class booking | `{API_URL}/{handle}/book/{classId}?layout=…` |
| All classes (sidebar) | `{API_URL}/{handle}/book` |
| All classes (dropdown) | `{API_URL}/{handle}/book?style=dropdown` |
| All-classes schedule | `{API_URL}/{handle}/schedule` |
| Single-class schedule | `{API_URL}/{handle}/schedule/{classId}` |

For a single class the plugin prefers the SaaS-provided `ClassInfo.embed_url` as the
base and appends params to it.

### Styling params (booking forms only — schedules take none)

| Param | Value |
|---|---|
| `layout` | `classic` \| `modern` \| `minimal` \| `compact` |
| `style` | `dropdown` (all-in-one only; `sidebar` emits no param) |
| `primary` | hex, **no leading `#`** |
| `text` | hex, no `#` |
| `bg` | hex, no `#` |
| `font` | `Roboto` \| `Inter` \| `Poppins` \| `Lato` \| `Montserrat` \| `Open Sans` |
| `radius` | integer |

- **Canonical order:** `layout|style, primary, text, bg, font, radius`.
- **Encoding:** `application/x-www-form-urlencoded` — spaces are `+`, not `%20`
  (`Open Sans` → `Open+Sans`). PHP `urlencode()` and JS `URLSearchParams` agree here;
  `rawurlencode()` does **not** and would break parity.
- `padding` is client-side CSS only and is **never** sent to the SaaS.

> These are the names the backend's `parseTheme` honours. The Framer repo's Webflow
> adapter sends `primaryColor`/`textColor`/`fontFamily`, which the SaaS silently
> ignores — a latent bug that is deliberately not reproduced here.

## Default sizes

| Variant | Size |
|---|---|
| `classic` | 480×720 (the block default) |
| `modern` | 520×680 |
| `minimal` | 400×500 |
| `compact` | 360×400 |
| all-in-one `sidebar` | 900×680 |
| all-in-one `dropdown` | 440×720 |
| schedule (all) | 1040×640 |
| schedule (single class) | 820×440 |

## Running the stub

```bash
npm run stub        # http://127.0.0.1:5199
```

Point a site at it:

```php
add_filter( 'booksurfer_api_base', fn() => 'http://127.0.0.1:5199/api' );
add_filter( 'booksurfer_app_base', fn() => 'http://127.0.0.1:5199' );
```

Under `wp-env` this is already wired by `stub/mu-plugin-stub.php` (mapped as an
mu-plugin) whenever `BOOKSURFER_STUB` is defined. Note the asymmetry: PHP runs inside
the container (`host.docker.internal`) while the consent popup opens in the host
browser (`localhost`).

`GET /__stub/reset` clears all stub state between tests.
