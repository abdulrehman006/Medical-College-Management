=== Medical College Manager ===
Contributors: abdulrehmanallshore
Tags: medical, college, faculty, timetable, admissions
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.2
Stable tag: 1.32.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Fifteen connected content types for a medical college, with CSV import/export, filterable
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
                                                          OPD days/time, phone, email, gender*.
* Team         — Department, Specialty, Faculty, Fields: designation, qualifications, experience,
                 Staff Type.
                                                          phone, email, LinkedIn, gender*.

  * Gender (Male / Female) is never shown on the website or sent through the REST
    API. It only picks the male or female default photo for people without a photo
    (College Manager → Settings → Default Photo — Male / Female).
* Admissions   — Department, Session.            Fields: program name, open/close date, eligibility,
                                                          fee, apply URL.
* Students     — Level (Graduate/Undergraduate), Fields: student ID, roll/registration no, email,
                 Department, Session.                     phone, program, batch, gender, DOB, status,
                                                          blood group, address, guardian + phone.
* Jobs         — Job Category, Department.       Fields: publication date, last date + closing time,
                                                          extended last date, apply link/label, how to
                                                          apply, job description, voucher/form, posts,
                                                          scale, type, contact, show on notice board.

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
   column) create new entries; tick "Update existing entries with the same
   title" to update by exact title instead. Rows inside one file are never
   merged. Dates may be written 15/10/2026, 15-10-2026, 15.10.2026 or
   2026-10-15 (day first); a row with an invalid date is skipped and reported.
   Matching terms are reused, not duplicated. Great for backups and bulk edits.

Sample files included: sample-timetable.csv, sample-programs.csv, sample-courses.csv,
sample-events.csv, sample-consultants.csv, sample-team.csv, sample-admissions.csv,
sample-jobs.csv and the other sample-*.csv files in /samples/.

== Shortcodes ==

Per-type (recommended):

  [mcm_timetable]      [mcm_programs]     [mcm_courses]
  [mcm_events]         [mcm_consultants]  [mcm_team]
  [mcm_admissions]     [mcm_tenders]      [mcm_scholarships]
  [mcm_students]       [mcm_publications] [mcm_jobs]

Team by staff type: [mcm_team staff_type="support-staff"] (or "teaching-technical-staff").

Jobs / Vacancies: [mcm_jobs] (alias [mcm_vacancies]) lists open jobs, soonest last
date first. status="closed" or status="all" lists past jobs too; category="…" and
department="…" pre-filter. A job is Open, Closing soon, Extended or Closed by its
dates in the site timezone; a future publication date hides it everywhere.

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
  exclude_category  category slug(s) to leave out
  layout     list | grid              (default list; grid shows cards)
  columns    grid columns on desktop  (default 3; 2 on tablets, 1 on phones)
  show_thumb / show_date / show_more   yes | no (default yes)
  empty      message when there are no posts
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
  orderby    also: modified | post__in (order of ids) | field (with meta_key:
             one of the type's own fields; items without it are listed last)
  search     initial search string
  search_box / reset   yes | no      show the search box / Reset button (default yes)
  facets     taxonomies to offer as filters, comma separated (default all)
  placeholder  search box placeholder text
  status     events: upcoming | past; admissions: open | upcoming | closed;
             scholarships, tenders: open | closed  (default all; jobs keep
             open | closed | all, default open)
  ids        only these items (comma separated IDs)
  exclude_ids  leave these items out
  offset     skip the first N items
  paginate   yes | no                 numbered pages ("count" is the page size)
  empty      message when nothing is found
  images     all | uploaded | none     photos/images on cards and list rows
             (people: "uploaded" leaves out the default photo for staff
             without a photo; "none" shows text-only cards)
  table_cols columns to show in the table view, comma separated, e.g.
             table_cols="title,mcm_designation,mcm_department" (default all)
  show_button / show_specialty / show_designation / show_department /
  show_excerpt / show_meta / show_status   yes | no (default yes)
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

== Header & Footer ==

College Manager > Header & Footer. Switch on the header and/or footer (both off by
default) and build the menus in Appearance > Menus (locations "College header: main
menu", "College header: top bar menu" and "College footer: column 2 / column 3 /
bottom bar links"). Tabs: General (on/off, built-in design or your own Elementor
templates, hidden pages), Top bar, Header & menu, Behaviour (static / sticky / smart
sticky / shrinking, breakpoint, back-to-top), Footer, Typography and Import / Export.
Works with Hello Elementor without editing it; on other classic themes the theme's own
header may also show (Site Health explains how to hide it); block themes keep their
own. If the college header or footer ever fails, the theme's comes back for that page.
The Montserrat font is bundled (SIL OFL, no Google request).

== Elementor ==

If Elementor (3.5+) is active, open any page in Elementor and look for the
"Medical College" widget category: 13 listing widgets (Consultants, Team,
Programs, Courses, Events, Timetable, Admissions, Tenders, Scholarships, Research,
Policies, Jobs, Publications), Blog Posts and three notice widgets. Configure
everything visually:

  Content          — Layout, Columns (per device), Number of items, Show
                     (Upcoming / Past events; Open / Closed admissions,
                     scholarships, tenders and jobs), Order by (date, title,
                     menu order, random, a field such as the event date, or
                     the order you picked items in), Skip the first N,
                     Pagination, the "nothing found" message.
  Filters & Tools  — Filter bar on/off, search box, placeholder, which
                     dropdowns to show, Reset button, a starting search
                     keyword, Print button and printed heading.
  Display          — Photos/Images: show (default photo when none uploaded),
                     only uploaded, or hide; show/hide specialty, designation,
                     department (people), status badge, details, short
                     description and the button; pick the Table columns
                     (e.g. leave out Staff Type and Specialty).
  Include / Exclude— limit to, or leave out, terms of each taxonomy
                     (Department, Session, Faculty, Specialty, Category…);
                     only these items / leave out these items.
  Style            — Layout & spacing, colours, cards (Normal / Hover:
                     background, border, radius, shadow, lift), images (fit,
                     radius, CSS filters, hover zoom), titles, details text,
                     status badges (colour per status), buttons (Normal /
                     Hover), filter bar and fields (incl. focus colour),
                     directory sidebar, table, citations, pagination, print
                     button and the empty message. Every option applies to
                     that widget only.

Testimonial / Message: a heading, a message (rich text), a photo and the
person's name, designation and organisation, e.g. "Message from Head of
Department". The photo sits left, right or on top, chosen per device (by
default right on desktop and tablet, on top on phones), with width, spacing
and vertical alignment controls; optional quotation mark and profile link.
Style tab: box, heading, message (incl. justified text), quotation mark,
photo (border, shadow, filters) and the name lines.

