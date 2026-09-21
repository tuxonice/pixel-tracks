# Bootstrap 5 Templating Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the AdminLTE 3 (Bootstrap 4 + jQuery) theme with plain Bootstrap 5.3.3 + Bootstrap Icons 1.11.3, both loaded from CDN, giving pixel-tracks a lighter, modern look while preserving every existing page, route, and interaction.

**Architecture:** Rewrite the two Twig layout files (`Default/base.html.twig`, `Error/base.html.twig`) and the shared header/flash/pagination blocks to reference Bootstrap 5 + Bootstrap Icons via `cdn.jsdelivr.net` (with SRI hashes) instead of the vendored AdminLTE/jQuery/FontAwesome assets; update each page template's Bootstrap-4-specific classes/attributes to their v5 equivalents; then delete the now-unused vendored asset directories.

**Tech Stack:** Symfony 7.4 Twig templates, Bootstrap 5.3.3 (CDN), Bootstrap Icons 1.11.3 (CDN). No JS build step, no npm — matches the project's existing "vendor or CDN, copy via `composer copy-assets`" pattern.

**Spec:** `docs/superpowers/specs/2026-09-21-bootstrap5-migration-design.md`

## Global Constraints

- Bootstrap 5.3.3 and Bootstrap Icons 1.11.3 are loaded from `cdn.jsdelivr.net` with SRI `integrity`/`crossorigin` attributes — never vendored into `src/Resources/`.
- AdminLTE, jQuery, and `bs-custom-file-input` are removed entirely — no page in scope may reference them once this plan is done.
- `templates/Default/Mail/*.twig` and `templates/Default/map.html.twig` are out of scope and must not be modified.
- No automated test suite exists in this project (see `CLAUDE.md`) — every task's verification step is a manual `curl`/`grep` check or, in the final task, a browser check. Do not add a test framework as part of this plan.
- The app runs via Docker Compose (`make start`); `bin/console`/`composer` commands run inside the container via `docker compose exec -u www-data app <command>`.

---

## Verification helper: authenticating for `curl` checks

Several tasks below need to check a page that requires a logged-in session (`/profile`, `/track/info/{key}`). Because auth is passwordless (magic link via email, caught by Mailpit in dev), the following self-contained recipe gets an authenticated session cookie jar. It's repeated verbatim in each task that needs it — run it fresh each time (Mailpit's in-memory store can be empty after a container restart).

```bash
scratch=/tmp/pixel-tracks-verify
mkdir -p "$scratch"
jar="$scratch/cookies.txt"
rm -f "$jar"

# 1. Get the send-magic-link form and its CSRF token
form=$(curl -sS -c "$jar" http://localhost/send-magic-link)
token=$(echo "$form" | grep -oP 'name="_token" value="\K[^"]+')

# 2. Submit the form for a throwaway test address
curl -sS -b "$jar" -c "$jar" -o /dev/null \
  -d "email=bootstrap5-verify@example.com" -d "_token=$token" \
  http://localhost/send-magic-link

# 3. Pull the magic link out of the email Mailpit just received
msg_id=$(curl -sS "http://localhost:8125/api/v1/messages" | php -r '
$d = json_decode(file_get_contents("php://stdin"), true);
echo $d["messages"][0]["ID"] ?? "";
')
link=$(curl -sS "http://localhost:8125/api/v1/message/$msg_id" | php -r '
$d = json_decode(file_get_contents("php://stdin"), true);
$text = $d["Text"] ?? $d["HTML"] ?? "";
if (preg_match("#http://localhost/login/check\?[^\s\"<]+#", $text, $m)) { echo $m[0]; }
')

# 4. Follow it to authenticate; $jar now holds a logged-in session cookie
curl -sS -b "$jar" -c "$jar" -o /dev/null "$link"
```

After this, `curl -sS -b "$scratch/cookies.txt" http://localhost/profile` returns the authenticated profile page.

---

### Task 1: Base layout — CDN Bootstrap 5 + Bootstrap Icons, drop AdminLTE/jQuery, CSP update

**Files:**
- Modify: `templates/Default/base.html.twig`
- Modify: `templates/Error/base.html.twig`
- Modify: `src/EventListener/SecurityHeadersListener.php`

