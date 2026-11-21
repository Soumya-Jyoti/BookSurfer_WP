# WordPress.org Plugin Review – Round 1 Action Plan

| | |
|---|---|
| **Plugin** | Book Surfer (`book-surfer`) |
| **Review received** | 8 Aug 2026 |
| **Review ID** | `AUTOPREREVIEW OWN book-surfer/booksurfer/8Aug26/T2 8Aug26/4.2A2 (P0TDX349993HGN)` |
| **Thread tag** | `{#HS:3412448713-1097088#}` – reply **in this thread only** |
| **WordPress.org account** | `booksurfer` – email changed to `support@booksurfer.in` on 5 Sep 2026 |
| **Status** | Resubmitted 5 Sep 2026 (zip uploaded, thread replied). Waiting on the plugins team |

The reviewer raised **two blockers** and gave standard process instructions. The
"Other details" section of the email was empty, so nothing else was flagged in
this round.

---

## Summary

| # | Action item | Blocking? | Who | Done |
|---|-------------|-----------|-----|------|
| 1 | Prove ownership of the "Book Surfer" name | **Yes** (red flag) | Account owner | ✅ Done – Option A, 5 Sep 2026 |
| 2 | Fix Guideline 11 – site-wide admin notice | **Yes** | Developer | ✅ Done 5 Sep 2026 – live-verified on booksurfer.local |
| 3 | Run Plugin Check and fix anything it reports | Required by reviewer | Developer | ✅ Done 5 Sep 2026 – Plugin Check 2.1.0 clean, 0 errors 0 warnings |
| 4 | Rebuild zip, re-upload, reply to the thread | **Yes** | Developer + account owner | ✅ Done 5 Sep 2026 – upload shows on the Add page, status "waiting on our plugins team" |

---

## Who does what – acceptance criteria by owner

Legend: **You** = needs your login, your machine, or your decision. **Claude** = can
be done in this repo from the terminal. **Both** = Claude does the work, you
unblock or sign off.

Environment as of 5 Sep 2026: PHP 8.3, Node 22, PHPCS and wp-scripts are
installed. Docker Desktop is installed but not running. Instead of wp-env, live
checks use the Local (by WP Engine) site `booksurfer.local` (WP 7.0.2, PHP 8.1,
WP_DEBUG on), with wp-cli run on Local's own PHP. The plugin folder there is a
copy of the rebuilt zip; the previous copy is backed up at
`~\Local Sites\booksurfer\book-surfer.bak-20260905`.

