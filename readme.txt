=== Medical College Manager ===
Contributors: abdulrehmanallshore
Tags: medical, college, faculty, timetable, admissions
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.2
Stable tag: 1.30.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Twelve connected content types for a medical college, with CSV import/export, filterable
archives, Elementor widgets and letterheaded printing.

== Description ==

Built from a medical-college admin perspective. Each content type has its own admin fields,
shared taxonomies for consistent filtering, front-end search + AJAX filters, and printable
output. Everything is dynamic, customizable and editable from wp-admin — colors, spacing,
columns, card style and raw CSS.

Post types & their taxonomies / fields:

* Timetable    — Department, Session, Faculty.   Fields: day, start/end time, subject, room, year.
* Programs     — Program Category, Department.   Fields: degree level, duration, fees, intake, apply URL.
* Courses      — Department, Faculty, Session,   Fields: course code, credit hours, semester, lead.
                 Course Category.
* Events       — Event Category, Department.     Fields: date, start/end time, venue, organizer, reg URL.
* Consultants  — Department, Specialty, Faculty. Fields: designation, qualifications, experience,
                                                          OPD days/time, phone, email.
* Team         — Department, Specialty, Faculty. Fields: designation, qualifications, experience,
                                                          phone, email, LinkedIn.
* Admissions   — Department, Session.            Fields: program name, open/close date, eligibility,
                                                          fee, apply URL.
* Students     — Level (Graduate/Undergraduate), Fields: student ID, roll/registration no, email,
                 Department, Session.                     phone, program, batch, gender, DOB, status,
                                                          blood group, address, guardian + phone.

Department is shared across all types, so a student can filter Timetable, Courses,
Consultants, Team etc. by the same departments consistently.

== Installation ==

1. Plugins > Add New > Upload Plugin > choose medical-college-manager.zip > Activate.
2. New admin menus appear: Timetable, Programs, Courses, Events, Consultants, Team, Admissions.
3. Open "College Manager" for design/colors/Custom CSS, and "College Manager > CSV Import" to bulk-import.
4. Add a shortcode (below) to any page.

== CSV Import / Export ==

Go to College Manager > CSV Import / Export.

Import:
1. Choose the content type.
2. Download the matching sample CSV (links are on that page; files are in /samples/).
3. Fill it with your data. First row = headers. Multiple terms in a taxonomy column are comma-separated.
4. Upload and import. Departments/sessions/faculty/categories are created automatically.

Export:
1. Choose the content type and click "Export CSV".
2. You get every entry of that type (its meta fields plus Departments/Sessions/
   Faculty/Categories) in a UTF-8 CSV that opens cleanly in Excel/Sheets.
3. The columns match the import format, so you can export, edit, and re-import.
   The export includes an "id" column; on re-import each row UPDATES the record
   it came from instead of creating a duplicate. Hand-written files (no id
   column) match an existing entry by its exact title within that type; keep
   titles unique, or include an id column, if two entries could share a name.
   Matching terms are reused, not duplicated. Great for backups and bulk edits.

Sample files included: sample-timetable.csv, sample-programs.csv, sample-courses.csv,
sample-events.csv, sample-consultants.csv, sample-team.csv, sample-admissions.csv.

== Shortcodes ==

Per-type (recommended):

  [mcm_timetable]      [mcm_programs]     [mcm_courses]
  [mcm_events]         [mcm_consultants]  [mcm_team]
  [mcm_admissions]     [mcm_tenders]      [mcm_scholarships]
  [mcm_students]       [mcm_publications]

Research & Development (Publications): each paper stores authors, journal, year,
volume, issue, pages, type, DOI, PMID, article URL, PDF, open access, impact
factor and indexing, and is tagged with a Research Area, a Department and a
Professor. Output is a numbered Vancouver reference list (the medical standard):

  Authors. Title. Journal. Year;Volume(Issue):Pages.

DOI and PMID become clickable doi.org / pubmed.ncbi.nlm.nih.gov links
automatically - you only paste the identifier, never the full URL.

  [mcm_publications count="20"]                       newest first
  [mcm_publications research_area="oncology"]         one research area
  [mcm_publications department="pathology"]           one department
  [mcm_publications professor="dr-sara-khan"]         one professor
  [mcm_publications view="list"]                      cards instead of citations

Because Publications share the Professor and Department connectors, a professor's
page automatically lists their papers and a department page lists the whole
department's output - nothing extra to link. Add ORCID iD, Google Scholar and
PubMed author URLs on a Consultant or Team member and they appear as buttons on
that person's profile. The /publications/ archive has sidebar filters (research
area, department, professor, search) and pagination.

Scholarships: a Scholarship type (Merit / Performance / Need-Based) with amount /
coverage, eligibility, application deadline and an Apply button on the single
page. Archive at /scholarships/, [mcm_scholarships] shortcode, an Elementor
"Scholarships" widget, and CSV import/export (with a sample file).

Tenders work like Admissions but auto-expire: a tender disappears from the
front-end once its Submission Deadline has passed (still visible in wp-admin).
Each tender has a reference no., published / deadline / bid-opening dates, fee,
earnest money, eligibility, and Download-document + Submit-portal buttons on its
single page.