Blog Posts adds a Grid layout, columns, show/hide image, date and read-more,
exclude categories, tags, author and offset, with its own Style tab (cards,
image, date badge, title, excerpt, read more, intro). The notice widgets add a
"last N days" filter and Style tabs for the bar/box, heading, notices, dates,
badges and the "View all" link.

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

= 1.32.0 =
New: college header & footer, Staff Type, full Elementor widget options.
* College header & footer (College Manager > Header & Footer): replaces the theme's
  header and footer without editing the theme; off until switched on.
* Staff Type for team members; gender and male/female default photos.
* Redesigned job cards and directory filters; status and time left on admissions,
  scholarships, tenders and events; display and accessibility fixes across listings.
* Elementor widgets: status, include/exclude, order by field, offset, pagination and
  filter-bar options, plus full Style tabs (Normal/Hover) on all widgets.
* New Elementor widget: Testimonial / Message (photo left, right or on top).
* Full list: changelog.txt.

= 1.31.0 =
New: Jobs / Vacancies.
* New content type Jobs with Job Categories (Teaching Faculty, Clinical,
  Administrative, Support Staff are created once) and Departments. Fields:
  publication date, last date and closing time, extended last date, apply link
  and label, how to apply, job description and voucher/form (image or PDF),
  number of posts, scale, type, contact.
* Status is worked out from the dates in the site timezone: Open, Closing soon
  (Settings > "Closing soon" days, default 3), Extended (old date struck
  through), Closed. A job with a future publication date is hidden everywhere.
  Closed jobs leave the list but their pages stay online.
* [mcm_jobs] (alias [mcm_vacancies]) and an Elementor "Jobs / Vacancies" widget:
  status="open" (default), "closed" or "all", with category and department
  filters; the status survives search, filters, Reset and Print.
* Department pages get a Vacancies section while that department has open jobs.
* Notice board, ticker and list can show open jobs and open tenders next to
  notices (Settings > Notice board sources; per item "Show on notice board";
  include="notice,job,tender" per shortcode or widget).
* CSV import/export for jobs (sample-jobs.csv); tenders gain an on_board column.
* JobPosting structured data while a job is open; none once it closes.
* Vacancies print on the letterhead.
= 1.30.2 =
Reliability and hardening release: error boundaries around every module and
render, Site Health checks and an error log, date/time validation with a
one-time upgrade of old values, pre-filters kept on live filters, Reset and
Print, safer CSV import, a Public student directory setting and many fixes.
Full details in changelog.txt.

= 1.30.1 =
Security hardening.
* The live-filter endpoint now only sorts by fields a content type is allowed
  to show. Previously a crafted request could order the public student list by
  a private field (e.g. date of birth) without ever displaying it.
* [mcm_single] no longer renders non-public content (student records).
* [mcm_blog] only lists publicly viewable post types; post_type="mcm_student"
  or another plugin's private type now falls back to blog posts.
* Added a directory-listing guard to /languages/.

= Earlier versions =
See changelog.txt in the plugin folder for the complete history.