**Interfaces:**
- Produces: `base.html.twig` and `Error/base.html.twig` both load Bootstrap 5's CSS/JS bundle and Bootstrap Icons' CSS from `cdn.jsdelivr.net`, and link `/css/custom.css` (created in Task 6 — a 404 on it until then is expected and harmless). Both keep the `{% block title %}`/`{% block content %}` blocks and the `Default/Blocks/header.html.twig` include with the same name, which Task 2 relies on.

- [ ] **Step 1: Replace `templates/Default/base.html.twig`**

```twig
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{% block title %}{% endblock %} - PixelTracks</title>
    <link rel="icon" type="image/x-icon" href="/img/favicon.ico">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous">
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+"
        crossorigin="anonymous">
    <link rel="stylesheet" href="/css/custom.css">
</head>
<body class="bg-body-tertiary">
{{ include('Default/Blocks/header.html.twig') }}
<main class="container py-4">
    {{ include('Default/Blocks/flash-messages.html.twig') }}
    {% block content %}
    {% endblock %}
</main>
<footer class="container py-4 text-center text-body-secondary small">
    beyond the mountains
</footer>
<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
    crossorigin="anonymous"></script>
</body>
</html>
```

- [ ] **Step 2: Replace `templates/Error/base.html.twig`**

```twig
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{% block title %}{% endblock %} - PixelTracks</title>
    <link rel="icon" type="image/x-icon" href="/img/favicon.ico">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous">
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+"
        crossorigin="anonymous">
    <link rel="stylesheet" href="/css/custom.css">
</head>
<body class="bg-body-tertiary">
{{ include('Default/Blocks/header.html.twig') }}
<main class="container py-4">
    {% block content %}
    {% endblock %}
</main>
<footer class="container py-4 text-center text-body-secondary small">
    beyond the mountains
</footer>
<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
    crossorigin="anonymous"></script>
</body>
</html>
```

- [ ] **Step 3: Update the CSP's `font-src` in `src/EventListener/SecurityHeadersListener.php`**

Change:

```php
            . "font-src 'self' https://fonts.gstatic.com; "
```

to:

```php
            . "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; "
```