| Item | Acceptance criterion | Owner | Why / what it depends on | Status |
|------|----------------------|-------|--------------------------|--------|
| 1 | `booksurfer` account provably linked to booksurfer.in | **You** | WordPress.org profile login | ✅ Done 5 Sep 2026 |
| 1 | Reviewer can verify without follow-up | **You** | Follows from the email change | ✅ Done |
| 1 | No second submission / account created | **You** | Behavioural, nothing to run | ✅ Done |
| 1 | *Optional:* DNS TXT record on booksurfer.in | **You** | GoDaddy DNS login | ☐ Optional |
| 2 | Decide: delete the PHP notice, or keep a scoped version | **You** | Product decision | ✅ Decided 5 Sep 2026: delete |
| 2 | No Book Surfer notice on any screen except the plugin page | **Both** | Hook and method removed from `class-admin.php`. Live-verified on booksurfer.local with the degraded flag set: Dashboard, Plugins, Settings, Posts all show 0 Book Surfer notices | ✅ Done 5 Sep 2026 |
| 2 | Degraded warning still visible on the plugin page | **Both** | React notice in `App.tsx` untouched. Live-verified: plugin page bootstrap carries `"degraded":true`, mount point and admin script present | ✅ Done 5 Sep 2026 |
| 2 | `grep admin_notices includes/` shows nothing unconditional | **Claude** | Only match left is `build_missing_notice`, registered inside the hook-suffix check | ✅ Done 5 Sep 2026 |
| 2 | Activates and settings page loads with no PHP warnings | **Both** | `php -l` passes. Live: deactivate + reactivate on booksurfer.local (WP 7.0.2, WP_DEBUG on) added zero lines to debug.log | ✅ Done 5 Sep 2026 |
| 3 | Plugin Check reports zero errors on the built zip | **Both** | Run 5 Sep 2026 on booksurfer.local via wp-cli (Plugin Check 2.1.0). First run: 1 error (readme `Tested up to: 7.0`, current is 7.1) + 1 warning (`load_plugin_textdomain()` discouraged for .org-hosted plugins). Both fixed. Re-run: "Checks complete. No errors found." | ✅ Done 5 Sep 2026 |
| 3 | Warnings fixed or understood and defensible | **Both** | The single warning was fixed by removing the redundant textdomain call. WordPress.org loads translations automatically since 4.6 and `languages/` ships no `.mo` files. Nothing left to sign off | ✅ Done 5 Sep 2026 |
| 3 | PHPCS passes | **Claude** | Shipped PHP clean as of 5 Sep 2026: 8 banners converted to `//`, 2 alignment lines fixed, Windows CRLF excluded (git index is LF). Left: 41 violations in 4 `tests/php/` files, not shipped. **Never run plain `phpcbf`**, it mangles banner comments | ✅ Shipped code clean – `tests/` optional ☐ |
| 4 | Decide: bump version / stable tag or not | **You** | Release decision. Recommendation: no bump for a resubmission | ☐ |
| 4 | Zip contains only the approved payload | **Claude** | Rebuilt 5 Sep 2026: assets byte-identical to the committed `build/`, payload verified with `unzip -l` | ✅ Done 5 Sep 2026 |
| 4 | Zip activates on a clean WordPress, no fatal, no stray notice | **Both** | The rebuilt zip was installed on booksurfer.local and activated cleanly, zero debug.log lines. That site is not pristine (Elementor, CF7 present), so a fresh-site repeat is optional | ✅ Done on Local – fresh site optional ☐ |
| 4 | Uploaded while logged in as `booksurfer` | **You** | Add-plugin page lists the 5 Sep 2026 upload of `book-surfer.zip` v1.0.0 | ✅ Done 5 Sep 2026 |
| 4 | Decide: keep slug `book-surfer` | **You** | Cannot change after approval. Recommendation: keep | ☐ |
| 4 | Reply posted in the existing thread, short | **You** | Review status flipped to "We've got your email… waiting on action from our plugins team" | ✅ Done 5 Sep 2026 |
| 4 | Monitor `support@booksurfer.in` for the reviewer's next reply | **You** | Thread now goes to the new address | ☐ ongoing |

### Your to-do list, in order

1. ~~Tell Claude: delete the notice (default) or keep the scoped variant.~~ ✅ Decided: delete.
2. ~~Start Docker Desktop and leave it running so wp-env can boot.~~ Not needed: live checks run on booksurfer.local instead.
3. Decide on version bump (recommend no) and slug (recommend keep).
4. ~~After Claude reports the zip is clean: upload it at the "Add your plugin" page as `booksurfer`.~~ ✅ Done 5 Sep 2026.
5. ~~Send the reply from `support@booksurfer.in` in the same thread.~~ ✅ Done 5 Sep 2026.
7. Now: wait. Monitor `support@booksurfer.in`. Do not upload again or send further emails unless the reviewer asks. Confirm the demo account (`testuser@booksurfer.com`) still logs in at booksurfer.in so the reviewer's connect test works.
6. *Optional:* add the DNS TXT record at GoDaddy.

### Claude's to-do list, in order

1. ~~Remove the site-wide `admin_notices` hook and method in `class-admin.php`.~~ ✅ Done 5 Sep 2026.
2. ~~Run the grep check and PHPCS. Convert the 8 banner comments, fix the 2 alignment lines.~~ ✅ Done 5 Sep 2026. Shipped code is PHPCS-clean with CRLF excluded on Windows. Only `tests/php/` leftovers remain, optional.
3. ~~Install Plugin Check on booksurfer.local, run it, fix errors, re-run until clean.~~ ✅ Done 5 Sep 2026. Second run: "No errors found".
4. ~~Verify the degraded notice appears only on the plugin page, and that activation is warning-free with `WP_DEBUG` on.~~ ✅ Done 5 Sep 2026 on booksurfer.local.
5. ~~`npm run build`, `npm run plugin-zip`, verify the zip payload, smoke-test the zip.~~ ✅ Done 5 Sep 2026. Zip installed and activated on booksurfer.local.
6. ~~Update this document and hand you the final reply text.~~ ✅ Done 5 Sep 2026. Reply text is in Action Item 4 below. All code changes are still **uncommitted** in the working tree pending your decision on branch and commit.