Printing:

  Enable the types you want in College Manager > Settings > Printable Content
  Types (Timetable and Students are on by default), then the Print button
  appears on those listings automatically:

    [mcm_timetable]                 print button (type enabled in settings)
    [mcm_students]                  print button - branded "Student List"
    [mcm_students print="no"]       suppress it on this one instance

  The printed sheet carries the site name, a heading, and the filters, result
  count and date at the moment of printing - so a filtered list prints exactly
  what is on screen, and nothing else on the page is printed.

Generic:

  [mcm_grid type="mcm_event"]
  [mcm_list type="mcm_course"]
  [mcm_table type="mcm_timetable"]
  [mcm_single id="123"]

Notice Board (announcements, results, circulars):

  [mcm_notice_ticker]   scrolling headline strip (great under the header)
  [mcm_notice_board]    vertical auto-scroll panel (sidebar / homepage)
  [mcm_notices]         static filterable list

  count       number of notices           (default 10)
  category    Notice Category slug(s), comma separated
  department  Department slug
  priority    Urgent | Important | Normal
  days        only notices from the last N days
  speed       scroll speed 1 (slow) – 10 (fast)   (ticker/board)
  height      board height in px          (board; default 340)
  label       ticker label                (default "Latest")
  title       board heading               (default "Notice Board")
  show_date   yes | no       show_badge   yes | no       more   yes | no

  Notices auto-sort pinned -> priority -> date, auto-hide after their Expiry
  Date, and show a "New" badge for the last 7 days. Scrolling pauses on hover,
  has a pause button, and respects prefers-reduced-motion. Also available as
  three Elementor widgets (Notice Ticker / Notice Board / Notices List).

Blog posts (standard WordPress posts, date-badge layout):

  [mcm_blog count="6" order="DESC" category="news"]

  count      number of posts            (default 6)
  orderby    date | title | rand | comment_count   (default date)
  order      ASC | DESC                  (default DESC)
  category   category slug(s), comma separated
  tag        tag slug(s), comma separated
  author     author ID
  post_type  defaults to "post"
  offset     skip N posts
  excerpt    excerpt length in words     (default 30; 0 hides it)
  intro      optional intro line above the list
  read_more  custom read-more label
  class_css  extra wrapper class

Attributes (all optional):

  view       grid | list | table     (timetable defaults to table)
  columns    1–6                      (grid only; default from settings)
  count      number of items          (default 12)
  filters    yes | no                 AJAX search + taxonomy dropdowns
  print      yes | no                 show a Print button (timetable only)
  print_title custom heading on the printed/PDF page (default "Class Timetable")
  category   primary-taxonomy term slug(s)
  department / session / teacher / specialty / level   taxonomy term slug(s)
  orderby    date | title | menu_order | rand | meta_value
  order      ASC | DESC
  meta_key   required when orderby="meta_value" (e.g. mcm_event_date)
  meta_type  DATE | NUMERIC | CHAR (optional, pairs with meta_key)
  search     initial search string
  class_css  extra wrapper class for Custom CSS targeting

Examples:

  [mcm_timetable view="table" filters="yes" print="yes" print_title="Class Timetable — Fall 2025"]
  [mcm_courses view="table" filters="yes"]
  [mcm_consultants view="grid" columns="4" filters="yes"]
  [mcm_consultants view="directory" columns="3"]
  [mcm_consultants department="cardiology" specialty="neonatology"]
  [mcm_team view="grid" columns="4" filters="yes"]
  [mcm_programs columns="3" filters="yes"]
  [mcm_admissions columns="3" filters="yes"]

Directory view: view="directory" renders a left sidebar (a "Find a Consultant"
search box that matches name, email, phone and designation, plus a checkbox list
of Departments for multi-select filtering) next to a card grid where each card
shows the photo, name, designation, department and a "Meet the Consultant"
button. Filtering is AJAX. Works for consultants and team.

All archive pages (/consultants/, /team/, /courses/, /events/, /programs/,
/admissions/, /timetable/) use this left-sidebar Directory layout by default.
The main area adapts to the type: a table for the Timetable, profile cards for
Consultants/Team, and info cards (with each type's details) for the rest. The
sidebar checkbox facet is the type's primary taxonomy (Department for most;
Program/Event/Course Category where relevant). Override with the
mcm_archive_view filter.

Front-end filtering: when filters="yes", students get a search box plus a dropdown for each
relevant taxonomy (e.g. Department, Session, Teacher for timetable; Department, Specialty for
consultants/team). Filtering is AJAX — no page reload. On the Timetable, adding
print="yes" shows a Print button that prints just the (filtered) timetable.

== Elementor ==

If Elementor (3.5+) is active, open any page in Elementor and look for the
"Medical College" widget category. Drag in Consultants, Team, Programs, Courses,
Events, Timetable or Admissions. Configure everything visually:

  Content tab    — Layout (Grid / List / Table), Columns, Number of items, Order.
  Filters & Tools— Filter bar (AJAX search + dropdowns); Print button (Timetable only).
  Pre-filter     — optionally limit the widget to a Department, Session, Faculty,
                   Specialty or Category. (For a fixed list, turn the Filter bar
                   off when you pre-filter.)

No shortcodes or term slugs are needed — terms are picked from dropdowns. The
widgets use the same renderer as the shortcodes, so styling from the Design panel
and AJAX filtering apply automatically.

== Social Bar ==

