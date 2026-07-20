# BSWP backlog — verification result and remediation plan

## Context

`bswp-jira-backlog-md-booksurfer-wp-plug-atomic-eclipse.md` defines a 39-ticket build plan for the
Book Surfer WordPress plugin. The in-repo checklist `docs/BUILD-TODO.md` claims **"34 of 39 tickets
landed."** This task audited every ticket against actual code rather than trusting that claim.

**Verdict: 21 of 39 fully verified, 14 partial, 2 not started, 2 blocked.** The code is real and of
genuinely good quality — every file cited by every doc exists, and the docs are honest rather than
aspirational. But the "34" is inflated (the table's own markers total 31 ✅), and — most importantly —
**nothing in this repo has ever been installed, built, committed, or executed.** There is no `.git`,
no `node_modules/`, no `vendor/`, no `build/`, and no lockfiles. Every "verified" below means
*verified by code inspection only*.

The audit also found 8 defects the checklist does not reflect, two of which contradict claims the
docs actively make (BSWP-44 security, BSWP-34 parity).

---

## The tally

| Bucket | Count | Tickets |
|---|---|---|
| ✅ Verified (by inspection) | 21 | 2, 7, 8, 9, 13, 14, 15, 16, 18, 19, 20, 21, 23, 25, 29, 30, 31, 32, 35, 40, 46 |
| 🟡 Partial | 14 | 5, 10, 12, 24, 26, 27, 34, 36, 37, 39, 42, 44, 45, 47 |
| ⬜ Not done | 2 | 41 (no `.pot`), 48 (empty template) |
| 🔴 Blocked | 2 | 3, 4 (need booksurfer.in repo) |

### Where the checklist overstates

| Ticket | Claimed | Actual | Gap |
|---|---|---|---|
| BSWP-41 | 🟡 | ⬜ | `languages/` holds only `README.md`; `makepot` never run. Plus untranslated strings in `format.ts:49,54,72` and `config.ts:53-64`. |
| BSWP-37 | ✅ | 🟡 | "AMP/feed/REST render nothing" not implemented — the `is_feed()`/`REST_REQUEST` check at `helpers-embed.php:407` only guards the *misconfigured notice*; a valid embed renders fully in feeds. Zero `is_amp`/`amp_is` hits anywhere. |
| BSWP-26 | ✅ | 🟡 | "connected-since" does not exist — absent from `types.ts:36-42` and `class-admin.php:146-160`. |
| BSWP-24 | ✅ | 🟡 | Polls `POST /connect`, not `/status` as the AC specifies. `needs-reconnect` isn't a hook state. |
| BSWP-39 | ✅ | 🟡 | No shared chunk (`webpack.config.js:27-31` declines to split); uses v3 `tailwindcss: {}` not `@tailwindcss/postcss`. Both deliberate — reconcile the AC or the code. |
| BSWP-12 | ✅ | 🟡 | Crypto is correct, but the required degradation **admin notice** doesn't exist; `is_degraded()` surfaces only inside the React app. |
| BSWP-45 | ✅ | 🟡 | No test for `class-block.php`; ≥80% coverage AC not gated in `phpunit.xml.dist`. |
| BSWP-47 | ✅ | 🟡 | No 401-replay E2E; "disconnect w/ published block" actually uses a *shortcode* with a hardcoded handle. |
| BSWP-5 | ✅ "verified end-to-end" | 🟡 | Cannot be true — nothing has been executed. No Postman collection. |

---

## Defects to fix (ranked)

1. **`embedUrl` bypasses the allowlist** — `helpers-embed.php:216-218` accepts any value starting
   with `http` verbatim as the iframe base, skipping the `studioHandle`/`classId` allowlists.
   `esc_url` constrains protocol but not host, so an authored/imported block can point an
   `allow="payment"` iframe at any origin. **Contradicts BSWP-44's SSRF claim.** Fix: check host
   against `booksurfer_api_base()`.

2. **Parity gate has a hole at exactly the bug it exists to prevent** — JS accumulates
   `pairs: Array<[string,string]>` (`embed.ts:87`), PHP an assoc array (`helpers-embed.php:205`).
   A pre-existing colliding key diverges: `embed_url = ".../book/42?layout=modern"` → PHP
   overwrites (`?layout=classic`), JS duplicates (`?layout=modern&layout=classic`). `embed_url`
   comes straight from the SaaS (`edit.tsx:111`), so this goes live the day the SaaS adds a styling
   param. The only fixture case (`embed-urls.json:91`) uses non-colliding `ref=xyz`. Also:
   `parse_str` mangles `a.b`/`a[]`/repeated keys; `radius` coercion differs on `"1e3"`/`true`.
   Fix: align data structures, add fixture cases for each.

3. **`booksurfer_resolve_dimensions()` emits warnings and returns 0×0** — `helpers-embed.php:345,357`:
   `isset($layouts[$atts['layout'] ?? 'classic'])` makes `isset` true when `layout` is unset, then
   reads the undefined key → two warnings → `width:0px;height:0px`. Contradicts the file's own
   ":369-370" contract. JS twin handles it via `hasKey` (`embed.ts:188,195`). Masked only because
   callers always set the key; the fixture never calls this function.

4. **Disconnect modal's promise is false for shortcodes** — `ConnectedPanel.tsx:88` says published
   forms keep working. `shortcode.ts:73` only emits `handle=` when `studioHandle` is passed, and
   `ClassesPanel.tsx:39,97,112` never passes it → after disconnect the fallback to `get_studio()`
   returns null → renders nothing. True for blocks, false for every shortcode the UI generates.

5. **`clear_cache()` deletes the wrong key on disconnect** — `class-rest.php:274-277` revokes before
   clearing cache, so `cache_key()` derives from a now-null studio. Masked by `flush_transients()`.
   Swap the two calls.

6. **`apiBase` never sent to the admin app** — `embed.ts:44` reads `window.BookSurferAdmin.apiBase`;
   `class-admin.php:146-160` never sets it (only `class-block.php:80` does). Sites filtering
   `booksurfer_api_base` get correct PHP URLs and hardcoded prod URLs from admin JS.

7. **Tailwind utilities in the Inspector are dead** — `important: '.booksurfer-root'` scopes rules
   under that ancestor, but `InspectorControls` renders through a Slot outside it. `Inspector.tsx:81,182,185`
   never apply. `edit.tsx` wraps correctly; the Inspector doesn't.

8. **Test-order fragility** — `test-lifecycle.php:129` depends on `require_once uninstall.php`
   running file-scope code on first require; reordering the methods silently guts the test.

Minor: `uninstall.php` has no ABSPATH guard (correct by design, but BSWP-44 says "every PHP file");
block/shortcode dimension defaults diverge for `variant=schedule` (480×720 vs 1040×640); `ci.yml`
uses `npx wait-on` without declaring it; state validator `[A-Za-z0-9_-]{32,64}` is looser than the
doc's "64 lowercase hex".

---

## Remediation plan

**Phase 1 — Make it real (blocks everything else).**
`git init` + first commit. `npm install && composer install`, commit lockfiles. `npm run build` —
this is also the only way to settle the unverified `build/block/index.css` filename assumption
(`block.json:84`), which currently fails *silently* behind `file_exists`. Then run the gates that
have never run: `npm run test:unit`, PHPUnit via wp-env, `npm run test:e2e` with `npm run stub`.
Expect failures; they're unmeasured, not absent.

**Phase 2 — Fix defects 1–3** (security + both parity holes), each with a regression test.
Defect 2 needs new fixture cases in `tests/fixtures/embed-urls.json`; defect 3 needs
`resolve_dimensions` brought under the fixture at all.

**Phase 3 — Close the overstated tickets.** BSWP-41 (`npm run makepot`, wrap `format.ts`/`config.ts`
strings), BSWP-12 (degradation admin notice), BSWP-37 (feed/REST/AMP guard), BSWP-26
(connected-since through `types.ts` + `bootstrap_data()`), then defects 4–8.

**Phase 4 — Reconcile docs to reality.** Fix `BUILD-TODO.md:8` (34 → real count), drop BSWP-5's
"verified end-to-end", and decide whether BSWP-39's no-shared-chunk / Tailwind-v3 deviations amend
the AC or the code.

**Phase 5 — Human-gated, cannot be automated.** BSWP-44 reviewer signature, BSWP-48 compat matrix
pass, BSWP-27 visual regression, BSWP-42 screenshots + real terms/privacy URLs + release workflow,
BSWP-10 branch protection. BSWP-3/4 stay blocked pending the SaaS repo.

## Verification

The plan's own §Verification (lines 195-208) is the acceptance test and is currently unrunnable at
step 1. The gate that matters most is the PHP↔JS embed-URL parity fixture (BSWP-34/46) — and per
defect 2 it currently passes while the builders genuinely diverge, so fixing the gate is a
prerequisite to trusting it. Success = the 10-step checklist green on `wp-env` at `WP_DEBUG=true`
with zero notices, which defect 3 would currently violate.