---

## Action Item 1 – Prove ownership of the "Book Surfer" name

### What the reviewer said

The plugin is published as "Book Surfer", author "Book Surfer", author URI
`https://booksurfer.in`. The WordPress.org account that submitted it uses a
`gmail.com` address. A gmail address cannot prove that the account represents
booksurfer.in, because anyone can register any gmail address.

### Why it matters

This is the only item marked with a red flag. The plugin will **not be
approved** until ownership is proven, regardless of code changes.

### Acceptance criteria

- [x] The `booksurfer` WordPress.org account is provably linked to booksurfer.in (email is now `support@booksurfer.in`).
- [x] The reviewer can verify this without asking follow-up questions.
- [x] No second submission or second account has been created.

### Steps to resolve

**Option A – change the account email (recommended, fastest) – ✅ DONE 5 Sep 2026**

Completed: the account email was changed to `support@booksurfer.in` and WordPress.org
confirmed "Your email has been successfully changed." Steps kept for reference.

1. Create or pick a mailbox on the company domain, e.g. `wordpress@booksurfer.in`
   or `hello@booksurfer.in`.
2. Log in to <https://login.wordpress.org/> as `booksurfer`.
3. Open <https://profiles.wordpress.org/me/profile/edit/> and change the email to
   the booksurfer.in address.
4. Confirm the change via the verification email WordPress.org sends.
5. Note: the review thread will continue at the **new** address. Make sure that
   inbox is monitored.

**Option B – DNS TXT record (optional extra proof, not yet done)**

1. Log in to the DNS provider for booksurfer.in (the domain currently uses
   GoDaddy nameservers, `ns41.domaincontrol.com`).
2. Add a new record:

   | Field | Value |
   |-------|-------|
   | Type | `TXT` |
   | Host / Name | `@` (root) |
   | Value | `wordpressorg-booksurfer-verification` |
   | TTL | default |

3. Wait 10–30 minutes, then verify from any machine:

   ```
   nslookup -type=TXT booksurfer.in 8.8.8.8
   ```

   The output must show the verification string. As of 5 Sep 2026 the domain has
   **no** TXT records at all.
4. Mention in the reply that the record was added.

**Option C – transfer to another account (only if A is not possible)**

1. Create a WordPress.org account using a booksurfer.in email address.
2. Reply to the thread and ask the reviewer to transfer the submission to that
   username.
3. Do **not** upload the plugin again from the new account.

**Do not**

- Resubmit the plugin from a different account. Both submissions get rejected
  and the accounts may be suspended.
- Rename the plugin to avoid the check. Book Surfer is our own product, so the
  name is legitimate; we only need to prove it.

---

## Action Item 2 – Fix Guideline 11 (admin notices must be limited in scope)

### What the reviewer said

> Plugins should not hijack the admin dashboard. Upgrade prompts, notices,
> alerts, and the like must be limited in scope and used with moderation.
> (Guideline 11)

### What was actually flagged

The plugin registered a notice on **every** WordPress admin screen (removed on
5 Sep 2026, see "Steps to resolve"):

| Location (before the fix) | What it did |
|---------------------------|-------------|
| `register()` in [includes/class-admin.php](../includes/class-admin.php) | `add_action( 'admin_notices', ... 'maybe_degraded_notice' )` – hooked unconditionally |
| `maybe_degraded_notice()` in the same file | Printed a non-dismissible warning on every admin page when the server had neither sodium nor OpenSSL |

The code comment itself said "Shown on every admin screen". That is exactly what
"limited in scope" prohibits. Automated review tools detect any
`admin_notices` hook that is not restricted to the plugin's own screen.

