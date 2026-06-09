<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Commands\DownloadMediaCommand;
use App\Commands\FullIndexSourceCommand;
use App\Enums\DownloadMode;
use App\Enums\MediaItemStatus;
use App\Enums\SourceType;
use App\Models\Feed;
use App\Models\MediaItem;
use App\Models\MediaProfile;
use App\Models\Source;
use App\Services\PodcastFeedService;
use App\Services\DownloadProgressService;
use App\Services\EpisodeFilterService;
use App\Services\IndexingProgressService;
use App\Services\IndexingProgress;
use App\Services\SourceDeletionService;
use App\Services\SourceIndexingTriggers;
use App\Services\SourceMetadataService;
use App\Services\YouTubeChannelResolver;
use App\Services\YouTubeRssUrlBuilder;
use App\Support\MediaItemPresenter;
use App\Support\ModelId;
use App\Support\SourceContentTypes;
use App\Support\SourceEpisodeQuery;
use App\Support\SourceFilters;
use Tempest\CommandBus\CommandBus;
use Tempest\Http\Responses\Redirect;
use Tempest\Database\Direction;
use Tempest\Http\Request;
use Tempest\Router\Get;
use Tempest\Router\Post;
use Tempest\View\View;

use function Tempest\env;
use function Tempest\View\view;

