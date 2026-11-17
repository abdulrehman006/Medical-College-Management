# Medical College Manager: targeted security & standards audit

**Date:** 2026-11-17
**Branch:** `security-audit/ai-common-issues`, from `qa/pre-launch-audit` @ `95c0643`
**Fix commits:** `a057f84`, `f08a1c6`
**Stacked branches also reviewed:**
- `feature/slider-widget`
- `feature/people-email-identity` (fix `09fb8b4`, see Finding 7)

**Scope:** the 42 PHP files on the audit branch (21,857 lines), checked file by file. This includes:
- the 19 Elementor widget classes in `includes/elementor-widgets.php`;
- `uninstall.php`;
- the templates.

The two stacked branches add `class-mcm-widget-slider.php` and `class-mcm-people.php`. They were reviewed against the same 14 categories; their findings are marked as such.

## How it was checked

Every category was checked in two ways: a systematic search across the whole tree, and a test that exercises the real code path.

**Searches:**
- every `wp_ajax_*`, `admin_post_*`, `register_rest_route`, `permission_callback` and `auth_callback`;
- all 27 superglobal reads;
- every `$wpdb` call;
- every `catch` block;
- every `==` / `!=` and every `in_array`;
- every `set_transient` and option write;
- every global function, class, constant and hook name;
- all 146 lines with a PHPCS suppression (`phpcs:ignore` / `phpcs:disable`), each opened and read.

**Tests:** all run in Docker. This was never run on the XAMPP site or any live site.

| Test | What it does | Result |
|---|---|---|
| `auth-qa.php` | REST controllers via `rest_do_request`, plus admin-post, CSV, meta-box, term and profile handlers. Run as a department coordinator, an Editor, an Author and a Subscriber, including requests carrying a **valid** nonce. | 33 checks |
| `fuzz-qa.php` | Arrays, objects, null, 300 KB strings and invalid UTF-8 sent into every sanitizer and handler. Also 13 malformed CSV files (binary, 2,000 columns, 1 MB cell, unclosed quote, BOM/CRLF, formula injection) and odd shortcode attributes. | 999 cases, run on WP 7.1 / PHP 8.3 **and** WP 5.8 / PHP 8.0 (the declared minimum) |
| `xss-seed.php` + `xss-browser.js` | Payloads written **straight into the database**, bypassing every input sanitizer, into: every post title and meta field of all 15 types, every term name/description and term field, the plugin's settings, header/footer and social options, and Elementor widget settings. Every listing view, single page, archive, search page, header/footer, JSON-LD, Elementor widget and wp-admin screen was then loaded in Chromium. | 162 page loads, as a visitor and as admin |
| `failclosed-qa.php` | Fault injection: the department-access module is made to throw while it starts. | 8 checks; fail-open before the fix, fail-closed after |
| PHP 7.2 lint | Every file parsed by a real PHP 7.2.34 interpreter. | Clean |
| Secrets | Search of the current tree **and all 74 commits on every ref**, including any file ever committed. | None found |

**Regression after the fixes:**
- PHP suites: publications 20, security 23, sorting 28, P1 23.
- Browser suites: P1 browser 22, AJAX 10, sort browser 85.
- PHPCS 0/0; Plugin Check clean (only the folder-name TextDomainMismatch notices).

---

## 1. Clean: categories with no confirmed finding

