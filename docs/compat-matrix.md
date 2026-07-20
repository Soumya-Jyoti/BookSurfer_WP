# Compatibility matrix

**Ticket:** [BSWP-48](https://aivorntech.atlassian.net/browse/BSWP-48)
**Version:** 1.0.0
**Status:** 🟡 **Not yet run.** This is the manual pass before ship — fill it in and sign off.

Legend: ✅ pass · ❌ fail (link an issue) · ⬜ not yet run · N/A not applicable

Run every row with `WP_DEBUG = true` and `WP_DEBUG_DISPLAY = false`, checking
`debug.log` afterwards. **Zero notices is the bar**, not "it looked fine."

## The happy path

Unless a row says otherwise, "full happy path" means: activate → connect → browse
classes → insert block → pick class + layout → publish → front-end iframe renders →
disconnect.

---

## PHP versions

Automated in CI (`phpunit` matrix), but activation + a real happy path is manual.

| PHP | Activates cleanly | Full happy path | Zero notices | Result |
|---|---|---|---|---|
| 7.4 (declared minimum) | ⬜ | ⬜ | ⬜ | ⬜ |
| 8.1 | ⬜ | ⬜ | ⬜ | ⬜ |
| 8.3 | ⬜ | ⬜ | ⬜ | ⬜ |

> Watch on 7.4: the plugin uses `??` and `?:` freely (fine) but **no** arrow-function
> `fn()` in shipped code paths, no union types, no `match`. PHPCS's
> `PHPCompatibilityWP` with `testVersion 7.4-` is the automated guard.

## WordPress versions

| WordPress | Block | Shortcode | Settings page | Result |
|---|---|---|---|---|
| 6.4 (declared minimum) | ⬜ | ⬜ | ⬜ | ⬜ |
| Current stable | ⬜ | ⬜ | ⬜ | ⬜ |

## Editors

| Editor | Insert | Live preview | Sidebar controls | Result |
|---|---|---|---|---|
| Block editor (post/page) | ⬜ | ⬜ | ⬜ | ⬜ |
| Site Editor (iframed canvas) | ⬜ | ⬜ | ⬜ | ⬜ |
| Classic editor (shortcode) | N/A | N/A | N/A | ⬜ |

> The Site Editor row matters: its canvas is itself an iframe, so a preview iframe
> nested inside it is the case most likely to break (BSWP-31).

## Page builders — the shortcode path

| Builder | Mechanism | Renders | Result |
|---|---|---|---|
| Elementor | Shortcode widget | ⬜ | ⬜ |
| Divi | Code module | ⬜ | ⬜ |
| Classic text widget | Widget | ⬜ | ⬜ |

## Themes

| Theme | Type | Block | Shortcode | Result |
|---|---|---|---|---|
| Twenty Twenty-Four (or later) | Block theme | ⬜ | ⬜ | ⬜ |
| Twenty Twenty-One | Classic theme | ⬜ | ⬜ | ⬜ |

## Multisite

The plugin declares multisite **supported**, one connection per site (readme FAQ).

| Scenario | Expected | Result |
|---|---|---|
| Network-activate | Activates on every site, no notices | ⬜ |
| Per-site connections independent | Site A connecting does not connect Site B | ⬜ |
| Per-site class cache isolated | Site A never sees Site B's classes | ⬜ |
| Network uninstall | Zero `booksurfer_*` rows on **every** site (verify by DB query) | ⬜ |

> If any row fails and is not economically fixable, the fallback is to declare
> multisite unsupported in `readme.txt` — but the code is written for it
> (`uninstall.php` iterates sites; caches are per-site), so fix rather than declare.

## Conflict smoke tests

| Plugin | What to check | Result |
|---|---|---|
| WooCommerce active | No style bleed either direction on the settings screen, inserter, or block sidebar (BSWP-27); no JS console errors | ⬜ |
| A caching plugin (e.g. W3TC / WP Super Cache) | Published pages still render the iframe; the admin/status route is not cached; disconnect is not served stale | ⬜ |

> The Tailwind scoping (`preflight: false`, `bs-` prefix, `.booksurfer-root` scope) is
> what these rows are really testing. A WooCommerce-heavy admin is the realistic worst
> case for style collisions.

## Style-bleed visual check (BSWP-27)

Check in **both** directions — our styles must not escape, and admin styles must not
trample ours.

| Surface | Ours escapes? | Theirs tramples? | Result |
|---|---|---|---|
| Book Surfer settings screen | ⬜ | ⬜ | ⬜ |
| Block inserter | ⬜ | ⬜ | ⬜ |
| Block sidebar | ⬜ | ⬜ | ⬜ |
| Site Editor | ⬜ | ⬜ | ⬜ |
| WooCommerce admin screens | ⬜ | ⬜ | ⬜ |

## Front-end assertions

| Check | Expected | Result |
|---|---|---|
| Enqueued handles on a page containing the block | **Zero** plugin JS, **zero** plugin CSS | ⬜ |
| iframe attributes | `allow="payment"`, `loading="lazy"`, `title`, pixel width/height | ⬜ |
| Disconnected site with a published block | Still renders | ⬜ |
| Anonymous visitor, misconfigured block | Renders nothing (no notice) | ⬜ |
| Feed / REST context | Renders nothing | ⬜ |

## Sign-off

- [ ] Every row above is ✅ or has a linked issue with an accepted decision.
- [ ] Tested by: ______________________  Date: __________
- [ ] Results committed to this file (replace the ⬜s — do not ship the template).
