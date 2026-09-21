# Bootstrap 5 templating/layout migration — design

## Goal

Replace the current AdminLTE 3 (Bootstrap 4 + jQuery) theme with plain Bootstrap 5,
loaded from CDN, giving the app a lighter, more modern look modeled on the
[weather-dashboard](https://github.com/tuxonice/weather-dashboard) reference project
— soft-shadow cards, a `bg-primary`/dark navbar, Bootstrap Icons — while preserving
every existing page, route, and interaction (upload form, track delete modal,
dismissible flash messages, pagination, magic-link form).

This is a light visual refresh, not a redesign: layout structure (top navbar +
container content + footer, card-based pages) stays the same; only the framework
underneath and small styling details (shadows, spacing, colors) change.

## Decisions

- **Drop AdminLTE entirely.** The app only ever used AdminLTE's light top-nav
  layout (`layout-top-nav`), never its dashboard widgets/sidebar. AdminLTE 3 is
  Bootstrap-4-only and hard-depends on jQuery (see `src/Resources/js/adminlte.js`,
  which requires `jquery` in its UMD wrapper); AdminLTE 4 targets Bootstrap 5 but
  restructured around a sidebar layout and dropped its simple prebuilt-dist
  distribution in favor of an npm/Vite build — not worth adopting for a layout this
  app doesn't use.
- **CDN, not vendoring.** Bootstrap 5.3.3 and Bootstrap Icons 1.11.3 are loaded
  directly from `cdn.jsdelivr.net` with Subresource Integrity hashes, matching the
  weather-dashboard reference, instead of being downloaded into
  `src/Resources/plugins/` and copied to `public/` via `composer copy-assets` (the
  pattern used for the outgoing AdminLTE/jQuery/FontAwesome/Bootstrap-4 files). This
  is consistent with the app's existing use of CDNs for Google Fonts
  (`fonts.googleapis.com`) and, on the map page, Leaflet and jsPanel
  (`unpkg.com`/`cdn.jsdelivr.net`) — it is not a new pattern for this codebase. It
  also means no build step is introduced.
- **Drop jQuery and `bs-custom-file-input`.** Both existed solely to support
  AdminLTE/Bootstrap-4's custom file input styling. Bootstrap 5 styles a plain
  `<input type="file" class="form-control">` natively, so neither is needed once
  the file input markup is updated. (`templates/Default/map.html.twig` loads its
  own separate jQuery 3.6 slim build directly from `code.jquery.com` for an
  unrelated jsPanel info overlay — that is untouched; see Out of scope.)
- **Switch FontAwesome → Bootstrap Icons.** Only 5 icons are used app-wide (map,
  info-circle, list, trash). FontAwesome is vendored as a large webfonts+CSS folder
  and is unrelated to the Bootstrap version, but since assets are already being
  touched, switching to Bootstrap Icons (loaded from the same CDN as Bootstrap
  itself) removes that vendored folder and fits Bootstrap 5 conventions, matching
  the reference project.
- **Light visual refresh, not a redesign.** Reuse Bootstrap 5's own default theme
  (default `$primary` blue, default spacing scale). The only deliberate styling
  additions are the reference project's soft-shadow/borderless card look
  (`border-0 shadow-sm`), a `bg-body-tertiary` page background, and a dark
  (`data-bs-theme="dark"`) `bg-primary` navbar — no custom color palette or
  typography.

## Current state (for reference)

- Theme: AdminLTE 3.2.0 (`src/Resources/css/adminlte.css`,
  `src/Resources/js/adminlte.js`), `layout-top-nav` body class.
- Vendored under `src/Resources/plugins/`: `bootstrap` (JS bundle only, no CSS —
  AdminLTE's CSS bundles Bootstrap 4's styles), `bs-custom-file-input`, `jquery`,
  `fontawesome-free`.
- `templates/Default/base.html.twig` and `templates/Error/base.html.twig` are
  near-duplicates: AdminLTE's `wrapper` > `content-wrapper` > `content-header` >
  nested `container` markup, loading Google Fonts, FontAwesome, `adminlte.css`,
  then at the bottom jQuery, Bootstrap JS, `bs-custom-file-input`, `adminlte.js`.
- `templates/Default/magic-link.html.twig` already uses Bootstrap 5 utility
  classes (`fw-light`, `text-body-secondary`, `row g-3`) — apparently copied from
  Bootstrap's own "Cover"-style example — except one leftover Bootstrap 4 class
  (`text-left`). This is the one template that needs the least work.
- Bootstrap-4-specific classes/attributes present today, needing v5 equivalents:
  `data-toggle`/`data-target`/`data-dismiss` → `data-bs-*`; `.close` → `.btn-close`;
  `float-right`/`text-left` → `float-end`/`text-start`; `font-weight-light` →
  `fw-light`; `.form-group` → utility spacing (`mb-3`); `.custom-file`/
  `.custom-file-input`/`.custom-file-label` → a plain `.form-control` file input.
- CSP (`src/EventListener/SecurityHeadersListener.php`) already allow-lists
  `cdn.jsdelivr.net` in `script-src` and `style-src` (for Leaflet/jsPanel); it does
  **not** yet allow it in `font-src`, which Bootstrap Icons' `.woff2` needs.

## Out of scope

- **`templates/Default/Mail/*.twig`** — fully self-contained, inline-styled HTML
  email markup (table-based, for email client compatibility). Never loaded
  Bootstrap and isn't touched.
- **`templates/Default/map.html.twig`** — a standalone page (own `<!DOCTYPE html>`,
  does not extend `Default/base.html.twig`), styled entirely by Leaflet/jsPanel/
  `map-style.css`. Its own jQuery-slim + jsPanel usage is unrelated to this
  migration and is left as-is.
- Any new test suite — none exists per `CLAUDE.md`; not introduced by this change.
- `src/Resources/images/logo.{png,svg}` — used by the navbar brand, left as-is;
  not part of the Bootstrap swap.

## File-by-file changes

### Templates

- **`templates/Default/base.html.twig`** — replace the `<head>` asset tags: drop
  Google-Fonts-adjacent AdminLTE/FontAwesome/`adminlte.css` links, add Bootstrap
  5.3.3 CSS + Bootstrap Icons 1.11.3 CSS (CDN, SRI) + a new `custom.css` link.
  Replace the AdminLTE `wrapper`/`content-wrapper`/`content-header` nesting with a
  single `<main class="container py-4">` wrapping
  `{% block content %}`/flash messages. Set `<body class="bg-body-tertiary">`.
  Simplify the footer to plain text (drop the "Design by AdminLTE.io" credit).
  Replace the script tags at the bottom with the Bootstrap 5 JS bundle (CDN, SRI)
  only — no jQuery, no `bs-custom-file-input`, no `adminlte.js`.
- **`templates/Error/base.html.twig`** — same treatment as `base.html.twig` (kept
  as a separate near-duplicate file, matching its current structure — not merged,
  to avoid an unrelated refactor).
- **`templates/Default/Blocks/header.html.twig`** — rebuild as a standard
  Bootstrap 5 navbar: `navbar navbar-expand-md bg-primary` with
  `data-bs-theme="dark"`, a working `navbar-toggler`/`#navbarCollapse` mobile
  toggle (the existing markup has the collapse div but no toggler button, so it's
  currently non-functional on mobile — fixed as part of rebuilding this block),
  and `bi-*` icons next to "Profile"/"About"/"Logout" for visual consistency with
  the reference project's nav.
- **`templates/Default/Blocks/flash-messages.html.twig`** — `.close`/
  `data-dismiss="alert"` → `.btn-close`/`data-bs-dismiss="alert"` (Bootstrap 5's
  close button is a styled empty element, not a `&times;` glyph).
- **`templates/Default/Blocks/pagination.html.twig`** — `float-right` →
  `float-end`.
- **`templates/Default/home.html.twig`** — both `.card`s get `border-0 shadow-sm`;
  `.form-group` wrappers → `mb-3`; the `.custom-file` block replaced with a plain
  `<input type="file" class="form-control" ...>` (label kept as a standalone
  `<label class="form-label">`); FontAwesome icons (`fas fa-solid fa-map`,
  `fas fa-solid fa-info-circle`) → `bi bi-map`, `bi bi-info-circle`.
- **`templates/Default/track.html.twig`** — `.card` gets `border-0 shadow-sm`;
  `data-toggle="modal" data-target="#modal-default"` → `data-bs-toggle="modal"
  data-bs-target="#modal-default"`; modal's `.close`/`data-dismiss="modal"` →
  `.btn-close`/`data-bs-dismiss="modal"` (dropping the manual `&times;` span, which
  `.btn-close` renders itself); `float-right` → `float-end`; icons `fas fa-list`/
  `fas fa-trash` → `bi bi-list`/`bi bi-trash`.
- **`templates/Default/magic-link.html.twig`** — only change: `text-left` →
  `text-start`.

### Assets

- **Delete**: `src/Resources/plugins/bootstrap/`,
  `src/Resources/plugins/bs-custom-file-input/`, `src/Resources/plugins/jquery/`,
  `src/Resources/plugins/fontawesome-free/`, `src/Resources/js/adminlte.js`,
  `src/Resources/css/adminlte.css`, `src/Resources/images/user1-128x128.jpg`,
  `src/Resources/images/user3-128x128.jpg`, `src/Resources/images/user8-128x128.jpg`
  (unreferenced AdminLTE demo avatars).
- **Add**: `src/Resources/css/custom.css` — small overrides layered on top of
  Bootstrap 5 defaults (e.g. navbar link contrast on the dark `bg-primary` navbar,
  in the same spirit as the reference project's `custom.css`); copied to
  `public/css/custom.css` by the existing `composer copy-assets` step.
- **`bin/copy-assets.php`** — remove the now-pointless `plugins` entry from the
  `$copies` array (its source directory will be empty/nonexistent).

### Config

- **`src/EventListener/SecurityHeadersListener.php`** — add
  `https://cdn.jsdelivr.net` to the CSP's `font-src` directive (currently
  `font-src 'self' https://fonts.gstatic.com`), needed for Bootstrap Icons' woff2
  file. No other CSP changes: `script-src`/`style-src` already allow
  `cdn.jsdelivr.net`.

## Testing / verification

No automated test suite exists (per `CLAUDE.md`). Verification is manual, using
the `run` skill against the Docker dev stack:

1. `composer copy-assets` inside the app container, to publish the new/removed
   assets to `public/`.
2. Load and visually check each affected page: `/profile` (track list + upload
   card, including the mobile navbar toggle), `/track/info/{key}` (info card +
   delete modal open/confirm), `/send-magic-link` (form), a flash message
   (trigger one via a normal action, e.g. after upload), and a 404/403 error page.
3. Confirm no browser console errors (in particular: no CSP violations for the
   Bootstrap/Bootstrap-Icons CDN assets, no missing-jQuery errors) and no broken
   icons (FontAwesome → Bootstrap Icons swap).
4. Confirm `/map/{key}` still renders correctly (it's untouched, but its
   `code.jquery.com`/CSP entries must remain intact for it to keep working).