College Manager > Social Bar. Paste each network's URL (leave blank to hide it),
then either:

  * tick "Show on whole website" to float it on every page, or
  * place the shortcode [mcm_social_bar] on a specific page / Elementor Shortcode widget.

Options: side (left or right) and mobile behaviour (turn into a bottom bar, or
hide on small screens). WhatsApp takes a number (country code + digits) and
builds a wa.me link. If site-wide is on, the shortcode is not needed and any
accidental duplicate is suppressed.

== Styling / Custom CSS ==

College Manager settings page: color pickers, columns, gap, padding, radius, image height,
shadow + hover toggles, font family, and a raw Custom CSS box. Generated CSS uses variables
(--mcm-primary, --mcm-radius, --mcm-gap, etc.) so Custom CSS overrides anything. Classes:

  .mcm-wrap .mcm-toolbar .mcm-filters .mcm-grid .mcm-list .mcm-table
  .mcm-card .mcm-card-title .mcm-card-link .mcm-meta .mcm-list-item .mcm-single
  Per-type modifiers: .mcm-card--consultant, .mcm-card--team, .mcm-card--event,
  .mcm-card--program, .mcm-card--course, .mcm-card--timetable, .mcm-card--admission

== Changelog ==

= 1.30.1 =
Security hardening.
* The live-filter endpoint now only sorts by fields a content type is allowed
  to show. Previously a crafted request could order the public student list by
  a private field (e.g. date of birth) without ever displaying it.
* [mcm_single] no longer renders non-public content (student records).
* [mcm_blog] only lists publicly viewable post types; post_type="mcm_student"
  or another plugin's private type now falls back to blog posts.
* Added a directory-listing guard to /languages/.

= 1.30.0 =
* Fixed: department / event videos set as a YouTube or Vimeo link now always
  display. The player is now built directly from the link instead of calling
  the provider over the network on every page load — which previously failed
  silently on many servers (Vimeo never worked, YouTube failed behind
  firewalls/proxies), leaving only the image. Every common link form is
  supported: youtube.com/watch, youtu.be, /embed/, /shorts/, /live/, and
  vimeo.com / player.vimeo.com. Pages also load faster (no outbound request).
* The hero banner (video or image) is now FULL-WIDTH and sits ABOVE the page
  title on department and listing pages, as a proper cinematic banner. Videos
  are centred at a sensible size; images span edge to edge. The full-width
  banner no longer introduces a stray horizontal scrollbar.
* The Head-of-Department card has been redesigned: a colour accent spine, a
  framed photo, and the email / phone now show as tidy pill buttons with
  icons — each appearing only when that detail is filled in.
* New per-department "Hide Sections" control: on a department, tick any
  sections you don't want on its page (e.g. hide the Timetable). Unticked
  sections still appear automatically whenever they have content.
* Security: unknown or internal URLs are still never embedded (SSRF-safe) and
  fall back to a plain link. Videos carry explicit dimensions so they never
  collapse to a tiny box when a theme's CSS is restrictive.
* Fixed: the archive / directory filter bar (search + dropdowns + Reset) now
  always stays on a single inline row. Some themes force every input and
  select to full width, which stacked the filters vertically; the bar now
  overrides that so it looks the same on every theme. Applies to the archive
  pages and the Elementor content widgets (they share the same filter bar).

= 1.29.0 =
* Events can now carry a live stream. Add a "Live Stream / Embed URL" to any
  event and the plugin shows the player at the top of the event page plus a
  "Watch Live" button in the details card. Left blank, nothing is shown - the
  feature is entirely optional and never displays an empty box. YouTube, Vimeo
  and direct MP4/WebM links are supported; unknown/internal hosts are never
  server-embedded (SSRF-safe) and fall back to a plain link.
* New "Page Banners" in Settings: give any public listing page (Events,
  Faculty, Scholarships, etc.) an optional hero video or image that appears
  above the list. Video takes priority over image; blank means no banner.
* Student lists ([mcm_students]) can be filtered by academic level, so
  graduate and undergraduate rosters can be shown separately. Only safe,
  public fields are ever rendered - no personal contact data is exposed.
* Research & Publication pages now label the lead as "Supervisor / Principal
  Investigator" and the team as "Assisting Students", matching institutional
  wording.
* Event live streams, department heroes and page banners now share one
  consistent, hardened media renderer.

= 1.28.0 =
* Elementor content widgets are now fully designable and responsive:
  - Columns are per-device (separate desktop / tablet / phone counts).
  - New Style tab: Layout & Spacing (gap, card padding, corner radius, image
    height - all responsive), Colours (primary, accent, text, borders, card &
    panel backgrounds), Titles (full typography + colour) and Card shadow.
  - Each widget can now be restyled independently, overriding the global
    design just for that block, without any custom CSS.

= 1.27.2 =
* Settings page now has a complete, grouped "Shortcodes — copy & paste"
  reference: every shortcode with a plain-language description and a one-click
  Copy button, plus the common attributes and connector filters.

= 1.27.1 =
* Fix: a taxonomy term page that contained exactly one content type (e.g. any
  category page, or a department with a single populated type) threw a fatal
  error. It now renders and paginates correctly.
* Standards: the student-only "Level" taxonomy no longer exposes a public term
  URL or REST route (its pages could only ever be empty). It stays fully
  usable in wp-admin and as a student-list filter. Any taxonomy that lives
  only on private content is now automatically kept off the public site.

