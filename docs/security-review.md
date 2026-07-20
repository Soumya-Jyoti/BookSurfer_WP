# Security review — Book Surfer WordPress plugin

**Ticket:** [BSWP-44](https://aivorntech.atlassian.net/browse/BSWP-44)
**Version reviewed:** 1.0.0
**Reviewer:** _(sign here before ship)_
**Date:** _(fill on sign-off)_
**Status:** 🟡 Checklist complete in code; **awaiting human reviewer sign-off**

Run as a single reviewed pass against the WordPress plugin handbook checklist — not as
scattered fixes. Every box below names the file that satisfies it and, where possible,
the automated test that keeps it satisfied.

---

## 1. REST routes: nonce + capability + sanitization, zero exceptions

All routes: `includes/class-rest.php`, namespace `booksurfer/v1`, registered on
`rest_api_init`.

| Route | Method | Capability | Args validated |
|---|---|---|---|
| `/status` | GET | `edit_posts` | `verify` (boolean, sanitized) |
| `/connect` | POST | `manage_options` | `state` (sanitize + `validate_callback`) |
| `/disconnect` | POST | `manage_options` | — |
| `/classes` | GET | `edit_posts` | `refresh` (boolean, sanitized) |

- [x] **Every route has an explicit `permission_callback`.** None uses `__return_true`.
      This is the single most common WordPress plugin security failure, so it is
      asserted structurally rather than by review:
      `Test_Rest::test_every_route_has_a_real_permission_callback()` walks the
      registered route table and fails on a missing/empty/`__return_true` callback.
- [x] **Write routes require `manage_options`; read routes require `edit_posts`.**
      The split is deliberate: block editors must know whether the site is connected,
      but only admins may change the connection. Verified by
      `Test_Rest::test_editor_can_read_but_not_write()`.
- [x] **Nonce verified.** `@wordpress/api-fetch` sends `X-WP-Nonce` automatically and
      WordPress's REST cookie auth (`rest_cookie_check_errors`) rejects any
      cookie-authenticated request without a valid nonce before our callback runs.
      Documented in `README.md` so nobody later "fixes" it by disabling the check.
- [x] **Logged-out and under-privileged requests get 401/403, never data.**
      `Test_Rest::test_logged_out_is_refused_everywhere()`,
      `Test_Rest::test_subscriber_is_refused()`.
- [x] **`state` is format-validated (32–64 `[A-Za-z0-9_-]`) before any outbound call**
      — `OAuth::is_valid_state_format()`, enforced both by the route's
      `validate_callback` and again in `try_exchange()`.
- [x] **PHP cannot be driven to exchange an arbitrary state.** The submitted state must
      `hash_equals()` the server-minted pending state.
      `Test_OAuth::test_unknown_state_is_refused_without_a_request()` proves no request
      is even made.

## 2. Secrets never reach the client

- [x] **Refresh token encrypted at rest** — `includes/class-token-store.php`,
      AES-256-GCM via `openssl_encrypt` (or `sodium_crypto_secretbox`), key derived
      with `hash_hkdf( 'sha256', AUTH_KEY . AUTH_SALT, … )`, random IV/nonce stored
      alongside the ciphertext. `Test_Token_Store::test_refresh_token_is_not_stored_in_plaintext()`.
- [x] **No token in any REST response.** `Test_Rest::test_status_never_returns_a_token()`
      and `Test_Rest::test_connect_response_contains_no_token()` assert the serialized
      body contains no token value.
- [x] **No token in the JS bundle or the inline bootstrap.** `Admin::bootstrap_data()`
      emits only `restBase`, `nonce`, `appBase`, `connectUrl`, `dashboardUrl`, `siteUrl`
      and a token-free status snapshot.
- [x] **No token in any log line or error message.** `booksurfer_log()` is only called
      with fixed strings; upstream bodies are never logged.
- [x] **End-to-end proof:** `tests/e2e/connect.spec.ts` → "no token ever reaches the
      browser" captures every `/wp-json/booksurfer/` response body during a real connect
      and asserts none contains a token.
- [x] **Option does not autoload** (`autoload = no`) so the encrypted blob is not loaded
      into memory on every front-end request. `Test_Token_Store::test_option_does_not_autoload()`.

## 3. Output escaping — at the point of output

- [x] `esc_url()` on every iframe `src` — `booksurfer_render_embed()`.
- [x] `esc_attr__()` on the iframe title; `(int)` cast on width/height. No exceptions.
- [x] `esc_html__()` on every admin notice string.
- [x] `esc_url()` on the dashboard link wherever rendered server-side.
- [x] Block wrapper via `get_block_wrapper_attributes()` (core-escaped).
- [x] `wp_json_encode()` for all inline script data.
- [x] Verified: `Test_Shortcode::test_output_is_escaped()` and
      `Test_Shortcode::test_garbage_attributes_are_ignored()`.

## 4. Input sanitization

- [x] `studioHandle` allowlisted to `^[a-z0-9][a-z0-9-]{0,63}$`; anything else yields an
      empty string and the embed renders nothing (`booksurfer_sanitize_handle()`).
- [x] `classId` allowlisted to `^[A-Za-z0-9_-]{1,64}$` (`booksurfer_sanitize_class_id()`).
- [x] `layout` / `variant` / `allInOneStyle` resolved against a fixed map with a safe
      default — never interpolated raw.
- [x] Shortcode attributes pass through `shortcode_atts()` then per-field sanitization;
      unknown attributes are ignored.

## 5. SSRF

- [x] **No user-supplied host ever reaches `wp_remote_*`.** The base URL is a constant
      plus filter only (`BOOKSURFER_API_BASE` / `booksurfer_api_base`,
      `BOOKSURFER_APP_BASE` / `booksurfer_app_base`), defined in `Plugin::define_constants()`.
- [x] Every outbound path is a literal string appended to that base
      (`/oauth/plugin/exchange`, `/oauth/plugin/refresh`, `/oauth/plugin/revoke`,
      `/widget/classes`).
- [x] The class-variant `embedUrl` attribute is used **only** as an iframe `src` rendered
      in the browser (and `esc_url()`'d). It is never fetched server-side.

## 6. Outbound request hygiene

- [x] `sslverify => true` on **every** outbound request — `OAuth::post_json()`,
      `OAuth::fetch_studio()`, `Api_Proxy::request_classes()`. Asserted by
      `Test_OAuth::test_exchange_sends_only_state_and_no_auth_header()` and
      `Test_Api_Proxy::test_request_shape()`.
- [x] `timeout => 10` on every request; PHP never long-polls, so a connecting admin
      cannot pin a PHP-FPM worker (BSWP-13 design note).
- [x] Identifying `User-Agent`: `BookSurfer-WP/{version}; {site url}`.
- [x] **The raw upstream body is never echoed to the browser** — it can carry internal
      detail. All proxy errors normalize to `{ code, message, status }`.
      `Test_Api_Proxy::test_upstream_500_is_normalized_and_body_not_echoed()`.

## 7. Rate limiting

- [x] `/connect` is rate-limited per user (120 requests / 300s) — `Rest::rate_limit()`.
      This bounds exchange-polling abuse while comfortably allowing the legitimate
      2s-interval / 120s-cap poll (≈60 requests).
- [x] Returns `429 booksurfer_rate_limited`; the client treats 429 as transient and
      keeps polling rather than failing the flow.

## 8. File and lifecycle hygiene

- [x] **`ABSPATH` guard at the top of every PHP file** — `book-surfer.php`,
      `uninstall.php`, all of `includes/`, `stub/mu-plugin-stub.php`.
- [x] No directly-executable files; no work at include time (`Plugin::instance()->run()`
      is called on `plugins_loaded`).
- [x] `uninstall.php` guarded by `WP_UNINSTALL_PLUGIN`.
- [x] **Uninstall removes the encrypted token blob** — leaving a refresh token in the DB
      after uninstall is a security issue. Verified by direct DB query:
      `Test_Lifecycle::test_uninstall_removes_every_trace()` and
      `test_uninstall_removes_the_token_blob()`.
- [x] Every function/class/option/hook uses the `booksurfer_` prefix or the `BookSurfer\`
      namespace — enforced by `phpcs.xml`'s `PrefixAllGlobals`.

## 9. Failure modes reviewed

| Scenario | Behaviour | Covered by |
|---|---|---|
| Salt rotation (`AUTH_KEY` changed) | Connection cleared, "please reconnect" — never a fatal or white screen | `Test_Token_Store::test_salt_rotation_prompts_reconnect()` |
| Ciphertext tampered | Auth tag rejects it; treated as rotation | `Test_Token_Store::test_tamper_detection_clears_connection()` |
| No OpenSSL and no libsodium | base64 fallback + admin notice; never fatal | `Token_Store::encrypt()`, surfaced via `status.degraded` |
| Grant revoked (4xx on refresh) | Connection cleared, `needs_reconnect` set | `Test_OAuth::test_refresh_hard_failure_clears_connection()` |
| SaaS 5xx / network blip on refresh | Connection **kept**; transient error surfaced | `Test_OAuth::test_refresh_5xx_keeps_connection()` |
| Two concurrent refreshes | Exactly one refresh; the loser waits and re-reads | `Test_OAuth::test_single_flight_loser_issues_no_refresh()` |
| Fatal mid-refresh wedges the lock | Stale lock stolen after 15s | `Test_OAuth::test_stale_lock_is_stolen()` |
| Repeated 401s | At most one replay; no recursion | `Test_Api_Proxy::test_second_401_does_not_recurse()` |
| SaaS down during disconnect | Local state cleared anyway | `Test_OAuth::test_revoke_clears_local_state_even_when_remote_fails()` |

## 10. Findings from this pass

| # | Finding | Severity | Resolution |
|---|---|---|---|
| 1 | `OAuth::refresh()` treated **any** non-200 as a revoked grant, so a transient SaaS 5xx would clear the connection and disconnect every site during an outage. | High (availability) | Fixed: only 4xx clears the connection; 5xx/network returns a transient `booksurfer_upstream_error` and keeps the connection. Regression-locked by `test_refresh_5xx_keeps_connection()`. |
| 2 | The JS embed builder used the `in` operator for layout/style lookup, which walks the prototype chain — `layout="constructor"` would pass validation in JS (and reach the URL) while PHP's `isset()` rejected it. A parity break and a value-smuggling vector. | Medium | Fixed: own-property lookup via `Object.prototype.hasOwnProperty.call()`. Locked by two golden-fixture cases. |
| 3 | The block declared a `className` attribute, which collides with core's `customClassName` support. | Low (correctness) | Renamed to `classLabel`. |

## Sign-off

- [ ] Reviewed by: ______________________  Date: __________
- [ ] All items above re-verified against the shipped `1.0.0` tag.
- [ ] `npm run lint:js`, `composer run lint`, `composer run analyse`, `composer run test`, `npm run test:unit`, `npm run test:e2e` all green.
