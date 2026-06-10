<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\VideoInfoParser;
use App\Models\MediaProfile;
use App\Models\Source;
use App\Support\ModelId;
use App\TubecastConfig;
use Symfony\Component\Process\Process;
use Ytdlphp\Exception\YtDlpException;
use Ytdlphp\Metadata\VideoInfo;
use Ytdlphp\Options;
use Ytdlphp\Option\SponsorBlockCategory;
use Ytdlphp\YtDlp;

final class YtDlpService
{
    private YtDlp $client;

    public function __construct(
        private TubecastConfig $config,
        private OutputPathBuilder $paths,
        private SettingsRepository $settings,
    ) {
        $this->client = new YtDlp($this->config->ytDlpBinary)
            ->withWorkingDirectory($this->config->downloadsPath)
            ->withDefaultOptions($this->defaultOptions());
    }

    public function defaultOptions(): Options
    {
        $options = Options::create()
            ->noWarnings()
            ->embedMetadata()
            ->writeInfoJson();

        if ($this->config->sleepInterval > 0) {
            $options = $options->option('--sleep-interval', (string) $this->config->sleepInterval);
        }

        if ($this->config->sleepRequests > 0) {
            $options = $options->option('--sleep-requests', (string) $this->config->sleepRequests);
        }

        if ($this->config->limitRate !== null && $this->config->limitRate !== '') {
            $options = $options->option('--limit-rate', $this->config->limitRate);
        }

        $cookies = $this->settings->get('ytDlpCookiesFile');

        if ($cookies !== null && $cookies !== '' && is_file($cookies)) {
            $options = $options->cookies($cookies);
        }

        $proxy = $this->settings->get('ytDlpProxy');

        if ($proxy !== null && $proxy !== '') {
            $options = $options->proxy($proxy);
        }

        return $options;
    }

    public function forSource(Source $source, ?MediaProfile $profile = null): YtDlp
    {
        return $this->client->withDefaultOptions(
            $this->videoDownloadOptions($source, $profile),
        );
    }

    public function forAudioDownload(Source $source, ?MediaProfile $profile = null): YtDlp
    {
        return $this->client->withDefaultOptions(
            $this->audioDownloadOptions($source, $profile),
        );
    }

    private function videoDownloadOptions(Source $source, ?MediaProfile $profile): Options
    {
        $options = $this->defaultOptions();
        $template = $source->outputTemplate ?? $this->paths->defaultTemplate();
        $options = $options->output($template);

        if ($profile !== null) {
            $options = $options->format($profile->formatSelector);

            if ($profile->mergeFormat !== null) {
                $options = $options->mergeOutputFormat($profile->mergeFormat);
            }

            if ($profile->sponsorblockRemove) {
                $options = $this->withSponsorBlock($options);
            }
        }

        return $options;
    }

    private function audioDownloadOptions(Source $source, ?MediaProfile $profile): Options
    {
        $options = $this->defaultOptions()
            ->output($this->paths->podcastOutputTemplate(ModelId::int($source->id)))
            ->extractAudio()
            ->format($profile?->formatSelector ?? 'bestaudio/best')
            ->audioFormat($profile?->mergeFormat ?? 'm4a');

        if ($profile === null || $profile->sponsorblockRemove) {
            $options = $this->withSponsorBlock($options);
        }

        return $options;
    }

    private function withSponsorBlock(Options $options): Options
    {
        return $options->sponsorblockRemove(
            SponsorBlockCategory::Sponsor,
            SponsorBlockCategory::SelfPromo,
            SponsorBlockCategory::Intro,
            SponsorBlockCategory::Outro,
        );
    }

    public function extractInfo(string $url): VideoInfo
    {
        $info = null;

        $this->eachVideo($url, function (VideoInfo $video) use (&$info): void {
            $info ??= $video;
        }, stopAfterFirst: true);

        if ($info === null) {
            throw new YtDlpException('yt-dlp returned no metadata.');
        }

        return $info;
    }

    /** @return list<VideoInfo> */
    public function extractAll(string $url): array
    {
        $videos = [];

        $this->eachVideo($url, function (VideoInfo $video) use (&$videos): void {
            $videos[] = $video;
        });

        return $videos;
    }

    /**
     * Streams yt-dlp `-j` output line-by-line so callers can persist episodes while the
     * catalog scan is still running (full channel indexes can take several minutes).
     *
     * @param callable(VideoInfo): void $onVideo
     */
    public function eachVideo(string $url, callable $onVideo, bool $stopAfterFirst = false): void
    {
        $options = $this->defaultOptions()
            ->dumpJson()
            ->noWarnings()
            ->quiet();

        if ($stopAfterFirst) {
            $options = $options->playlistEnd(1);
        }

        $process = new Process(
            command: [$this->config->ytDlpBinary, ...$options->toArray(), $url],
            cwd: $this->config->downloadsPath,
            timeout: null,
        );

        $buffer = '';
        $done = false;

        $process->run(function (string $type, string $data) use (&$buffer, $onVideo, $stopAfterFirst, $process, &$done): void {
            if ($done || $type !== Process::OUT) {
                return;
            }

            $buffer .= $data;

            while (($newline = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $newline);
                $buffer = substr($buffer, $newline + 1);

                if (trim($line) === '') {
                    continue;
                }

                $onVideo(VideoInfoParser::fromJsonLine($line));

                if ($stopAfterFirst) {
                    $done = true;
                    $process->stop(10);

                    return;
                }
            }
        });

        if (! $done && trim($buffer) !== '') {
            $onVideo(VideoInfoParser::fromJsonLine(trim($buffer)));
        }

        if (! $process->isSuccessful() && ! $done) {
            $error = trim($process->getErrorOutput());

            throw new YtDlpException($error !== '' ? $error : 'yt-dlp catalog scan failed.');
        }
    }

    public function extractPlaylist(string $url): VideoInfo
    {
        return $this->client->extractPlaylist($url);
    }

    public function download(string $url, ?Options $options = null): void
    {
        $this->client->download($url, $options);
    }

    public function resolveChannelId(string $url): ?string
    {
        try {
            $info = $this->extractInfo($url);

            return $info->raw['channel_id'] ?? $info->raw['uploader_id'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }
}