= 1.27.0 =
Department pages redesigned + composable department shortcodes.
* Term/department pages now show a section ONLY for content types that actually
  have entries — no more wall of empty "No results found" blocks. A filter bar
  appears only on sections large enough to need one.
* Department hero: a term now takes a Hero Video URL OR a Hero Image (new). The
  video wins when both are set; otherwise the static banner shows.
* The Head-of-Department message is a redesigned card (photo, name, message,
  contact).
* New shortcodes to build a custom department template (e.g. in Elementor),
  each auto-detecting the current department or taking department="slug":
    [mcm_department_hero]     - the video/image banner
    [mcm_department_message]  - the HOD message card
    [mcm_department_intro]    - hero + message together
    [mcm_department_sections] - every populated section (courses, timetable,
                                faculty, publications…) as titled blocks
  The existing per-type shortcodes also fetch a department, e.g.
  [mcm_courses department="anatomy"], [mcm_timetable department="anatomy"].
* Filter bar restyled everywhere (cleaner inputs, select chevrons, a subtle
  Reset, visible focus) and section headings given clearer hierarchy.
= 1.26.1 =
* Fix: the "professor" and "program" shortcode filter attributes now work
  (e.g. [mcm_publications professor="dr-sara-khan"], [mcm_courses program="mbbs"]).
  They were documented but not wired to their taxonomies.

= 1.26.0 =
Program is now the academic spine (architecture batch D).
* New shared "Program" connector taxonomy (MBBS/BDS/BSN…) on Courses,
  Timetable, Admissions and Students — the same pattern as Department and
  Faculty. A Program page now lists its own curriculum, schedule and intake,
  and /program/<name>/ term pages, filters and CSV columns come for free.
* A matching Program term is created automatically whenever you save a Program
  entry, so the Program dropdown is pre-populated — no double data entry.
* One-time, additive migration: existing free-text program values on Students
  and Admissions are turned into Program terms and assigned, while the original
  text is kept intact (reversible; runs once on upgrade).
* Students stay private — they carry the Program term for filtering, but the
  public /program/ page never lists them (the roll remains protected).
= 1.25.0 =
CSV import is now an upsert — re-importing no longer creates duplicates.
* Export files include an "id" column; re-importing updates the exact record it
  came from instead of duplicating it.
* Hand-written CSVs (no id column) match an existing entry by its exact title
  within the type, so a corrected re-import updates rather than duplicates.
* Genuinely new rows still insert; the import summary now reports created,
  updated and skipped counts.
* Re-importing a file that omits the content/excerpt columns no longer wipes the
  existing body text — only columns present in the file are written.
= 1.24.0 =
Hardening, performance and accessibility pass (audit batch A).
* Performance: listing/table/directory renderers now read term names from the
  cache WordPress already primes instead of a fresh query per row — a 25-item
  directory dropped from ~59 queries to 14, and the print/export path no longer
  fires thousands of term queries.
* Security: the AJAX endpoint now serves only this plugin's own content types
  (never core post/page); department banner videos are oEmbedded only for known
  providers (YouTube/Vimeo) to prevent server-side request forgery; CSV export
  neutralises spreadsheet formula injection; the font-family setting is
  character-restricted; JSON-LD is hex-tag encoded; and the Related Content box
  no longer shows student names to editors who lack the student capability.
* Accessibility: visible keyboard focus (:focus-visible) on all controls, an
  accessible label on the search box, scope="col" on table headers, alt text on
  card/list/single images, and a higher-contrast "New" badge.
* Fix: the responsive stacked-table layout no longer leaks into print, so the
  printed timetable and student list render as proper tables.
= 1.23.0 =
WordPress.org directory-readiness pass.
* Text domain renamed from "mcm" to "medical-college-manager" to match the
  plugin slug, as required for directory hosting and translation. Translations
  now load correctly and Plugin Check no longer errors on the mismatch. No
  behaviour change: the Elementor category, object-cache group and widget
  keywords were renamed in lockstep, so everything keeps working.
* Translation template renamed to languages/medical-college-manager.pot.
* readme: valid Contributors username, tags trimmed to five, Tested up to 7.0.
= 1.22.0 =
Student privacy — the public student list now shows safe fields only.
* The student list ([mcm_students]) shows only Name, Student ID, PMDC No.,
  Level, Program, Department, Batch and Status. The Contact (email) column has
  been removed.
* Email, phone, date of birth, home address, blood group, gender and guardian
  details are never rendered on the front end in any view — they remain in
  wp-admin for staff only.
* Whatever view is requested, students are forced to the safe table renderer,
  so a grid/card view cannot surface a student photo (the public list is names
  only) or any other field.
* Student search matches Name, Student ID, PMDC No., Program and Batch only —
  never email or phone, so the directory can't be used to confirm a student
  from their contact details.
* The public list can now be searched/filtered by anyone on those safe fields
  (previously student filtering was blocked for logged-out visitors). Sensitive
  data stays fully protected; only safe fields are ever returned.
* AJAX result counts are clamped (filter: mcm_ajax_max_count, default 1000) so a
  crafted request cannot ask for an unbounded list.