(Bootstrap Icons' `.woff2` file is served from `cdn.jsdelivr.net`; `script-src`/`style-src` already allow that host for Leaflet/jsPanel on the map page, so no other CSP line changes.)

- [ ] **Step 4: Verify**

```bash
curl -sS http://localhost/send-magic-link -o /tmp/pixel-tracks-verify-task1.html
grep -c 'cdn.jsdelivr.net/npm/bootstrap@5.3.3' /tmp/pixel-tracks-verify-task1.html   # expect 2 (css + js)
grep -c 'bootstrap-icons@1.11.3' /tmp/pixel-tracks-verify-task1.html                  # expect 1
grep -c -i 'adminlte\|jquery\|bs-custom-file-input' /tmp/pixel-tracks-verify-task1.html  # expect 0
curl -sSI http://localhost/send-magic-link | grep -i 'content-security-policy'
```

Expected: the counts above, and the CSP header line contains `font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net`.

- [ ] **Step 5: Commit**

```bash
git add templates/Default/base.html.twig templates/Error/base.html.twig src/EventListener/SecurityHeadersListener.php
git commit -m "feat(ui): switch base layout to CDN Bootstrap 5 + Bootstrap Icons"
```

---

### Task 2: Rebuild the navbar (`templates/Default/Blocks/header.html.twig`)

**Files:**
- Modify: `templates/Default/Blocks/header.html.twig`

**Interfaces:**
- Consumes: is `{{ include(...) }}`'d by `base.html.twig`/`Error/base.html.twig` (Task 1) right after `<body>`; relies on Bootstrap 5 JS (loaded in Task 1) for the collapse toggle.
- Produces: a `#navbarCollapse` id used by its own `navbar-toggler` button (self-contained, no other template references this id).

- [ ] **Step 1: Replace `templates/Default/Blocks/header.html.twig`**

```twig
<nav class="navbar navbar-expand-md bg-primary" data-bs-theme="dark">
    <div class="container">
        <a href="/" class="navbar-brand fw-semibold">
            <img src="/img/logo.png" alt="PixelTracks Logo" class="rounded-circle" width="30" height="30" style="opacity: .85">
            PixelTracks
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse"
                aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarCollapse">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a href="/profile" class="nav-link"><i class="bi bi-person-circle me-1"></i>Profile</a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link"><i class="bi bi-info-circle me-1"></i>About</a>
                </li>
                {% if app.user %}
                    <li class="nav-item">
                        <a href="/logout" class="nav-link"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
                    </li>
                {% endif %}
            </ul>
        </div>
    </div>
</nav>
```

- [ ] **Step 2: Verify (unauthenticated: brand, toggler, Profile/About, no Logout)**

```bash
curl -sS http://localhost/send-magic-link -o /tmp/pixel-tracks-verify-task2.html
grep -c 'navbar-toggler' /tmp/pixel-tracks-verify-task2.html            # expect 1
grep -c 'data-bs-target="#navbarCollapse"' /tmp/pixel-tracks-verify-task2.html  # expect 1
grep -c 'id="navbarCollapse"' /tmp/pixel-tracks-verify-task2.html       # expect 1
grep -c 'bi bi-person-circle\|bi bi-info-circle' /tmp/pixel-tracks-verify-task2.html  # expect 2
grep -c 'bi-box-arrow-right' /tmp/pixel-tracks-verify-task2.html        # expect 0 (not logged in)
```

- [ ] **Step 3: Verify (authenticated: Logout link appears)**

Run the authentication recipe from "Verification helper" above, then:

```bash
curl -sS -b /tmp/pixel-tracks-verify/cookies.txt http://localhost/profile -o /tmp/pixel-tracks-verify-task2b.html
grep -c 'bi-box-arrow-right' /tmp/pixel-tracks-verify-task2b.html  # expect 1
```

- [ ] **Step 4: Commit**

```bash
git add templates/Default/Blocks/header.html.twig
git commit -m "feat(ui): rebuild navbar on Bootstrap 5 with a working mobile toggle"
```

---

### Task 3: Fix remaining Bootstrap-4 leftovers in shared blocks and the magic-link page

**Files:**
- Modify: `templates/Default/Blocks/flash-messages.html.twig`
- Modify: `templates/Default/Blocks/pagination.html.twig`
- Modify: `templates/Default/magic-link.html.twig`

**Interfaces:** None — each is an isolated, single-class/attribute fix with no cross-file dependency.

- [ ] **Step 1: Fix the dismiss button in `templates/Default/Blocks/flash-messages.html.twig`**

Change:

```twig
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
```

to:

```twig
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
```

(Bootstrap 5's `.btn-close` renders its own X glyph via CSS — the manual `&times;` span is no longer needed.)

- [ ] **Step 2: Fix `templates/Default/Blocks/pagination.html.twig`**

Change:

```twig
<ul class="pagination pagination-sm m-0 float-right">
```

to:

```twig
<ul class="pagination pagination-sm m-0 float-end">
```

- [ ] **Step 3: Fix `templates/Default/magic-link.html.twig`**

Change:

```twig
                <div class="py-5 text-left">
```

to:

```twig
                <div class="py-5 text-start">
```

- [ ] **Step 4: Verify the static renames**

```bash
grep -n 'btn-close' templates/Default/Blocks/flash-messages.html.twig      # expect 1 match
grep -n 'data-dismiss\|class="close"' templates/Default/Blocks/flash-messages.html.twig  # expect 0 matches
grep -n 'float-end' templates/Default/Blocks/pagination.html.twig          # expect 1 match
grep -n 'text-start' templates/Default/magic-link.html.twig                # expect 1 match
grep -n 'float-right\|text-left' templates/Default/Blocks/pagination.html.twig templates/Default/magic-link.html.twig  # expect 0 matches
```

- [ ] **Step 5: Verify a real flash message renders the new close button**

Run the authentication recipe from "Verification helper" above (step 2 of that recipe submits the magic-link form, which sets a `success` flash — "Please verify your mailbox" — before redirecting back to `/send-magic-link`). Capture that redirect response instead of discarding it:

```bash
scratch=/tmp/pixel-tracks-verify
jar="$scratch/cookies.txt"
rm -f "$jar"
form=$(curl -sS -c "$jar" http://localhost/send-magic-link)
token=$(echo "$form" | grep -oP 'name="_token" value="\K[^"]+')
curl -sS -b "$jar" -c "$jar" -L -o /tmp/pixel-tracks-verify-task3.html \
  -d "email=bootstrap5-verify@example.com" -d "_token=$token" \
  http://localhost/send-magic-link
grep -c 'btn-close' /tmp/pixel-tracks-verify-task3.html  # expect 1
grep -c 'Please verify your mailbox' /tmp/pixel-tracks-verify-task3.html  # expect 1
```

- [ ] **Step 6: Commit**

```bash
git add templates/Default/Blocks/flash-messages.html.twig templates/Default/Blocks/pagination.html.twig templates/Default/magic-link.html.twig
git commit -m "fix(ui): replace remaining Bootstrap 4 classes with v5 equivalents"
```

---

### Task 4: Migrate the home/profile page (`templates/Default/home.html.twig`)

**Files:**
- Modify: `templates/Default/home.html.twig`

**Interfaces:** None — self-contained page template extending `Default/base.html.twig` (Task 1).

- [ ] **Step 1: Update the track-list card and its icons**

Change:

```twig
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">Tracks</h3>
                </div>
```

to:

```twig
            <div class="card border-0 shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Tracks</h3>
                </div>
```

Change:

```twig
                                        <a href="/map/{{ track.key }}" target="_blank" class="nav-link" title="Show on map">
                                            <i class="fas fa-solid fa-map"></i>
                                        </a>
                                    </td>
                                    <td>
                                        <a href="/track/info/{{track.key}}" class="nav-link" title="Show info">
                                            <i class="fas fa-solid fa-info-circle"></i>
                                        </a>
```

to:

```twig
                                        <a href="/map/{{ track.key }}" target="_blank" class="nav-link" title="Show on map">
                                            <i class="bi bi-map"></i>
                                        </a>
                                    </td>
                                    <td>
                                        <a href="/track/info/{{track.key}}" class="nav-link" title="Show info">
                                            <i class="bi bi-info-circle"></i>
                                        </a>
```

- [ ] **Step 2: Update the upload card, form group, and file input**

Change:

```twig
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">Upload Track</h3>
                </div>
                <form method="POST" enctype="multipart/form-data" action="/track/upload">
                    <input type="hidden" name="_token" value="{{ csrf_token('track-upload') }}"/>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="trackName">Track Name</label>
                            <input type="text" class="form-control" id="trackName" name="trackName" placeholder="Enter track name" required/>
                        </div>
                        <div class="form-group">
                            <label for="exampleInputFile">Track File (Only GPX files)</label>
                            <div class="input-group">
                                <div class="custom-file">
                                    <input name="trackFile" type="file" id="trackFile" class="custom-file-input" required/>
                                    <label class="custom-file-label" for="trackFile">Choose file</label>
                                </div>
                            </div>
                        </div>
                    </div>
```

to:

```twig
            <div class="card border-0 shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Upload Track</h3>
                </div>
                <form method="POST" enctype="multipart/form-data" action="/track/upload">
                    <input type="hidden" name="_token" value="{{ csrf_token('track-upload') }}"/>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="trackName" class="form-label">Track Name</label>
                            <input type="text" class="form-control" id="trackName" name="trackName" placeholder="Enter track name" required/>
                        </div>
                        <div class="mb-3">
                            <label for="trackFile" class="form-label">Track File (Only GPX files)</label>
                            <input name="trackFile" type="file" id="trackFile" class="form-control" required/>
                        </div>
                    </div>
```

- [ ] **Step 3: Verify the static markup**

```bash
grep -c 'border-0 shadow-sm' templates/Default/home.html.twig     # expect 2
grep -c 'bi bi-map\|bi bi-info-circle' templates/Default/home.html.twig  # expect 2
grep -c 'custom-file\|form-group\|fas fa-solid' templates/Default/home.html.twig  # expect 0
```

- [ ] **Step 4: Verify the upload flow still works end to end**

Run the authentication recipe from "Verification helper" above, then upload the repo's sample GPX and confirm it lands on the profile page with the new markup:

```bash
scratch=/tmp/pixel-tracks-verify
jar="$scratch/cookies.txt"
form=$(curl -sS -b "$jar" -c "$jar" http://localhost/profile)
token=$(echo "$form" | grep -oP 'name="_token" value="\K[^"]+' | head -1)
curl -sS -b "$jar" -c "$jar" -L -o /tmp/pixel-tracks-verify-task4.html \
  -F "_token=$token" \
  -F "trackName=Bootstrap5 Verify Track" \
  -F "trackFile=@var/data/sample.gpx;filename=sample.gpx;type=application/gpx+xml" \
  http://localhost/track/upload
grep -c 'New file uploaded' /tmp/pixel-tracks-verify-task4.html   # expect 1
grep -c 'Bootstrap5 Verify Track' /tmp/pixel-tracks-verify-task4.html  # expect 1
grep -c 'bi bi-map' /tmp/pixel-tracks-verify-task4.html           # expect 1
```

(`_token` is the CSRF field name Symfony renders for `csrf_token('track-upload')` in the upload form on `/profile`; it's the only `_token` field on that page.)

- [ ] **Step 5: Commit**

```bash
git add templates/Default/home.html.twig
git commit -m "feat(ui): migrate home/profile page to Bootstrap 5"
```

---

### Task 5: Migrate the track info page and delete modal (`templates/Default/track.html.twig`)

**Files:**
- Modify: `templates/Default/track.html.twig`

**Interfaces:** None — self-contained page template extending `Default/base.html.twig` (Task 1). The `data-bs-target="#modal-default"` attribute added here must keep matching the modal's existing `id="modal-default"` (unchanged).

- [ ] **Step 1: Update the info card and its "Back to List"/"Delete" trigger button**

Change:

```twig
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">Track Info</h3>
                </div>
```

to:

```twig
            <div class="card border-0 shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Track Info</h3>
                </div>
```

Change:

```twig
                                <a href="/profile" class="btn btn-default">
                                    <i class="fas fa-list"></i> Back to List
                                </a>
                                <button type="button" class="btn btn-danger float-right" data-toggle="modal" data-target="#modal-default">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
```

to:

```twig
                                <a href="/profile" class="btn btn-default">
                                    <i class="bi bi-list"></i> Back to List
                                </a>
                                <button type="button" class="btn btn-danger float-end" data-bs-toggle="modal" data-bs-target="#modal-default">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
```

- [ ] **Step 2: Update the delete-confirmation modal**

Change:

```twig
                <div class="modal-header">
                    <h4 class="modal-title">Delete Track</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Are you sure do you want to delete <b>{{ track.name }}</b> track?</p>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <form action="/track/delete" method="post">
                        <input type="hidden" name="_token" value="{{ csrf_token('track-delete') }}"/>
                        <input type="hidden" name="track_key" value="{{ track.key }}"/>
                        <button type="submit" class="btn btn-danger float-right" style="margin-right: 5px;">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </form>
```

to:

```twig
                <div class="modal-header">
                    <h4 class="modal-title">Delete Track</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure do you want to delete <b>{{ track.name }}</b> track?</p>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-bs-dismiss="modal">Cancel</button>
                    <form action="/track/delete" method="post">
                        <input type="hidden" name="_token" value="{{ csrf_token('track-delete') }}"/>
                        <input type="hidden" name="track_key" value="{{ track.key }}"/>
                        <button type="submit" class="btn btn-danger float-end" style="margin-right: 5px;">
                            <i class="bi bi-trash"></i> Delete
                        </button>
                    </form>
```

- [ ] **Step 3: Verify the static markup**

```bash
grep -c 'data-bs-toggle="modal" data-bs-target="#modal-default"' templates/Default/track.html.twig  # expect 1 (both attributes are on the same button's line)
grep -c 'data-bs-dismiss="modal"' templates/Default/track.html.twig  # expect 2
grep -c 'btn-close' templates/Default/track.html.twig                # expect 1
grep -c 'float-end' templates/Default/track.html.twig                # expect 2
grep -c 'bi bi-list\|bi bi-trash' templates/Default/track.html.twig  # expect 3 (list once, trash twice)
grep -c 'data-toggle=\|data-target=\|data-dismiss=\|fas fa-\|float-right\|class="close"' templates/Default/track.html.twig  # expect 0
```

- [ ] **Step 4: Verify against a live track page**

Run the authentication recipe from "Verification helper" above, then upload a track (same as Task 4's Step 4) and follow it through to `/track/info/{key}`:

```bash
scratch=/tmp/pixel-tracks-verify
jar="$scratch/cookies.txt"
profile=$(curl -sS -b "$jar" -c "$jar" http://localhost/profile)
token=$(echo "$profile" | grep -oP 'name="_token" value="\K[^"]+' | head -1)
curl -sS -b "$jar" -c "$jar" -o /dev/null \
  -F "_token=$token" -F "trackName=Task5 Verify" \
  -F "trackFile=@var/data/sample.gpx;filename=sample.gpx;type=application/gpx+xml" \
  http://localhost/track/upload

profile2=$(curl -sS -b "$jar" -c "$jar" http://localhost/profile)
key=$(echo "$profile2" | grep -oP '/track/info/\K[a-zA-Z0-9_-]+' | head -1)
curl -sS -b "$jar" -c "$jar" "http://localhost/track/info/$key" -o /tmp/pixel-tracks-verify-task5.html
grep -c 'data-bs-target="#modal-default"' /tmp/pixel-tracks-verify-task5.html  # expect 1
grep -c 'id="modal-default"' /tmp/pixel-tracks-verify-task5.html              # expect 1
grep -c 'btn-close' /tmp/pixel-tracks-verify-task5.html                       # expect 1
```

- [ ] **Step 5: Commit**

```bash
git add templates/Default/track.html.twig
git commit -m "feat(ui): migrate track info/delete page to Bootstrap 5"
```

---

### Task 6: Remove old vendored assets, add `custom.css`, and do the final visual pass

**Files:**
- Delete: `src/Resources/plugins/bootstrap/`, `src/Resources/plugins/bs-custom-file-input/`, `src/Resources/plugins/jquery/`, `src/Resources/plugins/fontawesome-free/`
- Delete: `src/Resources/js/adminlte.js`, `src/Resources/css/adminlte.css`
- Delete: `src/Resources/images/user1-128x128.jpg`, `src/Resources/images/user3-128x128.jpg`, `src/Resources/images/user8-128x128.jpg`
- Create: `src/Resources/css/custom.css`
- Modify: `bin/copy-assets.php`

**Interfaces:** None — this task only runs once every earlier task has removed all references to the deleted assets (verified in Steps 1-5 of Tasks 1-5 above, which already assert zero remaining `adminlte`/`jquery`/`fas fa-`/`custom-file` matches across the templates touched by this plan).

- [ ] **Step 1: Confirm nothing in scope still references the assets being deleted**

```bash
grep -rniE 'adminlte|jquery|fontawesome|bs-custom-file-input|fas fa-' templates/Default/base.html.twig templates/Error/base.html.twig templates/Default/Blocks/ templates/Default/home.html.twig templates/Default/track.html.twig templates/Default/magic-link.html.twig
```

Expected: no output. (`templates/Default/map.html.twig` is out of scope and is expected to still reference `code.jquery.com` directly — do not touch it.)

- [ ] **Step 2: Delete the unused vendored source directories/files**

```bash
git rm -r src/Resources/plugins/bootstrap src/Resources/plugins/bs-custom-file-input src/Resources/plugins/jquery src/Resources/plugins/fontawesome-free
git rm src/Resources/js/adminlte.js src/Resources/css/adminlte.css
git rm src/Resources/images/user1-128x128.jpg src/Resources/images/user3-128x128.jpg src/Resources/images/user8-128x128.jpg
```

- [ ] **Step 3: Create `src/Resources/css/custom.css`**

```css
/* Navbar contrast: Bootstrap's dark-theme navbar sets .nav-link to
   rgba(255,255,255,.55) by default, which falls short of WCAG AA's 4.5:1
   ratio on the bg-primary (#0d6efd) background used here. Force full white. */
.navbar.bg-primary .nav-link,
.navbar.bg-primary .navbar-brand {
    color: #fff;
}

.navbar.bg-primary .nav-link:hover,
.navbar.bg-primary .nav-link:focus {
    color: #fff;
    text-decoration: underline;
}
```

- [ ] **Step 4: Remove the now-pointless `plugins` copy entry in `bin/copy-assets.php`**

Change:

```php
$copies = [
    ['src' => $resourcesDir . '/plugins', 'dest' => $publicDir . '/plugins'],
    ['src' => $resourcesDir . '/css', 'dest' => $publicDir . '/css'],
    ['src' => $resourcesDir . '/js', 'dest' => $publicDir . '/js'],
    ['src' => $resourcesDir . '/images', 'dest' => $publicDir . '/img'],
];
```

to:

```php
$copies = [
    ['src' => $resourcesDir . '/css', 'dest' => $publicDir . '/css'],
    ['src' => $resourcesDir . '/js', 'dest' => $publicDir . '/js'],
    ['src' => $resourcesDir . '/images', 'dest' => $publicDir . '/img'],
];
```

- [ ] **Step 5: Remove the stale copies already sitting in `public/`, then re-run `composer copy-assets`**

`copy-assets.php` only ever copies — it never deletes destination files whose source disappeared — so the old copies under `public/` must be removed by hand once, and `public/img/` needs the three deleted avatar files removed too since `images/` is still copied wholesale:

```bash
docker compose exec -u www-data app rm -rf public/plugins
docker compose exec -u www-data app rm -f public/css/adminlte.css public/js/adminlte.js
docker compose exec -u www-data app rm -f public/img/user1-128x128.jpg public/img/user3-128x128.jpg public/img/user8-128x128.jpg
docker compose exec -u www-data app composer copy-assets
```

- [ ] **Step 6: Verify the asset cleanup**

```bash
ls src/Resources/plugins 2>&1   # expect "No such file or directory" (or an empty dir if git left it — either is fine)
ls src/Resources/css            # expect: custom.css  map-style.css
docker compose exec -u www-data app test -f public/css/custom.css && echo "custom.css present"
docker compose exec -u www-data app test ! -d public/plugins && echo "public/plugins removed"
docker compose exec -u www-data app test ! -f public/css/adminlte.css && echo "adminlte.css removed"
```

Expected: all three echoed confirmations print.

- [ ] **Step 7: Full manual browser pass**

Using the `run` skill against the running Docker stack (`http://localhost/`), walk through and visually confirm, with the browser console open (no errors, no CSP violations):

1. `/send-magic-link` — form renders, navbar shows Profile/About (no Logout), mobile navbar-toggler works below ~768px width.
2. Request a magic link, open it in Mailpit (`http://localhost:8125/`), follow the link — lands on `/profile` showing Bootstrap Icons (not broken-image glyphs) and a dismissible success alert with a working close button.
3. `/profile` — upload `var/data/sample.gpx` with a track name; new row appears with working map/info icon links; both cards show the `border-0 shadow-sm` look.
4. `/track/info/{key}` for the uploaded track — click "Delete", confirm the modal opens (Bootstrap 5 JS working without jQuery), click "Cancel" to close it, then reopen and confirm deletion actually removes the track and redirects to `/profile` with a flash message.
5. Visit a nonexistent route to trigger the 404 error page — confirm it renders with the same navbar/footer as normal pages, no broken assets.
6. `/map/{key}` for a remaining track — confirm it still renders the Leaflet map and the jsPanel info overlay (this page is untouched by this plan; regressions here would indicate a CSP or shared-asset mistake).

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "chore(ui): remove AdminLTE/jQuery/FontAwesome vendored assets, add custom.css"
```

## Self-review notes

- **Spec coverage:** every file listed in the spec's "File-by-file changes" section has a task (base/Error base + CSP → Task 1; header → Task 2; flash-messages/pagination/magic-link → Task 3; home → Task 4; track → Task 5; asset deletions/custom.css/copy-assets.php → Task 6). The spec's "Out of scope" items (Mail templates, map.html.twig) are explicitly called out as untouched in Tasks 1 and 6 and checked in Task 6 Step 1's grep and Task 6 Step 7.6's manual check.
- **Placeholder scan:** no TBD/TODO; every step has literal, runnable code or commands.
- **Type/name consistency:** `#navbarCollapse` (Task 2) is the only cross-reference between the toggler and collapse `<div>` and both live in the same file/step. `#modal-default` (Task 5) likewise only self-references within `track.html.twig`. No shared function/class signatures are introduced by this plan (Twig templates + one CSP string + one PHP array literal), so there's no cross-task signature drift to check.
