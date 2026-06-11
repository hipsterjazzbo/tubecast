# TubeCast

Self-hosted YouTube archiver with optional podcast feeds. Subscribe to channels, playlists, or individual videos; TubeCast indexes new uploads and downloads them in the background. Completed audio can be exposed as a private podcast RSS feed for your phone or media player.

## What it does

- **Subscribe** to YouTube channels, playlists, or single videos
- **Index** new episodes quickly via YouTube RSS, with full backfill via the YouTube Data API (when configured) or yt-dlp
- **Download** video files, audio-only podcast files, or both — per source
- **Filter** episodes by duration, title pattern, shorts, and live streams
- **Publish** token-protected podcast RSS feeds with HTTP Range support for seeking

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

On first start the container creates a data volume, runs database migrations, and seeds default download profiles. SQLite, downloaded media, and podcast files all live in the `tubecast-data` Docker volume.

### Configuration

`docker-compose.yml` ships with sane defaults — no env file required. Optional overrides use `.env.docker` (see `.env.docker.example`); variables are prefixed with `TUBECAST_` so they do not conflict with a developer's lerd `.env` in the same clone.

| Variable | Default | Purpose |
|----------|---------|---------|
| `TUBECAST_PORT` | `8742` | Host port for the web UI |
| `TUBECAST_ADMIN_USERNAME` | `admin` | Admin login username |
| `TUBECAST_ADMIN_PASSWORD` | `changeme` | Admin login password |
| `TUBECAST_BASE_URI` | `http://localhost:8742` | Public URL for RSS enclosures and media links |
| `TUBECAST_PUID` / `TUBECAST_PGID` | `33` | Container user/group — match your NAS volume owner (e.g. `568:568` on TrueNAS) |
| `TUBECAST_YOUTUBE_API_KEY` | _(empty)_ | Optional YouTube Data API key |

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
make reset && make dev # wipe data volume and start fresh
make test              # run tests inside the dev container
make assets            # rebuild frontend assets on the host
```

E2E tests (`make test-e2e`) need outbound HTTPS to YouTube.

## How downloads work

TubeCast runs background workers (command monitor + scheduler) inside the container. When episodes match your filters:

- **Auto mode** — downloads start automatically after indexing
- **Manual mode** — click **Download** on individual episodes or **Download all matching**

Video files land in `DOWNLOADS_PATH` (default `/data/downloads`). Podcast audio lands in `PODCAST_PATH/{source-id}/`. Interrupted downloads are recovered on restart.

## Stack

Built with [Tempest](https://tempestphp.com), [hazel/ytdlphp](https://packagist.org/packages/hazel/ytdlphp), and yt-dlp. The Docker image is based on [FrankenPHP](https://frankenphp.dev) with yt-dlp, ffmpeg, and deno copied from [fhfa/yt-dlp](https://hub.docker.com/r/fhfa/yt-dlp).

## License

MIT