| # | Category | Result | Evidence |
|---|---|---|---|
| 2 | SQL injection | **No** | 9 custom queries (`class-mcm-query.php` 970, 1015, 1055, 1445, 1587; `class-mcm-upgrade.php` 135, 196; `uninstall.php` 64). Every value goes through `$wpdb->prepare()`. `IN (...)` lists are built only from runs of `%s`/`%d` sized by `count()`. ORDER BY direction and CAST type are whitelisted (`class-mcm-query.php` 947–948). Table names come only from `$wpdb->` properties, never from placeholders. The only user-driven LIKE (`filter_where`, 1045) uses `$wpdb->esc_like()`. The two literal LIKEs (1587, uninstall 64) contain no input and escape `_` correctly. |
| 3 | Unescaped output / XSS | **No** | All 34 escaping suppressions and 41 blanket `phpcs:ignore` lines were read; each echoes a builder whose parts are escaped for their context (`esc_url` on every `href`/`src`, `esc_attr` in attributes, `wp_json_encode` + `JSON_HEX_TAG` in JSON-LD (`class-mcm-schema.php` 158), integers in every inline `style`). Static searches found no `esc_html` inside attributes, no `esc_attr` on URLs, and no raw variable in `href`/`src`. The DB-level payload test found **no** live markup from plugin output on any of the 162 page loads (846 payload instances on the listing page alone, all inert). Two places run the payload, but neither is plugin code: the theme's `<h1 class="entry-title">` (core `the_title()`) and core's category-parent `<select>`. WordPress prints titles and term names raw by design because it filters them with kses on save; the plugin's own writers keep to that (`sanitize_text_field` on CSV titles, `wp_insert_term` for terms). |
| 5 | Hardcoded secrets | **No** | No API keys, tokens, passwords or connection strings in the tree or in history. No `.env`, `wp-config`, `.sql`, `.pem` or `.key` file was ever committed. The only outbound request (Vimeo oEmbed, slider branch) needs no key. |
| 6 | File-upload handling | **No** | The plugin stores no uploads. Its two upload handlers only read the PHP temp file: the CSV import (`class-mcm-import.php` 445: extension, `is_uploaded_file`, finfo MIME sniff) and the header/footer JSON import (`class-mcm-site-chrome-settings.php` 608: `is_uploaded_file`, under 200 KB, schema check). It never moves the file, so there is no upload directory to protect. Documents, photos and banners are picked through the WordPress Media Library, where core checks the real file type (`wp_check_filetype_and_ext`). |
| 7 | Dangerous functions | **No** | None of `eval`, `unserialize`, `maybe_unserialize`, `exec`, `system`, `passthru`, `shell_exec`, `proc_open`, `popen`, `assert`, `create_function`, `extract`, `base64_decode`, `include`/`require` with variables, or backticks. The 2 dynamic callables are whitelisted: `'tab_' . $tab` is checked against `tab_labels()` (`class-mcm-site-chrome-settings.php` 145), and the Elementor Pro class name is a constant (`class-mcm-templates.php` 156). |
| 8 | Command injection | **No** | No shell commands are built anywhere. |
| 9 | Loose comparisons in auth/role logic | **No** | There are zero `==` / `!=` in the codebase, and all `in_array()` calls are strict. The 10 `switch` statements branch on field and post types from internal config, never on roles or capabilities. |
| 13 | Performance footguns | **No** | Every `set_transient` has an expiry (5 min to 1 week). Large data is not autoloaded (the health log and upgrade record use `autoload = no`). The autoloaded options are small settings arrays plus the `mcm_listing_ver` timestamp, which changes only when content is edited. Cache keys: listing caches are keyed by the listing's query and a version and hold public data only; per-user notices use per-user keys (`mcm_missing_<user>`, `mcm_meta_invalid_<user>`); the facet cache that differs for staff is keyed `staff`/`public` (`class-mcm-query.php` 1497). |
| 14 | PHP version mismatches | **No** | The header declares `Requires PHP: 7.2` and `Requires at least: 5.8` (`medical-college-manager.php` 9–10, `readme.txt`). PHPCompatibilityWP is set to `testVersion 7.2-` and reports 0. Every file parses on a real PHP 7.2.34. WordPress functions newer than 5.8 are guarded (`wp_is_block_theme`, 5.9, behind `function_exists`). |

Categories 1, 4, 10, 11 and 12 are not clean; they are covered below.

**Category 1: what was confirmed safe.**
- **AJAX:** 1 handler (`mcm_filter`, public read-only). It has a nonce, a signed config and a `type_allowed()` gate on non-public types.
- **admin-post:** 5 handlers. Each calls `current_user_can( 'manage_options' )` and then `check_admin_referer`; an Editor and a Subscriber with a valid nonce are refused.
- **REST:** the plugin registers **no** custom REST routes, so there is no `permission_callback` to get wrong. The post-meta `auth_callback` is object-level (`edit_post`, `$post_id`).
- **Forms:** CSV import/export, Settings API pages (`manage_options`), department access on the profile (`promote_users` + `edit_user` on that user) and meta-box save (`edit_post` on that post) are all object-level where an object exists.

