# Brightway School — public website (frontend)

A multi-page school website built with **HTML5, CSS3 and vanilla JavaScript**. No frameworks, no build step.
It is the public face of a future School Management System (Core PHP + MySQLi); the PHP backend, database and
admin panel are **not** part of this phase.

"Brightway School" is a placeholder name. Anything in a dashed underline (e.g. `[Add grade range]`) is editable placeholder content.

## Run it locally

The navbar and footer are shared components loaded with `fetch()` (see `assets/js/main.js`).
Browsers block `fetch()` on `file://`, so **open the site through a local server**, not by double-clicking the HTML files.

```bash
cd school-management-system
python3 -m http.server 8000      # then open http://localhost:8000
# or, with PHP installed:
php -S localhost:8000
# or: VS Code "Live Server", XAMPP/WAMP htdocs, etc.
```

If the components fail to load, a visible yellow notice tells you to use a server.

## Structure

```
school-management-system/
├── index.html          Home (13 sections, long-form)
├── about.html          About the school
├── academics.html      Academic programs
├── admissions.html     Admissions info + inquiry form
├── gallery.html        Filterable gallery + lightbox
├── contact.html        Contact details, form, map placeholder
├── components/
│   ├── navbar.html     Floating capsule navbar (shared)
│   └── footer.html     Footer (shared)
├── assets/
│   ├── css/style.css          Design tokens, components, sections
│   ├── css/responsive.css     Breakpoints: 1100 / 900 / 640
│   ├── js/main.js             Components, nav, reveal-on-scroll, form validation
│   ├── js/gallery.js          Gallery filter + lightbox (gallery page only)
│   └── images/                Placeholder artwork, icon sprite (icons.svg), favicon
└── README.md
```

## Replacing the placeholder artwork

All people and scene images are **illustrated placeholders** (SVG), not photographs.
To use real photos, drop in files and update the `src` attributes:

| Where | Files |
|---|---|
| Home hero arches | `assets/images/student-1.svg`, `student-2.svg`, `student-3.svg` |
| About photo (blue backdrop) | `assets/images/about-teacher.svg` |
| Staff portraits | `teacher.svg`, `student-2/3/5.svg` |
| Gallery and cards | `assets/images/gallery/*.svg` (names: `campus-`, `classroom-`, `activity-`, `event-` + 1–3) |

For the hero, use portrait-orientation photos with a transparent or cut-out background (PNG/WebP) so they sit inside the pastel arches.
Gallery categories are set by `data-category` on each `.gallery__item` in `gallery.html`.

## Editing content and branding

- School name: search for `Brightway` (navbar, footer, page titles).
- Colours, radii, spacing: CSS variables at the top of `assets/css/style.css`.
- Font: Plus Jakarta Sans from Google Fonts (falls back to system fonts offline).
- No statistics, fees, addresses, awards or testimonials are invented; fill these in yourself.

## Forms

All forms use `data-validate` (handled in `main.js`): required fields, email/phone format, minimum length, consent checkbox, inline errors and a success/error banner.
**They do not send data yet.** Each form carries a `data-endpoint` attribute (`php/contact-submit.php`, `php/admission-submit.php`) where the PHP handler will go.
In `main.js`, replace the "FRONTEND ONLY" block with a `fetch(form.dataset.endpoint, { method: 'POST', body: new FormData(form) })`, or switch to a normal `action`/`method` post.

## Preparing for PHP / MySQLi

1. **Shared components → includes.** Rename `index.html` etc. to `.php`, convert `components/navbar.html` and `footer.html` to `.php`, and replace each `<div data-include="…">` with `<?php include 'components/navbar.php'; ?>`. The `fetch()` loader in `main.js` can then be removed. Update the links from `*.html` to `*.php` (or add URL rewrites).
2. **Active nav state** uses `<body data-page="…">`; in PHP you can set `$page` before the include.
3. **Dynamic content later** (announcements, events, gallery, admissions dates, staff) maps to clearly separated blocks: `.events`, `.mosaic`/`.gallery`, `.dates`, `.staff`. Loop over database rows to output the same markup.
4. Keep `assets/` as is; add backend folders (`admin/`, `php/`, `config/`, `uploads/`) next to the public pages.

## Browser checks performed

All six pages were loaded through a local server at 393, 768, 1024 and 1440 px wide: no horizontal overflow, no failed requests or JS errors, all page links resolve, mobile menu opens/closes, form validation (errors and success) and gallery filter/lightbox work. Animations respect `prefers-reduced-motion`.
