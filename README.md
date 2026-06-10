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

The image is published to [GitHub Container Registry](https://github.com/hipsterjazzbo/tubecast/pkgs/container/tubecast) on each push to `main` (tagged `latest`).

Open **http://localhost:8742** and sign in with the default credentials:

- **Username:** `admin`
- **Password:** `changeme`

Change the password before exposing TubeCast to a network — set `ADMIN_PASSWORD` in a `.env` file next to `docker-compose.yml` (Compose reads it automatically) and run `docker compose up -d` again.

On first start the container creates a data volume, runs database migrations, and seeds default download profiles. SQLite, downloaded media, and podcast files all live in the `tubecast-data` Docker volume.

### Configuration

Optional settings can go in a `.env` file (see `.env.example`). Common overrides:

| Variable | Default | Purpose |
|----------|---------|---------|
| `TUBECAST_PORT` | `8742` | Host port for the web UI |
| `ADMIN_USERNAME` | `admin` | Admin login username |
| `ADMIN_PASSWORD` | `changeme` | Admin login password |
| `BASE_URI` | `http://localhost:8742` | Public URL for RSS enclosures and media links |
| `PUID` / `PGID` | `33` | Container user/group — match your NAS volume owner (e.g. `568:568` on TrueNAS) |
| `YOUTUBE_API_KEY` | _(empty)_ | Optional YouTube Data API key |

Cookies and proxy can also be set in the **Settings** UI (stored in the database).

> **Arch Linux:** `/usr/bin/docker` is often the Podman compatibility shim. Use **`podman compose up -d`** instead.

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

For working on TubeCast itself — builds the image locally instead of pulling from GHCR:

```bash
git clone https://github.com/hipsterjazzbo/tubecast.git && cd tubecast
make setup          # copies .env.example → .env and prompts for admin password
make dev            # dev mode: bind-mounts app code for live reload
# or: make up       # production-like local build
```

| Command | Code changes | When to use |
|---------|--------------|-------------|
| `make dev` | Bind-mounts `app/` and `tests/`; image includes Composer **dev** deps (Pest, PHPUnit) | Day-to-day development |
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
