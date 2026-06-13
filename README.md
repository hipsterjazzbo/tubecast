# TubeCast

Self-hosted YouTube archiver with optional podcast feeds. Subscribe to channels, playlists, or individual videos; TubeCast indexes new uploads and downloads them in the background. Completed audio can be exposed as a private podcast RSS feed for your phone or media player.

## What it does

- **Subscribe** to YouTube channels, playlists, or single videos
- **Index** new episodes quickly via YouTube RSS, with full backfill via the YouTube Data API (when configured) or yt-dlp
- **Download** video files, audio-only podcast files, or both — per source
- **Filter** episodes by duration, title pattern, shorts, and live streams
- **Publish** token-protected podcast RSS feeds with HTTP Range support for seeking

## Requirements

| Dependency      | Version                                        |
|-----------------|------------------------------------------------|
| PHP             | `^8.5`                                         |
| Node.js         | `22+` (for building frontend assets)           |
| Docker / Podman | Latest with Compose (for containerised deploy) |

## Deploy

Requires [Docker](https://docs.docker.com/get-docker/) or [Podman](https://podman.io/) with Compose.

```bash
git clone https://github.com/hipsterjazzbo/tubecast.git && cd tubecast
docker compose up -d
```

The image is published to [GitHub Container Registry](https://github.com/hipsterjazzbo/tubecast/pkgs/container/tubecast) on each push to `master` (tagged `latest`).

Open **http://localhost:8742** and sign in with the default credentials:

- **Username:** `admin`
- **Password:** `changeme`

Change the password before exposing TubeCast to a network — copy `.env.docker.example` to `.env.docker`, set `TUBECAST_ADMIN_PASSWORD`, and run `docker compose --env-file .env.docker up -d`.

On first start the container creates two data volumes, runs database migrations, and seeds default download profiles:

| Volume            | Container path | Contents                                     |
|-------------------|----------------|----------------------------------------------|
| `tubecast-config` | `/config`      | SQLite database, app `.env`, stored commands |
| `tubecast-media`  | `/media`       | Downloaded videos and podcast audio          |

To store config and media on separate host directories (e.g. different NAS pools), set `TUBECAST_CONFIG_DIR` and
`TUBECAST_MEDIA_DIR` in `.env.docker` — see `.env.docker.example`.

**Upgrading from a single `tubecast-data` volume:** stop the container, then copy into your config mount (`/config`):
`database.sqlite`, `stored-commands/`, and rename `config/.env` → `.env`. Copy `downloads/` and `podcast/` into your
media mount (`/media`). Start with the updated compose file and remove the old `tubecast-data` volume once verified.

### Configuration

`docker-compose.yml` ships with sane defaults — no env file required. Optional overrides use `.env.docker` (see `.env.docker.example`); variables are prefixed with `TUBECAST_` so they do not conflict with a developer's lerd `.env` in the same clone.

| Variable                          | Default                            | Purpose                                                                        |
|-----------------------------------|------------------------------------|--------------------------------------------------------------------------------|
| `TUBECAST_PORT`                   | `8742`                             | Host port for the web UI                                                       |
| `TUBECAST_ADMIN_USERNAME`         | `admin`                            | Admin login username                                                           |
| `TUBECAST_ADMIN_PASSWORD`         | `changeme`                         | Admin login password                                                           |
| `TUBECAST_BASE_URI`               | `http://localhost:8742`            | Public URL for RSS enclosures and media links                                  |
| `TUBECAST_PUID` / `TUBECAST_PGID` | `33`                               | Container user/group — match your NAS volume owner (e.g. `568:568` on TrueNAS) |
| `TUBECAST_YOUTUBE_API_KEY`        | _(empty)_                          | Optional YouTube Data API key                                                  |
| `TUBECAST_CONFIG_DIR`             | _(named volume `tubecast-config`)_ | Host path for config (database, `.env`, stored commands)                       |
| `TUBECAST_MEDIA_DIR`              | _(named volume `tubecast-media`)_  | Host path for downloads and podcast files                                      |

Cookies and proxy can also be set in the **Settings** UI (stored in the database).

## Adding your first source

1. Open **Sources → Add source**
2. Paste a YouTube URL (channel, `@handle`, `/c/name`, playlist, or video)
3. Choose what to save:
   - **Video** — MP4 files in your downloads folder (good for archiving shows like [Critical Role](https://www.youtube.com/@CriticalRole))
   - **Audio** — M4A podcast files plus an RSS feed (good for music or talk channels like [Oculus Imperia](https://www.youtube.com/oculusimperia))
   - **Index only** — index episodes without downloading
4. TubeCast queues a full index automatically. Use **Activity** on the source page to watch progress.
5. For audio sources, copy the **RSS feed URL** into your podcast app.

### Optional: YouTube Data API

For faster, more reliable full indexing of large channels, add a [YouTube Data API key](https://console.cloud.google.com/) in **Settings**. Without it, TubeCast falls back to yt-dlp for full backfill.

## Podcast feeds

Each source gets a private RSS feed. The feed URL contains a random token in the path — treat it like a password. Podcast clients do not need your admin login.

- Feed URLs look like `/feeds/{token}/audio.xml`
- Media enclosures use `/media/{token}/{video-id}/audio.m4a`
- Audio enclosures are served efficiently by Caddy (with HTTP 206 Range support for scrubbing/seeking)

## Development

TubeCast supports two local workflows:

| Workflow | Best for | Config |
|----------|----------|--------|
| **[lerd](https://github.com/hipsterjazzbo/lerd)** | Day-to-day app development | `.env` from `.env.example` |
| **Docker Compose** | Testing the published image / container build | optional `.env.docker` |

A lerd `.env` and Docker can coexist in the same clone — Compose ignores lerd paths and only reads `TUBECAST_*` overrides from `.env.docker`.

### lerd (recommended)

Requires [lerd](https://github.com/hipsterjazzbo/lerd) (Podman-based PHP dev environment).

```bash
git clone https://github.com/hipsterjazzbo/tubecast.git && cd tubecast
cp .env.example .env
lerd env_setup
lerd setup
```

`lerd setup` runs migrations and `tubecast:init` (downloads yt-dlp if missing, creates data dirs, seeds defaults). To
diagnose tools: `php tempest tubecast:init --check`.

Open **https://tubecast.test** (lerd generates a local TLS cert). Start background workers from the lerd dashboard or:

```bash
lerd worker start command_bus --site tubecast
lerd worker start schedule --site tubecast
lerd worker start vite --site tubecast
```

Host paths (`database/`, `data/`) live in the project tree. Run tests with `composer test`.

### Docker (container-based dev)

Builds the image locally instead of pulling from GHCR:

```bash
make setup-docker   # optional: copies .env.docker.example → .env.docker
make dev            # dev mode: bind-mounts app code for live reload
# or: make up       # production-like local build
```

| Command | Code changes | When to use |
|---------|--------------|-------------|
| `make dev` | Bind-mounts `app/` and `tests/`; image includes Composer **dev** deps (Pest, PHPUnit) | Hacking on the Docker image |
| `make up` | Baked into image with production deps only — rebuild after code changes | Testing production builds |

```bash
make logs              # follow container logs
make shell             # shell into the container
make migrate           # run pending migrations
make reset && make dev # wipe named volumes and start fresh
make test              # run tests inside the dev container
make assets            # rebuild frontend assets on the host
```

E2E tests (`make test-e2e`) need outbound HTTPS to YouTube.

## Scripts

### Composer

| Script                   | Description                                    |
|--------------------------|------------------------------------------------|
| `composer test`          | Run all test suites (Pest)                     |
| `composer test:unit`     | Unit tests only                                |
| `composer test:feature`  | Feature tests only                             |
| `composer test:ui`       | UI tests only                                  |
| `composer test:e2e`      | E2E tests (requires network access to YouTube) |
| `composer lint:psr`      | Check PSR-12 compliance (phpcs)                |
| `composer fix:psr`       | Auto-fix PSR-12 issues (phpcbf)                |
| `composer lint:cs-fixer` | Dry-run php-cs-fixer                           |
| `composer fix:cs-fixer`  | Apply php-cs-fixer fixes                       |

### npm

| Script          | Description                          |
|-----------------|--------------------------------------|
| `npm run dev`   | Start Vite dev server                |
| `npm run build` | Build frontend assets for production |

## Tests

Tests use [Pest](https://pestphp.com) (PHPUnit under the hood). Configuration lives in `phpunit.xml`.

| Suite   | Directory        | Notes                                                  |
|---------|------------------|--------------------------------------------------------|
| Unit    | `tests/Unit/`    | Fast, isolated tests                                   |
| Feature | `tests/Feature/` | Application-level tests                                |
| Ui      | `tests/Ui/`      | UI / view tests                                        |
| E2e     | `tests/E2E/`     | End-to-end; set `TUBECAST_E2E=1`, needs YouTube access |

Tests run against an in-memory SQLite database with temp data paths. See `phpunit.xml` `<php>` block for test-specific
env overrides.

## Environment variables

The app reads these variables at runtime (set via `.env` for lerd, or baked into the container):

| Variable                    | Default                    | Purpose                                                  |
|-----------------------------|----------------------------|----------------------------------------------------------|
| `ENVIRONMENT`               | `local`                    | App environment (`local`, `production`, `testing`, etc.) |
| `BASE_URI`                  | `https://tubecast.test`    | Public base URL                                          |
| `ADMIN_USERNAME`            | `admin`                    | Admin login                                              |
| `ADMIN_PASSWORD`            | `changeme`                 | Admin password                                           |
| `DB_DATABASE`               | `database/database.sqlite` | SQLite database path                                     |
| `DATA_PATH`                 | `data`                     | Root data directory                                      |
| `DOWNLOADS_PATH`            | `data/downloads`           | Downloaded video files                                   |
| `PODCAST_PATH`              | `data/podcast`             | Podcast audio files                                      |
| `YT_DLP_BINARY`             | `yt-dlp`                   | Path to yt-dlp binary                                    |
| `YT_DLP_WORKER_CONCURRENCY` | `1`                        | Parallel download workers                                |
| `YT_DLP_SLEEP_INTERVAL`     | `5`                        | Seconds between yt-dlp requests                          |
| `YT_DLP_SLEEP_REQUESTS`     | `1`                        | Request count before sleeping                            |
| `YT_DLP_LIMIT_RATE`         | _(empty)_                  | Download rate limit (e.g. `5M`)                          |
| `YOUTUBE_API_KEY`           | _(empty)_                  | YouTube Data API key (also settable in UI)               |

## How downloads work

TubeCast runs background workers (command monitor + scheduler) inside the container. When episodes match your filters:

- **Auto mode** — downloads start automatically after indexing
- **Manual mode** — click **Download** on individual episodes or **Download all matching**

Video files land in `DOWNLOADS_PATH` (default `/media/downloads`). Podcast audio lands in `PODCAST_PATH/{source-id}/`.
Interrupted downloads are recovered on restart.

## Project structure

```
tubecast/
├── app/                    # Application source code
│   ├── Authentication/     # Auth logic
│   ├── Commands/           # Tempest console commands
│   ├── Config/             # Configuration classes
│   ├── Controllers/        # HTTP controllers
│   ├── Database/           # Migrations
│   ├── Enums/              # Enumerations
│   ├── Middleware/         # HTTP middleware
│   ├── Models/             # Domain models
│   ├── Repositories/       # Data access layer
│   ├── Requests/           # Form / API requests
│   ├── Services/           # Business logic services
│   ├── Tasks/              # Scheduled / background tasks
│   └── views/              # Tempest Blade-style views
├── tests/
│   ├── Unit/               # Unit tests
│   ├── Feature/            # Feature tests
│   ├── Ui/                 # UI tests
│   ├── E2E/                # End-to-end tests
│   └── Support/            # Test helpers
├── public/                 # Web root (entry point)
├── database/               # SQLite database (local dev)
├── data/                   # Downloads & podcast files (local dev)
├── docker/                 # Container config (Caddyfile, s6, entrypoint)
├── Dockerfile              # Multi-stage production image
├── docker-compose.yml      # Production Compose
├── docker-compose.build.yml # Local build overlay
├── docker-compose.dev.yml  # Dev overlay (bind-mounts, dev deps)
├── Makefile                # Developer shortcuts
├── composer.json           # PHP dependencies & scripts
├── package.json            # Node dependencies (Vite, Tailwind)
├── vite.config.ts          # Vite / Tailwind CSS build config
├── phpunit.xml             # Test configuration
├── phpcs.xml.dist          # PHP_CodeSniffer rules (PSR-12)
├── mago.toml               # Mago static analysis config
└── .php-cs-fixer.dist.php  # php-cs-fixer rules
```

## Stack

Built with [Tempest](https://tempestphp.com), [hazel/ytdlphp](https://packagist.org/packages/hazel/ytdlphp), and yt-dlp. The Docker image is based on [FrankenPHP](https://frankenphp.dev) with yt-dlp, ffmpeg, and deno copied from [fhfa/yt-dlp](https://hub.docker.com/r/fhfa/yt-dlp).

Frontend assets are built with [Vite](https://vite.dev), [Tailwind CSS](https://tailwindcss.com)
v4, [TypeScript](https://www.typescriptlang.org), and [htmx](https://htmx.org).

## License

MIT
