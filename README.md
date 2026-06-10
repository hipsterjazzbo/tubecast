# TubeCast

Self-hosted YouTube archiver with optional podcast feeds. Subscribe to channels, playlists, or individual videos; TubeCast indexes new uploads and downloads them in the background. Completed audio can be exposed as a private podcast RSS feed for your phone or media player.

## What it does

- **Subscribe** to YouTube channels, playlists, or single videos
- **Index** new episodes quickly via YouTube RSS, with full backfill via the YouTube Data API (when configured) or yt-dlp
- **Download** video files, audio-only podcast files, or both — per source
- **Filter** episodes by duration, title pattern, shorts, and live streams
- **Publish** token-protected podcast RSS feeds with HTTP Range support for seeking

## Quick start

```bash
git clone https://github.com/hipsterjazzbo/tubecast.git && cd tubecast
make setup          # copies .env.example → .env
# Edit .env: set ADMIN_USERNAME and ADMIN_PASSWORD (required)
make dev            # dev mode: live code reload via bind mounts
# or: make up       # production-like: code baked into the image
```

Open **http://localhost:8742** and sign in (change `TUBECAST_PORT` in `.env` if needed). The container will not start without `ADMIN_PASSWORD` set.

On first start the container syncs your `.env` into the data volume, runs database migrations, and seeds default download profiles. SQLite, downloaded media, and podcast files all live in the `tubecast-data` Docker volume.

> **Arch Linux:** `/usr/bin/docker` is often the Podman compatibility shim and does not support `docker compose`. Use **`podman compose`** or **`make dev`** instead. With real Docker: `make dev COMPOSE="docker compose"`.

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
- Audio enclosures are served efficiently by nginx (with HTTP 206 Range support for scrubbing/seeking)

## Configuration

All settings live in **`.env`** at the project root (see `.env.example`). Docker Compose and the app both read this file; the container entrypoint syncs Tempest-relevant values to `/data/config/.env` on start.

| Variable | Purpose |
|----------|---------|
| `TUBECAST_PORT` | Host port for the web UI |
| `ADMIN_USERNAME` | Admin login username (required) |
| `ADMIN_PASSWORD` | Admin login password (required) |
| `PUID` / `PGID` | Container user/group — match your NAS volume owner (e.g. `568:568` on TrueNAS) |
| `BASE_URI` | Public URL for RSS enclosures and media links |
| `YOUTUBE_API_KEY` | Optional YouTube Data API key |
| `YT_DLP_*` | yt-dlp throttling (sleep intervals, rate limits) |

Cookies and proxy can also be set in the **Settings** UI (stored in the database).

## Dev vs production-like

| Command | Code changes | When to use |
|---------|--------------|-------------|
| `make dev` | Bind-mounts `app/`, `public/`, and `tests/`; image includes Composer **dev** deps (Pest, PHPUnit) | Day-to-day development |
| `make up` | Baked into image with production deps only — rebuild after code changes | Testing production builds |

Both modes use the same `.env`. Restart the container after changing environment variables.

Set `PUID` and `PGID` to match the owner of your data volume. On first start, init re-maps the container `www-data` user to those IDs and `chown`s `/data`.

## Useful commands

```bash
make logs              # follow container logs
make shell             # shell into the container
make migrate           # run pending migrations
make reset && make dev # wipe data volume and start fresh
composer test          # run tests on the host (requires local composer install)
make test              # run tests inside the dev container
make test-e2e          # live network tests inside the dev container (needs outbound HTTPS to YouTube)
```

E2E tests skip quickly when `TUBECAST_E2E` is unset, or when the container cannot reach YouTube. If `make test-e2e` used to hang, recreate the dev stack (`make dev`) so DNS is configured, or run `composer test:e2e` on the host instead.

## How downloads work

TubeCast runs background workers (command monitor + scheduler) inside the container. When episodes match your filters:

- **Auto mode** — downloads start automatically after indexing
- **Manual mode** — click **Download** on individual episodes or **Download all matching**

Video files land in `DOWNLOADS_PATH` (default `/data/downloads`). Podcast audio lands in `PODCAST_PATH/{source-id}/`. Interrupted downloads are recovered on restart.

## Stack

Built with [Tempest](https://tempestphp.com), [hazel/ytdlphp](https://packagist.org/packages/hazel/ytdlphp), and yt-dlp. The Docker image is based on [serversideup/php:8.5-fpm-nginx](https://hub.docker.com/r/serversideup/php) with yt-dlp, ffmpeg, and deno copied from [fhfa/yt-dlp](https://hub.docker.com/r/fhfa/yt-dlp).

## License

MIT