**Category 4: what was confirmed safe.** All 27 superglobal reads are sanitized and unslashed, or are read-only checks; the exception is Finding 5.

---

## 2. Confirmed findings

### Finding 1: High (category 1). Department scoping bypassed through the REST API

- **Where:** `includes/class-mcm-dept-access.php:47`, the rule only ran on `save_post`:
  ```php
  add_action( 'save_post', array( __CLASS__, 'enforce_terms' ), 15, 2 );
  ```
- **Problem:** the REST posts controller assigns taxonomy terms in `handle_terms()`, which runs *after* `wp_insert_post()` has fired `save_post`. Department rules were therefore applied before the REST request's departments were written. The CPTs have `show_in_rest`, so any coordinator can use `/wp/v2/...` with their login cookie or an application password.
- **Evidence:** `auth-qa.php`, before the fix, as `qadepteditor` (Editor, limited to Anatomy):
  - `POST /wp/v2/mcm_course {mcm_department:[Cardiology]}` returned `201`, and the entry was **Cardiology only** (`terms=4`).
  - `POST /wp/v2/mcm_course/<shared>` with `[Anatomy]` **removed Cardiology** from a shared entry (`terms=2`).
  - `POST /wp/v2/mcm_course/<own>` with `[Anatomy, Cardiology]` **added Cardiology** (`terms=2,4`).
- **Impact:** a coordinator could publish content on another department's pages, or take another department's content off them. That is the exact shape of the scoping bug fixed in the last pass, on a path that pass didn't cover.
- **Fix (`a057f84`):** the same rule now also runs on `wp_after_insert_post`. That hook fires once the post, its terms and its meta are all stored, on every path (REST, classic editor, quick edit, XML-RPC).
- **Re-test after the fix:**
  - the three requests above end with `terms=2`, `terms=2,4` and `terms=2`;
  - edit, read and delete of a Cardiology-only entry still return `403`;
  - editing another department's term (and its meta) and creating a department are refused.

### Finding 2: Medium (category 10). Department access failed open if its module could not start

- **Where:**
  - `includes/class-medical-college-manager.php:63`: `$module = MCM_Safe::boot( $class_name );`
  - `includes/class-mcm-safe.php:60–63`: the catch logs (`MCM_Health::module_failed`) and returns `null`.
- **Problem:** the error boundary is right for display modules, but `MCM_Dept_Access` is an authorization control. If its file or constructor ever failed (a PHP fatal, or a conflicting plugin), the site kept running without it. Coordinators then silently got their role's full rights over every department's content. The failure was reported to administrators, not silent, but the behaviour was fail-open.
- **Evidence:** `failclosed-qa.php` with the module made to throw, on the old code. The scoped coordinator **could** edit, delete and publish other departments' entries, edit any department, and add departments (4 checks failed).
- **Fix (`a057f84`):** if `MCM_Dept_Access` did not boot, a fallback in the main class (`dept_access_fail_closed`, `dept_access_no_new_terms`) denies these to users who have departments assigned, until the module works again: `edit_post`, `delete_post` and `publish_post` on plugin content; `edit_term` and `delete_term` on departments; and adding departments. The fallback reads the user meta directly, so it doesn't depend on the failed module. Administrators, unscoped editors and ordinary WordPress posts are not affected.
- **Re-test:** same fault injection, 8/8 pass. Only scoped users lose rights; Site Health still reports the failed module.

### Finding 3: Low (category 1). Department fields: general capability instead of an object-level check

- **Where:**
  - `includes/class-mcm-termmeta.php:303`, the form handler:
    ```php
    if ( ! current_user_can( 'manage_categories' ) ) {
    ```
  - `includes/class-mcm-termmeta.php:178`, the REST `auth_callback`:
    ```php
    return current_user_can( 'manage_categories' );
    ```