final readonly class SourceController
{
    public function __construct(
        private YouTubeRssUrlBuilder $rssUrlBuilder,
        private YouTubeChannelResolver $channelResolver,
        private CommandBus $commandBus,
        private PodcastFeedService $feeds,
        private DownloadProgressService $downloadProgress,
        private EpisodeFilterService $episodeFilter,
        private IndexingProgressService $indexingProgress,
        private SourceMetadataService $metadata,
        private SourceDeletionService $deletion,
        private SourceIndexingTriggers $indexingTriggers,
    ) {
    }

    #[Get('/sources')]
    public function index(): View
    {
        $sources = Source::select()->orderBy('id', Direction::DESC)->all();
        $sourceRows = [];

        foreach ($sources as $source) {
            if ($source->title === null && $source->youtubeRssUrl !== null) {
                $this->metadata->ensureTitle($source, allowYtDlp: false);
            }

            $sourceId = ModelId::int($source->id);
            $feed = $this->feeds->forSource($sourceId);
            $saveLabels = array_values(array_filter([
                $source->saveVideo ? 'Video' : null,
                $source->saveAudio ? 'Audio' : null,
            ]));

            $sourceRows[] = (object) [
                'source' => $source,
                'stats' => $this->episodeStats($sourceId),
                'feed' => $feed,
                'saveLabel' => match (true) {
                    $saveLabels !== [] => 'Saves ' . implode(' + ', $saveLabels),
                    default => 'Index only',
                },
                'saveLabelMuted' => $saveLabels === [],
            ];
        }

        return view('views/sources/index.view.php', ...[
            'sourceRows' => $sourceRows,
        ]);
    }

    #[Get('/sources/create')]
    public function create(): View
    {
        return view('views/sources/create.view.php', ...[
            'profiles' => MediaProfile::select()->all(),
            'source' => $this->blankSource(),
            'sourceFilters' => new SourceFilters(),
        ]);
    }

    #[Get('/sources/{source}/edit')]
    public function edit(Source $source): View
    {
        return view('views/sources/edit.view.php', ...[
            'source' => $source,
            'profiles' => MediaProfile::select()->all(),
            'sourceFilters' => SourceFilters::fromSource($source),
        ]);
    }

    #[Get('/sources/{source}')]
    public function show(Source $source, Request $request): View
    {
        $sourceId = ModelId::int($source->id);
        $baseUri = rtrim(env('BASE_URI', 'http://localhost:8742'), '/');
        $feed = $this->feeds->forSource($sourceId);
        $episodeQuery = SourceEpisodeQuery::fromRequest($request);
        $episodeData = $this->episodeViewData($source, $episodeQuery);

        return view('views/sources/show.view.php', ...[
            'source' => $source,
            'feed' => $feed,
            'audioFeedUrl' => ($feed !== null && $source->saveAudio)
                ? $this->feeds->audioFeedUrl($feed, $baseUri)
                : null,
            'videoFeedUrl' => ($feed !== null && $source->saveVideo)
                ? $this->feeds->videoFeedUrl($feed, $baseUri)
                : null,
            'stats' => $episodeData['stats'],
            'contentFilterLabel' => $this->filterSummary($source),
            'episodeRows' => $episodeData['episodeRows'],
            'episodeQuery' => $episodeQuery,
            'showFilterBadges' => $episodeData['showFilterBadges'],
            'manualDownload' => $episodeData['manualDownload'],
            'canDownload' => $episodeData['canDownload'],
            'pendingManualCount' => $episodeData['pendingManualCount'],
            'indexingProgress' => $episodeData['indexingProgress'],
            'pollTrigger' => $episodeData['pollTrigger'],
            'sourceProgress' => $this->downloadProgress->forSource($sourceId, $episodeData['stats']),
            'showFilterBadges' => $episodeData['showFilterBadges'],
        ]);
    }

    #[Get('/sources/{source}/episodes/partial')]
    public function episodesPartial(Source $source, Request $request): View
    {
        $episodeQuery = SourceEpisodeQuery::fromRequest($request);

        return view('views/x-episodes.view.php', ...[
            'source' => $source,
            ...$this->episodeViewData($source, $episodeQuery),
        ]);
    }

    #[Get('/sources/{source}/stats/partial')]
    public function statsPartial(Source $source): View
    {
        $sourceId = ModelId::int($source->id);
        $stats = $this->episodeStats($sourceId);
        $indexingProgress = $this->indexingProgress->forSource(
            $source,
            $stats['total'],
            $stats['matched'],
            $stats['filtered'],
        );

        return view('views/x-source-stats.view.php', ...[
            'source' => $source,
            'stats' => $stats,
            'contentFilterLabel' => $this->filterSummary($source),
            'indexingProgress' => $indexingProgress,
            'pollTrigger' => $this->pollTrigger($stats, $sourceId, $indexingProgress->active),
            'sourceProgress' => $this->downloadProgress->forSource($sourceId, $stats),
            'showFilterBadges' => $this->episodeFilter->sourceHasEpisodeFilters($source),
        ]);
    }

    #[Post('/sources')]
    public function store(Request $request): Redirect
    {
        $url = (string) $request->get('url', '');
        $type = SourceType::tryFrom((string) $request->get('type', '')) ?? $this->rssUrlBuilder->detectType($url);

        $channelId = $this->channelResolver->resolve($url);
        $playlistId = $this->rssUrlBuilder->extractPlaylistId($url);

        $rssUrl = match ($type) {
            SourceType::Playlist => $playlistId !== null
                ? $this->rssUrlBuilder->forPlaylist($playlistId)
                : null,
            SourceType::Channel => $channelId !== null
                ? $this->rssUrlBuilder->forChannel($channelId)
                : null,
            SourceType::Video => null,
        };

        $profileId = $request->get('mediaProfileId');
        $profileId = is_string($profileId) && $profileId !== '' ? (int) $profileId : null;
        $filters = $this->filtersFromRequest($request);
        $filtersJson = $filters->toJson();
        $title = trim((string) $request->get('title', ''));

        $source = Source::create(
            url: $url,
            type: $type,
            title: $title !== '' ? $title : null,
            youtubeChannelId: $channelId,
            youtubeRssUrl: $rssUrl,
            mediaProfileId: $profileId,
            includeShorts: $request->hasBody('includeShorts'),
            includeLive: $request->hasBody('includeLive'),
            saveVideo: $request->hasBody('saveVideo'),
            saveAudio: $request->hasBody('saveAudio'),
            filtersJson: $filtersJson !== '' ? $filtersJson : null,
        );

        $sourceId = ModelId::int($source->id);

        Feed::create(
            sourceId: $sourceId,
            slug: 'source-' . $sourceId,
            title: $source->title ?? 'YouTube feed #' . $sourceId,
            token: bin2hex(random_bytes(16)),
            maxEpisodes: 100,
            enabled: true,
        );

        $this->metadata->ensureTitle($source, allowYtDlp: false);

        $this->commandBus->dispatch(new FullIndexSourceCommand($sourceId));

        return new Redirect('/sources/' . $sourceId);
    }

    #[Post('/sources/{source}/index')]
    public function indexNow(Source $source): Redirect
    {
        $sourceId = ModelId::int($source->id);

        $this->commandBus->dispatch(new FullIndexSourceCommand($sourceId));

        return new Redirect('/sources/' . $sourceId);
    }

    #[Post('/sources/{source}/settings')]
    public function updateSettings(Source $source, Request $request): Redirect
    {
        $sourceId = ModelId::int($source->id);

        $previousIncludeShorts = $source->includeShorts;
        $previousIncludeLive = $source->includeLive;
        $previousSaveVideo = $source->saveVideo;
        $previousSaveAudio = $source->saveAudio;
        $previousFilters = SourceFilters::fromSource($source);

        $title = trim((string) $request->get('title', ''));
        if ($title !== '') {
            $source->title = $title;
        }

        $source->includeShorts = $request->hasBody('includeShorts');
        $source->includeLive = $request->hasBody('includeLive');
        $source->saveVideo = $request->hasBody('saveVideo');
        $source->saveAudio = $request->hasBody('saveAudio');

        $filters = $this->filtersFromRequest($request);
        $encoded = $filters->toJson();
        $source->filtersJson = $encoded !== '' ? $encoded : null;

        $profileId = $request->get('mediaProfileId');
        $source->mediaProfileId = is_string($profileId) && $profileId !== '' ? (int) $profileId : null;
        $source->save();

        $newFilters = SourceFilters::fromSource($source);

        foreach ($this->indexingTriggers->commandsAfterSettingsChange(
            $source,
            $previousIncludeShorts,
            $previousIncludeLive,
            $previousSaveVideo,
            $previousSaveAudio,
            $previousFilters,
            $newFilters,
        ) as $command) {
            $this->commandBus->dispatch($command);
        }

        return new Redirect('/sources/' . $sourceId);
    }

    #[Post('/sources/{source}/episodes/{item}/retry')]
    public function retryEpisode(Source $source, MediaItem $item): Redirect
    {
        if (ModelId::int($item->sourceId) !== ModelId::int($source->id)) {
            return new Redirect('/sources/' . ModelId::int($source->id));
        }

        $item->status = MediaItemStatus::Discovered;
        $item->save();

        $this->commandBus->dispatch(new DownloadMediaCommand(ModelId::int($item->id)));

        return new Redirect('/sources/' . ModelId::int($source->id) . '#episodes');
    }

    #[Post('/sources/{source}/episodes/{item}/download')]
    public function downloadEpisode(Source $source, MediaItem $item): Redirect
    {
        if (ModelId::int($item->sourceId) !== ModelId::int($source->id)) {
            return new Redirect('/sources/' . ModelId::int($source->id));
        }

        if (! $source->saveVideo && ! $source->saveAudio) {
            return new Redirect('/sources/' . ModelId::int($source->id) . '#episodes');
        }

        if ($item->status === MediaItemStatus::Filtered) {
            return new Redirect('/sources/' . ModelId::int($source->id) . '#episodes');
        }

        $this->commandBus->dispatch(new DownloadMediaCommand(ModelId::int($item->id)));

        return new Redirect('/sources/' . ModelId::int($source->id) . '#episodes');
    }

    #[Post('/sources/{source}/episodes/download-all')]
    public function downloadAllMatching(Source $source): Redirect
    {
        $sourceId = ModelId::int($source->id);

        if (! $source->saveVideo && ! $source->saveAudio) {
            return new Redirect('/sources/' . $sourceId . '#episodes');
        }

        foreach ($this->episodesForSource($sourceId, new SourceEpisodeQuery()) as $item) {
            if ($item->status !== MediaItemStatus::Indexed && $item->status !== MediaItemStatus::Discovered) {
                continue;
            }

            $this->commandBus->dispatch(new DownloadMediaCommand(ModelId::int($item->id)));
        }

        return new Redirect('/sources/' . $sourceId . '#episodes');
    }

    #[Post('/sources/{source}/delete')]
    public function destroy(Source $source): Redirect
    {
        $this->deletion->delete($source);

        return new Redirect('/sources');
    }

    /** @return array{episodeRows: list<object>, stats: array{total: int, completed: int, downloading: int, failed: int, pending: int, filtered: int, matched: int, unknown: int}, pollTrigger: string, manualDownload: bool, canDownload: bool, pendingManualCount: int, indexingProgress: IndexingProgress, showFilterBadges: bool, episodeQuery: SourceEpisodeQuery} */
    private function episodeViewData(Source $source, SourceEpisodeQuery $episodeQuery): array
    {
        $sourceId = ModelId::int($source->id);
        $episodes = $this->episodesForSource($sourceId, $episodeQuery);
        $filters = SourceFilters::fromSource($source);
        $manualDownload = $filters->downloadMode === DownloadMode::Manual;
        $canDownload = $source->saveVideo || $source->saveAudio;
        $showFilterBadges = $this->episodeFilter->sourceHasEpisodeFilters($source);
        $episodeRows = [];
        $matchedCount = 0;
        $unknownCount = 0;

        foreach ($episodes as $episode) {
            $filterResult = $this->episodeFilter->evaluateItem($source, $episode);

            if ($filterResult->matches === true) {
                $matchedCount++;
            } elseif ($filterResult->matches === null) {
                $unknownCount++;
            }

            if (! $episodeQuery->showFiltered && $filterResult->matches === false) {
                continue;
            }

            $showDownload = $manualDownload
                && $canDownload
                && $filterResult->matches !== false
                && ($episode->status === MediaItemStatus::Indexed || $episode->status === MediaItemStatus::Discovered);

            $episodeRows[] = (object) [
                'episode' => $episode,
                'presentation' => MediaItemPresenter::for($episode, $this->downloadProgress->forItem($episode)),
                'filterResult' => $filterResult,
                'showDownloadButton' => $showDownload,
            ];
        }

        $stats = $this->episodeStats($sourceId, $matchedCount, $unknownCount);
        $indexingProgress = $this->indexingProgress->forSource(
            $source,
            $stats['total'],
            $stats['matched'],
            $stats['filtered'],
        );

        return [
            'episodeRows' => $episodeRows,
            'stats' => $stats,
            'pollTrigger' => $this->pollTrigger($stats, $sourceId, $indexingProgress->active),
            'manualDownload' => $manualDownload,
            'canDownload' => $canDownload,
            'pendingManualCount' => $stats['pending'],
            'indexingProgress' => $indexingProgress,
            'showFilterBadges' => $showFilterBadges,
            'episodeQuery' => $episodeQuery,
        ];
    }

    /** @param array{downloading: int, pending: int} $stats */
    private function pollTrigger(array $stats, int $sourceId, bool $indexingActive = false): string
    {
        return ($stats['downloading'] > 0 || $stats['pending'] > 0 || $indexingActive) ? 'every 3s' : 'none';
    }

    private function filterSummary(Source $source): string
    {
        $parts = [SourceContentTypes::label($source)];
        $filters = SourceFilters::fromSource($source);

        if ($filters->downloadMode === DownloadMode::Manual) {
            $parts[] = 'Manual downloads';
        }

        if ($filters->minDurationSeconds !== null) {
            $parts[] = '≥ ' . $filters->minDurationMinutes() . ' min';
        }

        if ($filters->maxDurationSeconds !== null) {
            $parts[] = '≤ ' . $filters->maxDurationMinutes() . ' min';
        }

        if ($filters->titleRegex !== null) {
            $parts[] = 'Title regex';
        }

        return implode(' · ', $parts);
    }

    private function blankSource(): Source
    {
        $source = new Source();
        $source->url = 'https://www.youtube.com/';
        $source->saveVideo = false;
        $source->saveAudio = false;

        return $source;
    }

    private function filtersFromRequest(Request $request): SourceFilters
    {
        return SourceFilters::fromForm([
            'downloadMode' => $request->get('downloadMode'),
            'minDurationMinutes' => $request->get('minDurationMinutes'),
            'maxDurationMinutes' => $request->get('maxDurationMinutes'),
            'titleRegex' => $request->get('titleRegex'),
        ]);
    }

    /** @return list<MediaItem> */
    private function episodesForSource(int $sourceId, SourceEpisodeQuery $query): array
    {
        $builder = MediaItem::select()->where('sourceId = ?', $sourceId);

        foreach ($query->orderBy() as [$column, $direction]) {
            $builder = $builder->orderBy($column, $direction);
        }

        return $builder->limit(200)->all();
    }

    /** @return array{total: int, completed: int, downloading: int, failed: int, pending: int, filtered: int, matched: int, unknown: int} */
    private function episodeStats(int $sourceId, int $matchedCount = 0, int $unknownCount = 0): array
    {
        $filtered = MediaItem::count()
            ->where('sourceId = ? AND status = ?', $sourceId, MediaItemStatus::Filtered->value)
            ->execute();

        return [
            'total' => MediaItem::count()->where('sourceId = ?', $sourceId)->execute(),
            'completed' => MediaItem::count()
                ->where('sourceId = ? AND status = ?', $sourceId, MediaItemStatus::Completed->value)
                ->execute(),
            'downloading' => MediaItem::count()
                ->where('sourceId = ? AND status = ?', $sourceId, MediaItemStatus::Downloading->value)
                ->execute(),
            'failed' => MediaItem::count()
                ->where(
                    'sourceId = ? AND status IN (?, ?)',
                    $sourceId,
                    MediaItemStatus::Failed->value,
                    MediaItemStatus::Throttled->value,
                )
                ->execute(),
            'filtered' => $filtered,
            'matched' => $matchedCount,
            'unknown' => $unknownCount,
            'pending' => MediaItem::count()->where(
                'sourceId = ? AND status IN (?, ?, ?)',
                $sourceId,
                MediaItemStatus::Discovered->value,
                MediaItemStatus::Pending->value,
                MediaItemStatus::Indexed->value,
            )->execute(),
        ];
    }
}