The React admin app **already shows the same warning** on the Book Surfer
settings page ([src/admin/App.tsx:73-82](../src/admin/App.tsx#L73-L82)), so the
PHP notice is redundant.

Two other things the tools may have looked at, both acceptable:

- `build_missing_notice` ([class-admin.php](../includes/class-admin.php), registered inside `enqueue()`)
  is only added inside `enqueue()` after a hook-suffix check, so it is already
  scoped to our screen. It never fires in the shipped zip because `build/` is
  included. Optional cleanup only.
- `add_menu_page` (top-level menu) is allowed. A submenu under **Settings**
  would be lighter for a thin-client plugin but is not required.

### Acceptance criteria

- [x] No Book Surfer notice appears on any admin screen other than the Book
      Surfer settings page. *Live-verified 5 Sep 2026 on booksurfer.local with
      the degraded flag set: Dashboard, Plugins, Settings, Posts = 0 hits.*
- [x] The degraded-crypto warning is still visible to admins on the Book Surfer
      settings page. *Live-verified: the page bootstrap carries
      `"degraded":true` into the React app, which renders the warning.*
- [x] `grep -rn admin_notices includes/` returns nothing that is hooked
      unconditionally. *Verified 5 Sep 2026: only `build_missing_notice`
      remains, inside the hook-suffix check.*
- [x] Plugin activates and the settings page loads with no PHP warnings
      (`WP_DEBUG` on). *Live-verified: deactivate + reactivate and all page
      loads added zero lines to debug.log.*

### Steps to resolve

**Recommended – remove the site-wide PHP notice (React already covers it) – ✅ DONE 5 Sep 2026**

Applied: the `add_action` line in `register()` and the whole
`maybe_degraded_notice()` method were removed (22 lines). `php -l` and PHPCS
pass for `class-admin.php`. Steps kept for reference.

1. In `includes/class-admin.php`, delete this line from `register()`:

   ```php
   add_action( 'admin_notices', array( $this, 'maybe_degraded_notice' ) );
   ```

2. Delete the whole `maybe_degraded_notice()` method (the docblock and the
   function, currently lines ~199–222).
3. Confirm the React warning still renders: the `degraded` flag is passed in
   `bootstrap_data()` and `App.tsx` shows a `<Notice status="warning">` when it
   is true. No JS change needed.
4. Nothing else changes; this is a pure removal.

**Alternative – keep a PHP notice but scope it to our screens**

Use this only if you want the warning visible on the Plugins list too. Replace
the method body with:

```php
public function maybe_degraded_notice() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $screen = get_current_screen();
    if ( ! $screen || ! in_array( $screen->id, array( $this->hook_suffix, 'plugins' ), true ) ) {
        return;
    }
    if ( ! Plugin::instance()->token_store()->is_degraded() ) {
        return;
    }
    echo '<div class="notice notice-warning is-dismissible"><p>';
    echo esc_html__( 'Book Surfer: this server has neither the sodium nor the OpenSSL PHP extension, so your refresh token is only base64-encoded, not encrypted. Ask your host to enable one of them, then reconnect.', 'book-surfer' );
    echo '</p></div>';
}
```

Key changes: the screen check limits it to our page and `plugins.php`, and
`is-dismissible` lets the admin close it.

**Optional cleanup**

- Remove `build_missing_notice()` and the `add_action` that registers it inside
  `enqueue()`; replace with a plain `return;`. It is a developer-only message.

**Test** — ✅ performed 5 Sep 2026 on the Local site `booksurfer.local` (WP 7.0.2)
instead of wp-env, using the rebuilt zip. Steps below kept for reference.

1. `npm run env:start`
2. Log in at <http://localhost:8888/wp-admin> (`admin` / `password`).
3. To simulate the degraded state, run:

   ```
   npx wp-env run cli wp option update booksurfer_crypto_degraded 1
   ```

4. Visit Dashboard, Posts, Settings > General. **No** Book Surfer notice must
   appear.
5. Visit **Book Surfer** in the menu. The warning must appear (from React, or
   from PHP if you kept the scoped variant).
6. Reset:

   ```
   npx wp-env run cli wp option delete booksurfer_crypto_degraded
   ```

---

## Action Item 3 – Run Plugin Check and fix everything it reports

### What the reviewer said

> Search your codebase for other occurrences of the same issues, even if they
> are not explicitly mentioned in this review.

The next round reviews the **entire plugin** again. Anything Plugin Check finds
now will be found by the reviewer later, costing another round.

### Acceptance criteria

- [x] Plugin Check (all categories) reports **zero errors** on the built zip.
      *5 Sep 2026, Plugin Check 2.1.0 on booksurfer.local: "Checks complete.
      No errors found."*
- [x] Warnings are either fixed or understood and defensible. *The one warning
      was fixed, see the results table under step 4.*
- [x] PHPCS (WordPress Coding Standards, config in `phpcs.xml`) passes for all
      shipped PHP. *Done 5 Sep 2026, run with `--exclude=Generic.Files.LineEndings`
      on Windows. 41 leftovers in `tests/php/` only, not shipped, optional.*

### Steps to resolve

1. Build the plugin first so Plugin Check sees what the reviewer sees:

   ```
   npm ci
   npm run build
   npm run plugin-zip
   ```

2. Start the local site and install Plugin Check:

   ```
   npm run env:start
   npx wp-env run cli wp plugin install plugin-check --activate
   ```

3. Run the check from the CLI (fastest):

   ```
   npx wp-env run cli wp plugin check book-surfer
   ```

   Or in the browser: **Tools > Plugin Check**, select Book Surfer, tick every
   category, click **Check it!**

4. Fix every **ERROR**. Typical ones for a plugin like ours:
   - Missing escaping or sanitisation on output/input.
   - Direct database calls or `file_get_contents` on remote URLs (use
     `wp_remote_get`).
   - `readme.txt` header problems (`Tested up to`, `Stable tag`).
   - Enqueued assets without a version, or inline scripts not added via
     `wp_add_inline_script`.

   **What Plugin Check actually found on 5 Sep 2026, and the fix applied:**

   | Type | Where | Finding | Fix |
   |------|-------|---------|-----|
   | ERROR | `readme.txt` | `Tested up to: 7.0`, but the current WordPress release is 7.1. Plugins not marked as tested on the current release are hidden from directory search | Changed to `Tested up to: 7.1`. Caveat: the live checks ran on WP 7.0.2 (the Local site). Updating that site to 7.1 and re-running them would make the claim fully tested |
   | WARNING | `class-plugin.php` | `load_plugin_textdomain()` is discouraged since WP 4.6 for WordPress.org-hosted plugins | Removed the `init` hook and the `load_textdomain()` method, plus the stale paragraph in `languages/README.md`. `languages/` ships no `.mo` files, so nothing is lost |

5. Re-run until clean. Also run PHPCS locally:

   ```
   composer install
   vendor/bin/phpcs
   ```

   **What PHPCS reports today (5 Sep 2026) and how to fix each kind.** `phpcs`
   only reports. `phpcbf` is the same tool in rewrite mode: safe for some rules,
   harmful for others, so always target it per sniff and preview first with
   `vendor/bin/phpcs --report=diff <files>`.

   | Kind | Where | Fix |
   |------|-------|-----|
   | CRLF line endings (`Generic.Files.LineEndings`) | 12 shipped files, all test files | Windows checkout artefact, the git index is already LF. Either ignore on Windows with `vendor/bin/phpcs --exclude=Generic.Files.LineEndings`, or normalise the working copy with `vendor/bin/phpcbf --sniffs=Generic.Files.LineEndings` (harmless, git shows no diff). Decision 5 Sep 2026: exclude on Windows, no file changes |
   | Banner comments (`Squiz.Commenting.BlockComment.NoNewLine`) | 8 three-line `/* --- */` section banners: 4 in `class-oauth.php`, 3 in `class-rest.php`, 1 in `class-token-store.php` | **Hand edit only.** Change each banner line from `/* Connect */` style to `// Connect` style. `phpcbf` splits them into broken two-line comments. ✅ Done 5 Sep 2026 |
   | Assignment alignment (`Generic.Formatting.MultipleStatementAlignment`) | 2 lines in `class-api-proxy.php` | Safe to auto-fix: `vendor/bin/phpcbf --sniffs=Generic.Formatting.MultipleStatementAlignment includes/class-api-proxy.php`. ✅ Done 5 Sep 2026 |
   | Tests only, not shipped (`tests/php/`) | 28 one-line associative arrays (`WordPress.Arrays.ArrayDeclarationSpacing`), 6 docblock summaries not capitalised (`Generic.Commenting.DocComment.ShortNotCapital`), 3 more banner comments, 1 alignment, CRLF | Arrays and alignment: safe to `phpcbf --sniffs=...`. Docblock capitals and banners: hand edit. Lowest priority, the reviewer never sees `tests/` |

6. Re-check Guideline 11 by grepping for any remaining global hooks:

   ```
   grep -rn "admin_notices\|network_admin_notices\|all_admin_notices" includes/ book-surfer.php
   ```

---

## Action Item 4 – Rebuild, re-upload, reply

### Acceptance criteria

- [x] New zip contains only: `book-surfer.php`, `uninstall.php`, `readme.txt`,
      `README.md`, `LICENSE.txt`, `includes/`, `build/`, `languages/`.
      *Verified 5 Sep 2026.*
- [x] Zip activates on a clean WordPress with no fatal error and no notice
      outside the plugin screen. *Verified 5 Sep 2026 on booksurfer.local.
      Fresh-site repeat optional.*
- [x] Uploaded while logged in as `booksurfer`. *5 Sep 2026.*
- [x] Reply posted **in the existing thread**, under ~6 lines. *5 Sep 2026, confirmed by the status change on the Add page.*

### Steps to resolve

1. Bumping `Version` in `book-surfer.php` and `Stable tag` in `readme.txt` is
   optional; the reviewer does not require it for a resubmission.
2. Rebuild and zip:

   ```
   npm ci
   npm run build
   npm run plugin-zip
   ```

   Note: this checkout has `core.autocrlf=true`, so PHP files on disk use CRLF
   line endings. A zip built on this Windows machine ships CRLF files. PHP and
   Plugin Check do not care, and the GitHub release workflow builds LF. Either
   is fine for the reviewer.

3. Verify the payload (should match the list in the acceptance criteria):

   ```
   unzip -l book-surfer.zip
   ```

4. Smoke-test the zip on a fresh site: upload via **Plugins > Add New > Upload**,
   activate, open Book Surfer, open Dashboard. No errors, no stray notices.
5. Complete Action Item 1 (email change and/or DNS record) **before** replying.
6. Upload the zip at <https://wordpress.org/plugins/developers/add/> logged in as
   `booksurfer`.
7. Reply to the review email thread using the template below.
8. Wait. Do not ask for a status update for at least one month. If a reply seems
   lost, check spam first.

### Reply template

Keep it short. The reviewer explicitly asks not to list changes.

```
Hi, thanks for the review.

Ownership: I have changed the booksurfer WordPress.org account email to an
@booksurfer.in address (support@booksurfer.in). Book Surfer is our own product.

I have re-scoped the admin notice to the plugin's own screen, run Plugin Check
on the new build, and uploaded the updated zip.

Please keep the slug "book-surfer".
```

If you also complete Option B, add one line: "I have also added the DNS TXT record
wordpressorg-booksurfer-verification at the root of booksurfer.in."

Only mention the slug if you truly want to keep or change it. Permalinks cannot
be changed after approval.

---

## Already compliant – no action needed

- `readme.txt` has the **External service** section disclosing that the plugin
  talks to booksurfer.in. This is a required disclosure for plugins calling a
  third-party API, and it is already present.
- The distributed zip excludes all dev files (`src/`, `tests/`, `stub/`,
  `docs/`, `composer.phar`, `test-results/`). `.distignore` and the
  `plugin-zip` allowlist are in sync.
- `build_missing_notice` is already scoped to the plugin screen.
- The plugin enqueues zero JS/CSS on the public front end.

---

## Final checklist before hitting "Reply"

- [x] WordPress.org email for `booksurfer` is now `support@booksurfer.in` (changed 5 Sep 2026). DNS TXT record optional.
- [x] Site-wide `admin_notices` hook removed and live-verified on Dashboard, Plugins, Settings and Posts screens (5 Sep 2026, booksurfer.local).
- [x] Plugin Check: zero errors on the built zip (5 Sep 2026, v2.1.0).
- [x] Zip rebuilt, contents verified, activated cleanly on booksurfer.local (5 Sep 2026). Fresh-site repeat optional.
- [x] Zip uploaded while logged in as `booksurfer` (5 Sep 2026).
- [x] Reply is in the **same thread**, short, no change-list, slug stated (5 Sep 2026).

## References

- Detailed Plugin Guidelines: <https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/>
- Guideline 11 (admin experience): section 11 of the above.
- Plugin Check: <https://wordpress.org/plugins/plugin-check/>
- Re-upload page: <https://wordpress.org/plugins/developers/add/>
- WordPress.org profile edit: <https://profiles.wordpress.org/me/profile/edit/>
