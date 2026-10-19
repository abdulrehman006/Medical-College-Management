# Medical College Manager

A WordPress plugin that turns a WordPress site into a complete website for a medical college: academics, faculty, research, admissions, notices and student records, all managed from wp-admin and displayed with shortcodes, Elementor widgets or automatic archive pages.

## Features

- **15 content types**: Timetable, Programs, Courses, Events, Consultants, Team, Admissions, Notices, Tenders, Scholarships, Publications, Research Projects, Policies, Jobs and Students.
- **Shared connectors**: Department, Faculty, Program, Session, Research Area and Specialty link content across types, so a department or professor page lists everything related to it automatically.
- **Department page template**: every department page shows the same eight sections in a fixed order (hero, Head of Department message, introduction, vision & mission, services, faculty grouped by staff type, publications, contact) plus tabs for its other content. Off until switched on; a completeness column lists what each department still needs.
- **College header & footer**: a global header (top bar, logo, three-level accessible menus, mobile panel, sticky modes) and footer configured from a settings page, replacing the theme's without editing it. Off until switched on; if it fails, the theme's own header and footer come back.
- **Staff Type** for team members (Teaching & Technical, Support), with filters, CSV and `staff_type="…"`.
- **Live search and filters** (AJAX) in grid, list, table, directory and citation layouts.
- **Publications** as a numbered Vancouver reference list with automatic DOI and PubMed links.
- **Notice board**: scrolling ticker, auto-scroll board and list, with pinning, priority and auto-expiry. Tenders hide themselves after their deadline.
- **Printing on the college letterhead** (logo, address, signature block), including filtered lists and Save as PDF.
- **CSV import/export** for every type, with upsert on re-import and sample files in `samples/`.
- **Elementor**: 25 drag-and-drop widgets with full style controls, including one per department section.
- **Design settings**: colours, spacing, columns, page banners and custom CSS, without code.
- **SEO**: schema.org JSON-LD for people, events, courses, programs, publications, jobs and departments.
- **Privacy**: student records have their own capabilities (administrators only), no REST route, and only safe fields on the front end. Includes WordPress personal-data export and erase support.
- **Built not to break your site**: every module and every rendered block runs behind an error boundary. A problem shows visitors an empty block instead of a broken page, is logged, and appears under Tools → Site Health with checks for PHP, WordPress, Elementor, timezone and permalinks.

## Requirements

- WordPress 5.8 or newer
- PHP 7.2 or newer
- Elementor 3.5+ (optional, only for the widgets)

## Installation

1. Download this repository as a ZIP, or clone it into `wp-content/plugins/medical-college-manager`.
2. In wp-admin, go to **Plugins** and activate **Medical College Manager**.
3. Open **College Manager** in the admin menu for design, printing and banner settings.
4. Add content, or bulk-load it from **College Manager → CSV Import / Export**.
5. Place a shortcode such as `[mcm_consultants]` on any page, or use the **Medical College** widgets in Elementor.

## Shortcodes

| Shortcode | Shows |
| --- | --- |
| `[mcm_consultants]`, `[mcm_team]` | Faculty and staff directory |
| `[mcm_programs]`, `[mcm_courses]`, `[mcm_timetable]` | Academics |
| `[mcm_admissions]`, `[mcm_scholarships]` | Admissions and scholarships |
| `[mcm_events]`, `[mcm_tenders]`, `[mcm_policies]` | Events, open tenders, policies |
| `[mcm_publications]`, `[mcm_research]` | Research output |
| `[mcm_jobs]` (alias `[mcm_vacancies]`) | Jobs / vacancies: open by default, `status="closed"` or `"all"` |
| `[mcm_students]` | Student list (safe fields only) |
| `[mcm_notice_ticker]`, `[mcm_notice_board]`, `[mcm_notices]` | Notices, plus open jobs and tenders (`include="notice,job,tender"`) |
| `[mcm_department_page]` | The whole department page template |
| `[mcm_department_introduction]`, `[mcm_department_vision_mission]`, `[mcm_department_services]`, `[mcm_department_faculty]`, `[mcm_department_publications]`, `[mcm_department_contact]` | One department section each (`department="anatomy"`, or the department being viewed) |
| `[mcm_department_hero]`, `[mcm_department_message]`, `[mcm_department_intro]`, `[mcm_department_sections]` | Earlier department page parts (unchanged) |
| `[mcm_blog]`, `[mcm_social_bar]` | Blog posts, social links |

Common attributes: `view`, `columns`, `count`, `filters="yes"`, `print`, `paginate`, and connector filters such as `department="anatomy"` or `program="mbbs"`. The full reference is on the plugin's settings page and in `readme.txt`.

## Project structure

```
medical-college-manager.php   Bootstrap, constants, activation and upgrades
includes/                     One class per feature (types, fields, query engine, shortcodes, templates, import, Elementor…)
templates/archive-mcm.php     Archive and department page template (overridable from a theme)
assets/                       Front-end and admin CSS/JS
samples/                      Example CSV files for the importer
languages/                    Translation template (.pot)
```

## Security

Please report security issues privately to the maintainer rather than opening a public issue.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
