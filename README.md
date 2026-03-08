# pixel-tracks
 
 Small PHP website to manage GPX tracks.
 
 ## Goal
 
 Provide a lightweight self-hosted web app where you can:
 - upload GPX files
 - browse your uploaded tracks
 - view basic track stats (distance, elevation, total points)
 - view tracks on a map
 - share a map view via a share link
 
 Authentication is handled via email “magic links”.
 
 ## Requirements
 
 - PHP (project targets `^8.3`)
 - Composer
 - Node.js / npm (only needed to build static assets)
 - SQLite extension (`ext-sqlite3`)
 
 Alternatively, use the provided `docker compose` setup.
 
 ## Configuration
 
 Environment variables are loaded from `.env` (see `.env.dist` for a template).
 
 Key variables:
 - `BASE_URL` (used when generating magic links)
 - `APPLICATION_MODE` (`development|production|test`)
 - `MAIL_PROVIDER` / `MAIL_PROVIDER_DSN` (default SMTP points to the included Mailpit container)
 - `EMAIL_FROM`
 - `LOGIN_TOLERANCE_TIME`
 - `PAGINATION_IPP`
 
 ## Quickstart (Docker)

1. Copy env template:

   `cp .env.dist .env`

2. Start containers:

   `make start`

3. Open a shell in the app container:

   `make cli`

4. Install dependencies and copy assets:

   `composer install`

   `composer copy-assets`

5. Open the app:

   `http://localhost/`

Mailpit UI (for catching magic-link emails in dev):

`http://localhost:8025/`
 
 ## Running without Docker
 
 1. Copy env template:
 
    `cp .env.dist .env`
 
 2. Install dependencies and copy assets:

   `composer install`

   `composer copy-assets`
 
 3. Ensure writable folders exist:
 
    `var/logs/`
 
    `var/data/`
 
    `var/database/`
 
 4. Point your web server document root to `public/`.
 
 ## Development

- Enable/disable Xdebug in Docker:

  `make xdebug-enable`

  `make xdebug-disable`

- Other useful commands:

  `make help` - Show all available Makefile targets

  `make build` - Build Docker containers

  `make rebuild` - Rebuild containers without cache

  `make stop` - Stop containers

  `make clean` - Stop and remove containers
 
 ## CLI
 
 The project includes a small console entrypoint at `bin/console` with migration and transfer-generation commands.
 
 ## Tests
 
 Run static analysis + tests:
 
 `composer tests`
 
 ## License
 
 MIT (see `LICENSE`).