= 1.21.0 =
Research & Publications refinements requested after the portal review.
* Research: the "Principal Investigator / Professor" field is now "Supervisor".
* Research: "Assisting Student(s)" is now "Fellowship Members" - the students
  working under the supervisor, entered one per line (e.g. "Dr. Farooq - Final
  Year MBBS"). Line breaks are preserved on the page.
* Research: Research Status now offers Completed, In Progress and Dropped
  (Dropped is new).
* Research: the paper now has clear View (opens in a new tab) and Download
  buttons on the page, in addition to the existing upload.
* Publications: added Supervisor and Fellowship Members fields.
* Publications: the paper PDF is now uploaded / selected from the Media Library
  (it was a plain URL box) and gets a Download button beside the view link.
* Meta-box fields can now show a short format hint under the input.

= 1.20.0 =
Implements the GMC Website Portal Review Document (v1.0.0) items that fall to
this plugin.
* NEW Research Projects type (review item 14): title, principal investigator,
  status (In Progress / Completed), start and completion dates, assisting
  students, co-investigators, funding body, and a PDF uploaded from the Media
  Library with a Download button. Filterable archive, CSV import/export,
  Elementor widget, printable, and connected to the existing Professor,
  Department and Research Area connectors - so a professor page lists their
  projects automatically. This is distinct from Publications, which remains the
  bibliographic (journal / DOI / citation) register.
* NEW Policies type (review item 16): issuing body, reference no., effective
  date, Policy Category and a downloadable document. Configurable from the
  database, with CSV import/export and an Elementor widget.
* Departments now carry their own content (review items 4 and 7): Head of
  Department name and photograph, welcome message, banner video, and
  departmental email/phone - edited on the Department term itself and shown at
  the top of that department's page.
* Faculty cards now show the SPECIALTY under the name, and the portrait has more
  breathing room around it (review item 11).
* A branded default profile image can be uploaded in Settings and is used for
  anyone without a photograph (review item 11).
* Staff records gained a Joining / Appointment Date (review item 4).
* Tenders gained an Institute / Facility Name (review item 19).
* Events gained a Live Stream / Embed URL (review item 18).
* Document fields are now uploaded through the Media Library instead of pasting
  a URL.
* Research Projects and Policies are printable on the college letterhead.
* SEO: schema.org JSON-LD for faculty, events, courses, programs, publications,
  research projects and scholarships. Never emitted for students (review 25).
* Accessibility: filtered result lists announce themselves to screen readers
  via aria-live / aria-busy (review 25).

= 1.19.0 =
 * SECURITY: student records now have their own capabilities. Previously they
   used the default post capabilities, so every Author and Contributor could
   open the student roll and read PMDC numbers, emails, phones, dates of birth,
   addresses and guardian contacts. Access is now granted to Administrators
   only; use the mcm_student_roles filter to add a Registrar or Admissions
   role. All other content types are untouched - staff who manage timetables,
   events and faculty keep exactly the access they had.
 * Privacy: registered a personal-data exporter and eraser, so a subject-access
   or erasure request can be answered from Tools > Export/Erase Personal Data.
   Erasing anonymises the personal fields and keeps the academic record, since
   deleting enrolment history outright is rarely what a college wants.
 * Performance: the front-end CSS and JS used to load on every page of the
   site. They now load only where MCM content appears, with a render-time
   fallback so page-builder layouts are never left unstyled. Filter
   mcm_always_load_assets to force them everywhere.
 * Performance: filter facets (the checkbox lists) are cached and refreshed
   automatically when a term is added, renamed or deleted.
 * The plugin now stops with a clear admin notice on PHP older than 7.2
   instead of fataling mid-load.
 * Added LICENSE, a languages/mcm.pot translation template, License URI,
   Requires PHP / Requires at least headers, and corrected the plugin
   description and readme (they still described seven post types, not twelve).
 * Meta search now builds its SQL with prepared placeholders throughout.

= 1.18.0 =
 * Printed output (and Save-as-PDF) now uses a proper letterhead: logo,
   institution name, address/contact line, the document title, the applied
   filters, entry count and date - then an optional signature block
   (Prepared by / Checked by / Principal) and a footer note with the
   generation timestamp. Configure it under College Manager > Settings >
   Printing & Letterhead, including a Media Library picker for the logo.
   With nothing configured it falls back to the theme logo (then the site
   icon) and the site title, so it still prints as a proper document.
 * Printing a paginated list now prints the WHOLE list, not just the page on
   screen - a paginated timetable used to print only its first 12 rows.
 * Pagination links no longer appear on the printed sheet, and the entry count
   is correct for grid, list, directory and citation views (it previously
   counted table rows only and reported 0 for everything else).
 * Featured images now display reliably on single pages. Many themes only
   render a featured image for blog posts, so a photo set on a Consultant,
   Event or Notice could end up invisible. College Manager > Settings >
   "Featured Image on Single Pages" defaults to Auto: the plugin shows the
   image only when the theme has not already rendered it, so it never appears
   twice. Always / Never are available for themes that place it below the
   content or handle it themselves.
 * Publications can now have a featured image (journal cover, figure or graphical
   abstract) - the type was registered without thumbnail support.
 * Print is now a general capability instead of a timetable-only feature.
   College Manager > Settings has a "Printable Content Types" checkbox list;
   Timetable and Students are ticked by default. Any type can be enabled with
   no code. The printout keeps the visitor's applied filters and search, prints
   only the listing, and takes its heading from the content type.
 * Student lists are printable: [mcm_students] on a protected page now shows a
   Print button, giving a branded "Student List" sheet that respects the
   Level / Department / Session filters and the search box.
 * The print attribute is now optional - a listing follows the setting unless
   you pass print="no" to suppress it on one instance.
 * SECURITY (affects earlier versions): the AJAX filter endpoint accepted any
   post type from the browser and is also registered for logged-out visitors,
   so a crafted request could list a NON-PUBLIC type - returning the whole
   student directory, PMDC numbers and contact details included, without
   logging in. The endpoint now refuses any type the visitor could not already
   read; server-rendered [mcm_students] on a protected page is unaffected.
 * FIXED (affects earlier versions): archives ordered by an optional date/time
   field (Events, Timetable) could sort records that lacked that field by an
   unrelated value of their own, placing them in an apparently random position.
   Ordering now pins the field explicitly and is deterministic.
 * Directory archives now show a checkbox filter for EVERY taxonomy of the type,
   not just the first one - so publications filter by Research Area AND
   Department AND Professor, and consultants by Department AND Specialty.
 * Taxonomy (professor / department) pages no longer cut their list off at 12
   items with no pagination - a professor with 40 papers now shows all of them.
 * CSV import now takes each field type from the meta-box definitions instead of
   a separate hand-kept list that had drifted: multi-line fields keep their line
   breaks and URL fields are validated on import.
 * ORCID / Google Scholar / PubMed / Research Area are importable and exportable
   for Consultants and Team; Consultants gained the LinkedIn field they were
   already being rendered with.
 * NEW: Research & Development module (v1 - Publications).
 * Publications post type (Publications menu) with 14 fields: authors, journal,
   year, volume, issue, pages, type, DOI, PMID, article URL, PDF, open access,
   impact factor and indexing. Classic editor, no block editor.
 * NEW Research Area taxonomy, shared by Publications, Consultants and Team, so a
   research interest links a professor to every paper in that area.
 * Publications are connected to Department and Professor. A professor page and a
   department page now list that person's / that department's publications
   automatically - no manual linking.
 * Vancouver citation output (medical standard): Authors. Title. Journal.
   Year;Volume(Issue):Pages. New view="citation" - a numbered reference list.
 * Automatic DOI and PubMed links (doi.org / pubmed.ncbi.nlm.nih.gov), plus PDF
   and full-text buttons, and an Open Access badge.
 * Publications archive is filterable (Research Area, Department, Professor,
   search) and sorted newest year first; entries with no year are never dropped.
 * ORCID iD, Google Scholar and PubMed author fields added to Consultants and
   Team; they appear as buttons on the profile page.
 * New shortcode [mcm_publications] (all standard parameters, defaults to the
   citation view, 20 items).
 * New Elementor widget: Publications, in the Medical College category.
 * CSV import AND export for publications, plus samples/sample-publications.csv.
   Research area / department / professor are matched or created on import.

= 1.17.3 =
* Label updates: Timetable/Courses "Teacher" column and single-page label are
  now "Professor"; the student Roll/Registration field is now "PMDC No." (field,
  table, card, admin column, search box); the student table Email column heading
  is now "Contact". Data keys are unchanged - existing records keep their values.

= 1.17.2 =
* Courses table: the Credit Hours column is now centre-aligned (header + cells).
  Table columns support per-column alignment via an optional 4th value
  (center/right) in the column definition.

= 1.17.1 =
* Students now also get the default avatar (in grid/list cards and [mcm_single])
  when they have no photo.
* Single detail pages: for people with no featured image, the default avatar now
  shows at the top of the details card, so the page never looks photo-less.

= 1.17.0 =
* Default avatar: Consultants and Team (faculty/staff) with no featured image
  now show a clean silhouette placeholder instead of a blank box - in the
  directory cards, grid cards, list items and single page. Override the default
  with your own image via the mcm_default_avatar_url filter (e.g. a college logo).

= 1.16.1 =
* Single-page polish: the details panel on every content type is now a padded,
  self-contained card (background, border, comfortable cell padding) with proper
  margins and vertical rhythm, and reduced padding on phones. Better separation
  from the body content per UI spacing standards.

= 1.16.0 =
* Archive pagination: CPT archives (/consultants/, /courses/, /tenders/, etc.)
  now paginate at 12 per page with numbered Prev/Next controls, so all entries
  are reachable (previously capped at ~12 with no way to see more). Per-page is
  filterable via mcm_archive_per_page; the [mcm_*] shortcodes gained a
  paginate="yes" attribute. The main query per-page is aligned so /page/N/ URLs
  resolve without a 404.

= 1.15.1 =
* Standards: added translators comments to all i18n strings with placeholders
  (CPT/taxonomy labels, import row messages, related-content count) for full
  WPCS i18n compliance. No functional change.

= 1.15.0 =
* New Scholarships content type (like Admissions) with a Scholarship Type
  taxonomy (Merit / Performance / Need-Based) and fields for amount / coverage,
  eligibility, application deadline and apply URL. Archive (/scholarships/),
  elegant single page with an Apply button, [mcm_scholarships] shortcode, an
  Elementor "Scholarships" widget, and CSV import/export with a sample file.
  Matches the Scholarships section on medical-college sites like RMC.

= 1.14.1 =
* UI/UX QA pass: added a box-sizing reset for components rendered outside the
  main wrapper (blog, notices, ticker/board, social bar, archive) so padding
  never breaks their width on themes that don't set border-box globally.
* Phone polish: smaller archive headings and tighter notice ticker on small
  screens; removed a duplicate CSS rule.

= 1.14.0 =
* New Tenders content type (like Admissions) with a Tender Category taxonomy and
  fields for reference no., published / submission-deadline / bid-opening dates,
  document fee, earnest money, eligibility, document (download) and portal URLs.
* Auto-expiry: tenders drop off the front-end automatically once the submission
  deadline has passed (still visible and manageable in wp-admin; filterable via
  mcm_tender_hide_expired). Archive (/tenders/), elegant single page with
  Download/Submit buttons, [mcm_tenders] shortcode, an Elementor "Tenders"
  widget, and CSV import/export (with a sample file) — all included.

= 1.13.0 =
* New Notice Board content type with a Notice Category taxonomy and fields for
  priority, pin-to-top, notice/expiry dates, link and attachment.
* Three front-end displays + three Elementor widgets: a scrolling Ticker, a
  vertical auto-scroll Board, and a static Notices List. Notices auto-sort
  (pinned -> priority -> date), auto-expire, show a "New" badge, pause on hover,
  and honour reduced-motion. Feed is cached (transient) for speed.
* /notices/ archive (filterable list) + elegant single notice page with a
  Download/Open button. Notices join the CSV import/export (with a sample file).

= 1.12.2 =
* Fix: the branded print header (title + filters + count + date) now also shows
  when printing from the Timetable archive page (it was only added for the
  shortcode layout before).

= 1.12.1 =
* Print fix: the Print button now prints ONLY the timetable (header + table) —
  not the page header/footer/sidebar/filters. It clones just the timetable into
  a dedicated print area, so the rest of the page is fully excluded (and no blank
  trailing pages). Save-as-PDF gives a clean one-piece document.

= 1.12.0 =
* Elegant single (detail) pages for every content type: each post now shows a
  styled details panel with the fields that matter for that type and clear action
  buttons — Consultants/Team get Profile + Call/Email/LinkedIn; Events get a
  Register button; Programs/Admissions get an Apply button; Courses, Timetable
  show their full details. Works on the native permalink and via [mcm_single].
* Refined archive design: heading with an accent underline, divider, and tidier
  section/card spacing. All colours follow the Design panel.

= 1.11.2 =
* All CPT archive pages now use the left-sidebar Directory layout (search +
  primary-taxonomy checkboxes on the left). The main area adapts: Timetable shows
  a table, Consultants/Team show profile cards, and Courses/Events/Programs/
  Admissions show their info cards. Override per type via the mcm_archive_view
  filter (return "grid"/"list"/"table"/"directory").

= 1.11.1 =
* The Consultants and Team archive pages (/consultants/, /team/) now use the
  Directory layout — search box + Department checkboxes on the LEFT, cards on the
  right — instead of the top filter bar. (Taxonomy term pages keep the grid.)

= 1.11.0 =
* New CSV Export next to Import (College Manager > CSV Import / Export). Pick a
  content type and download every entry — meta fields plus Departments, Sessions,
  Faculty and Categories — as a UTF-8 CSV (BOM for Excel). Columns match the
  import format, so export → edit → re-import round-trips cleanly. Admin-only,
  nonce-protected.

= 1.10.1 =
* Easier data entry: every content type now opens in the simple classic editor
  (title + content + labelled fields) instead of the block editor — friendlier
  for non-technical staff. The REST API stays enabled (for Elementor/meta), only
  the editing screen changes. Core posts/pages are unaffected.
* Added a short "how to fill this in" hint at the top of each details box.

= 1.10.0 =
* Timetable print/PDF template: the Print button now produces a clean printable
  page with a branded header (site name + title), the active filters, the entry
  count and the date — then prints only the timetable (use the browser's "Save
  as PDF"). Tuned for A4, repeats the table header on every page, avoids row
  splits.
* Works after filtering: whatever Department/Session/Faculty/search is applied is
  reflected in the printout and its header summary.
* New print_title attribute to set the printed heading.

= 1.9.1 =
* Print is now a Timetable-only feature: the Print button only appears on the
  timetable (print="yes"), and is ignored on other content types. Other types
  can re-enable it via the mcm_print_types filter if ever needed.

= 1.9.0 =
* New Directory layout (view="directory") for Consultants/Team: a left sidebar
  with a search box and multi-select Department checkboxes, next to a card grid
  (photo, name, designation, department, "Meet the Consultant" button).
* Consultant/Team search now matches name, email, phone and designation (not
  just the title), so the sidebar "Find a Consultant" box works as expected.
* Multi-select checkbox filtering + a Search button were added to the AJAX
  layer; selecting several departments shows all of them (OR).
* Available in Elementor too: pick Layout = "Directory (left filters)".

= 1.8.0 =
* New blog shortcode [mcm_blog]: lists standard WordPress posts in a date-badge
  row layout (date + thumbnail + title + excerpt + read-more), fully responsive.
* Parameters: count, orderby (date/title/rand/comment_count), order (ASC/DESC),
  category, tag, author, post_type, offset, excerpt length, intro, read_more.
* Matching Elementor "Blog Posts" widget (under the Medical College category)
  with category multi-select and the same options — no shortcode needed.
* Posts with no featured image fall back to a clean placeholder/2-column layout.

= 1.7.0 =
* New Social Bar: a floating social-media bar with its own settings page
  (College Manager > Social Bar). Add Facebook, Instagram, X/Twitter, TikTok,
  YouTube, LinkedIn and WhatsApp links; empty fields are hidden.
* Show it site-wide with one toggle, or place [mcm_social_bar] on any page
  (or in an Elementor Shortcode widget). Duplicates are prevented automatically.
* Fully responsive: vertical bar with slide-out labels on desktop; on mobile it
  becomes a horizontal bottom bar, or can be hidden — your choice. Choose left
  or right side. WhatsApp builds a wa.me link from the number.

= 1.6.1 =
* Security/Privacy: the Students post type no longer exposes a REST route, so
  student PII (email, phone, address, DOB, guardian) can never be read via
  /wp-json. Student meta now follows each type's REST visibility. Students are
  edited in the classic editor (meta boxes), which is unaffected.

= 1.6.0 =
* Elementor support: drag-and-drop widgets for Consultants, Team, Programs,
  Courses, Events, Timetable and Admissions — found under a "Medical College"
  category in the Elementor panel.
* Each widget exposes Layout (grid/list/table), Columns, Count, Order, a Filter
  bar toggle, a Print toggle, and optional per-taxonomy pre-filters — no
  shortcodes or slugs to remember.
* Widgets reuse the same engine as the shortcodes, so AJAX filtering, views and
  the Faculty/Department connectors all work identically.
* Requires Elementor 3.5+ (free). Students are intentionally not exposed as a
  widget (PII); use the [mcm_students] shortcode deliberately if needed.

= 1.5.0 =
* Faculty connector: the Teacher taxonomy is now "Faculty" and is shared by
  Timetable, Courses, Consultants and Team — so one faculty member links their
  classes with their profile. Faculty term pages list everything they touch.
* New "Related Content" meta box on every content type: shows, grouped by shared
  Department / Faculty / Session / Specialty term, the linked posts across all
  content types (each a direct edit link). Read-only — driven by shared terms.
* CSV import now connects relationships automatically: matching term values are
  reused as one shared term across files (so content imported separately links
  up), and Consultants/Team are auto-linked to an existing Faculty entry that
  matches their name. A "faculty" column is also supported for explicit control.
* Note: the Faculty archive base changed from /teacher/ to /faculty/ (rewrite
  rules flush automatically on update).

= 1.4.0 =
* Filterable archive pages: CPT archives (/events/, /programs/, /consultants/…)
  and plugin taxonomy term pages now render the AJAX search + filter bar and
  card/table views automatically — no shortcode needed.
* Shared-taxonomy term pages (e.g. a Department) show one filterable section per
  public content type, each scoped to that term and kept scoped through AJAX.
* Themes can override per the standard hierarchy (archive-{cpt}.php /
  taxonomy-{tax}.php) or by copying templates/archive-mcm.php to /mcm/.
* New setting "Filterable Archive Pages" (on by default) to toggle the feature.

= 1.3.0 =
* Single CPT pages now display their custom fields (and consultant/team contact
  details) on the native permalink, not only via [mcm_single].
* Custom fields are registered with the REST API / block editor
  (register_post_meta) with per-type sanitize + auth callbacks.
* Event and Timetable archives now order sensibly (events soonest-first by date,
  timetable by start time) instead of by publish date.
* Admin list tables show key meta as columns, with sorting for IDs, codes and
  dates (Student ID, Course Code, Event/Admission dates).
* Internal: single shared sanitizer reused by meta box, CSV import and REST.

= 1.2.1 =
* Security: [mcm_single] now renders only published plugin content — drafts,
  private/pending/trashed posts and non-plugin posts can no longer be exposed.
* Privacy: Students are no longer publicly queryable or search-indexable
  (PII directory is admin + shortcode only); the admin UI is unchanged.
* Fix: AJAX filtering now preserves meta_key/meta_type, so meta-sorted views
  (e.g. events by date) keep their order after searching/filtering.
* Fix: meta_type is validated against an allowed SQL-type set.
* Added table-view columns for Programs, Events and Team.
* Compat: explicit fgetcsv() arguments remove the PHP 8.4 deprecation notice.
* Hardening: CSV upload now content-sniffs the MIME type.
* Print: printing a shortcode now isolates that block instead of the whole page.
* Added uninstall.php to clean up the settings option on delete (content kept).

= 1.2.0 =
* Added Students CPT (graduate & undergraduate) with full records.
* New Level taxonomy; students share Department & Session for filtering.
* Meta-aware search: students findable by name, Student ID, roll no, email, phone, program, batch.
* CSV import + sample for students; single students added via the normal Add New screen.

= 1.1.0 =
* Added Team and Admissions CPTs.
* Shared Department taxonomy across all types; added Teacher & Session taxonomies.
* CSV importer with per-type sample templates.
* Table view + printable output.
* Generic multi-taxonomy AJAX filtering (department, session, teacher, specialty + search).

= 1.0.0 =
* Initial release.