- **Problem:** both checked "can manage terms in general", not "can edit **this** department". Calling the handler for another department as a coordinator saved it (`auth-qa.php`, before the fix: `mcm_term_hod` on Cardiology became `hijack-…`).
- **Why only Low:** every HTTP path to these hooks is already gated by core's own `edit_term` check, which department access maps per term:
  - `edit-tags.php` and quick edit check `edit_term`;
  - the REST term route returned `403`.
  So the gap was only defense-in-depth.
- **Fix (`a057f84`):** both now use `current_user_can( 'edit_term', $term_id )`.
- **Re-test:** another department's field is refused; the coordinator can still save their own department; the REST term update returns `403`.

### Finding 4: Low (category 11). Wrong-type input causes fatal errors in admin forms

- **Where (evidence = TypeErrors from `fuzz-qa.php` before the fix):**
  - `includes/class-mcm-settings.php:183` (and its colour, number, URL and CSS lines, e.g. `261`): an array posted for a settings value gives `sanitize_hex_color()` → `preg_match(): Argument #2 must be of type string, array given`. Seen on WP 7.1 and 5.8.
  - `includes/class-mcm-termmeta.php:321`: `$cpt = sanitize_key( $cpt );` with a nested array → `strtolower()` TypeError. On WP 5.8 this is reachable by Editors and coordinators.
  - `includes/class-mcm-import.php:123`: `sanitize_key( $_POST['mcm_export_type'] )`, with no `wp_unslash` and no array guard → the same TypeError on WP 5.8.
  - `includes/class-mcm-site-chrome.php:197` (`clean()`) and `includes/class-mcm-social.php:141`: non-array input → "Cannot use object as array".
- **Why only Low:** every case needs a hand-crafted request carrying a valid nonce from a logged-in admin or editor. The result is a 500 error for that request only; nothing is stored or exposed.
- **Fix (`a057f84`):**
  - every value in these sanitizers is read through `MCM_Safe::str()`;
  - each sanitizer starts with `is_array( $input ) ? $input : array()`;
  - the export type uses `MCM_Safe::post()`, which unslashes and refuses arrays.
- **Re-test:** `fuzz-qa.php`, 999 cases → **0 issues** on WP 7.1 / PHP 8.3 and on WP 5.8 / PHP 8.0.
- **Also confirmed by the fuzz:** CSV import, shortcodes and the other handlers already failed gracefully on all malformed input, with no fatal error and no warning.

### Finding 5: Low (category 4). Export type not unslashed

- **Where:** `includes/class-mcm-import.php:123` (same line as Finding 4).
- **Problem:** `$_POST` was read without `wp_unslash()`. It's harmless because `sanitize_key` reduces the value to `[a-z0-9_-]` and it's then matched against the schema keys.
- **Fix:** same fix as Finding 4.

### Finding 6: Low (category 12). Counts without plural forms

- **Where:**
  - `includes/class-mcm-privacy.php:293, 300`: "personal field(s)";
  - `includes/class-mcm-upgrade.php:232`;
  - `includes/class-mcm-health.php:243, 257, 279`;
  - `includes/class-mcm-import.php:538`: "…and %d more.";
  - `includes/class-mcm-relations.php:108`: "+%d more".
- **Problem:** `__()` was used with a `%d` count, so languages with several plural forms can't translate correctly.
- **Evidence:** a tokenizer-based scan (`i18n-scan.php`) of every translation call. It found 18 strings with `%d`; 10 are real counts and the rest are row numbers, IDs or pixel sizes.
- **Fix (`a057f84`):** `_n()` for all of these. The remaining count string is backlog item B-1.
- **Concatenation check:** none of the 27 translated strings joined to other text splits a sentence. Each is a complete phrase followed by markup or a separate link.

### Finding 7: Low (category 1). Stacked branch `feature/people-email-identity`: duplicate-email check revealed other departments' unpublished names

- **Where:**
  - `includes/class-mcm-people.php`, `email_problem()`: `get_the_title( $other )`;
  - `includes/class-mcm-people.php`, `describe()` (AJAX `mcm_person_check`): `'name' => wp_strip_all_tags( get_the_title( $id ) )`.
- **Problem:** the AJAX check itself has a nonce, the type's `edit_posts` capability and `edit_post` on the record being edited. But when an email was already in use, it named the other record even when the user couldn't open it. A coordinator could learn the names of another department's draft people by trying email addresses.
- **Fix (`09fb8b4`, on that branch):** the name is shown only if the record is published (already public) or the user may edit it. Otherwise the message says "a record you cannot open". The duplicate is still refused.
- **Re-test (`people-leak-qa.php`, 3/3):** a draft in another department is not named for the coordinator; a published record is named; an admin still sees the name. `people-qa.php` 30/30.
- **Rest of the stacked code reviewed with no finding:**
  - The slider's only outbound request (`MCM_Widget_Slider::vimeo_thumb`) keeps only digits from the video ID, uses `wp_safe_remote_get`, accepts only an `https://…vimeocdn.com` thumbnail, and caches the result with an expiry.
  - Slider output escaping was covered by its own 10/10 XSS test.

---

## 3. Backlog (Low, deliberately not changed now)

| ID | Category | Item | Reasoning |
|---|---|---|---|
| B-1 | 12 | `class-mcm-import.php:486`: "Import complete: %1$d created, %2$d updated, %3$d skipped." | `_n()` can only pluralise one number. The proper fix is a label-and-number format ("Created: 2 · Updated: 1 · Skipped: 0"). The pending `feature/people-email-identity` branch rewrites this same block, so changing it here now would cause a merge conflict. Do it after that branch is merged. |
| B-2 | 12 | `class-mcm-query.php:3041`: `esc_html__( 'Priority', … ) . ':'`, a colon outside the translation | Matches the file's label pattern (`meta_pair` labels). Changing one string alone makes the labels inconsistent; changing all of them churns every listing string. Cosmetic: French translators can't add their space before the colon. |
| B-3 | 12 | Prefix `mcm_` / `MCM_` is 3 characters | Every function, class, constant, hook and option **is** prefixed, so there are zero unprefixed globals, and PHPCS PrefixAllGlobals passes. A longer prefix would lower the collision risk with another "MCM" plugin. Renaming would break stored data: post types, meta keys, option names, shortcodes and the developer hooks sites already use. Only worth it with a migration in a major version. |
| B-4 | 11 | CSV import has no row limit of its own | Admin-only (`manage_options`). The upload is bounded by PHP's `upload_max_filesize` and `post_max_size`. The fuzz test shows wide (2,000 columns), huge-cell (1 MB) and broken files all fail gracefully. A very long file can hit `max_execution_time`; the rows already written stay, and re-importing updates by `id` or email. A background or batched import would be a feature, not a fix. |

## 4. Not plugin issues (recorded so they aren't re-reported)

- The theme's single-page `<h1 class="entry-title">` and core's category-parent dropdown print post titles and term names unescaped. That's WordPress core's trust model: titles and term names are kses-filtered when saved by users without `unfiltered_html`.
- `href="https://javascript:mcmxss(4)"` appears in the raw page HTML: a URL normalizer prefixed `https://` to a `javascript:` value. It can't execute (the scan found no `javascript:` link on any page).

## 5. Files changed

| Commit | Files | Change |
|---|---|---|
| `a057f84` | `class-mcm-dept-access.php` | `wp_after_insert_post` enforcement (Finding 1) |
| | `class-medical-college-manager.php` | Fail-closed fallback (Finding 2) |
| | `class-mcm-termmeta.php` | `edit_term` checks, nested-array guard (Findings 3, 4) |
| | `class-mcm-settings.php`, `class-mcm-site-chrome.php`, `class-mcm-social.php`, `class-mcm-import.php` | Type guards (Findings 4, 5) |
| | `class-mcm-privacy.php`, `class-mcm-upgrade.php`, `class-mcm-health.php`, `class-mcm-relations.php`, `class-mcm-import.php` | Plural forms (Finding 6) |
| `f08a1c6` | `class-mcm-termmeta.php` | Comment punctuation (PHPCS) |
| `09fb8b4` (branch `feature/people-email-identity`) | `class-mcm-people.php` | `visible_name()` (Finding 7) |
