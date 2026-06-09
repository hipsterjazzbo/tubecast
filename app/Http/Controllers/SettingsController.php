<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\DownloadCleanupService;
use App\Services\SettingsRepository;
use App\Support\DevTools;
use Tempest\Http\Request;
use Tempest\Http\Responses\NotFound;
use Tempest\Http\Responses\Redirect;
use Tempest\Router\Get;
use Tempest\Router\Post;
use Tempest\View\View;

use function Tempest\env;
use function Tempest\View\view;

final readonly class SettingsController
{
    public function __construct(
        private SettingsRepository $settings,
        private DownloadCleanupService $downloadCleanup,
    ) {
    }

    #[Get('/settings')]
    public function index(Request $request): View
    {
        return view('views/settings/index.view.php', ...[
            'ytDlpBinary' => env('YT_DLP_BINARY', 'yt-dlp'),
            'workerConcurrency' => env('YT_DLP_WORKER_CONCURRENCY', '1'),
            'sleepInterval' => env('YT_DLP_SLEEP_INTERVAL', '5'),
            'sleepRequests' => env('YT_DLP_SLEEP_REQUESTS', '1'),
            'limitRate' => env('YT_DLP_LIMIT_RATE', ''),
            'cookiesFile' => $this->settings->get('ytDlpCookiesFile', ''),
            'proxy' => $this->settings->get('ytDlpProxy', ''),
            'youtubeApiKey' => $this->settings->get('youtubeApiKey', ''),
            'youtubeApiConfigured' => ($this->settings->get('youtubeApiKey') !== null
                && $this->settings->get('youtubeApiKey') !== '')
                || (is_string(env('YOUTUBE_API_KEY')) && env('YOUTUBE_API_KEY') !== ''),
            'devToolsEnabled' => DevTools::enabled(),
            'nukeResult' => $this->nukeResultFromRequest($request),
        ]);
    }

    #[Post('/settings/yt-dlp')]
    public function updateYtDlp(Request $request): Redirect
    {
        $this->settings->set('ytDlpCookiesFile', (string) $request->get('ytDlpCookiesFile', ''));
        $this->settings->set('ytDlpProxy', (string) $request->get('ytDlpProxy', ''));

        return new Redirect('/settings');
    }

    #[Post('/settings/youtube-api')]
    public function updateYouTubeApi(Request $request): Redirect
    {
        $this->settings->set('youtubeApiKey', trim((string) $request->get('youtubeApiKey', '')));

        return new Redirect('/settings');
    }

    #[Post('/settings/dev/nuke-downloads')]
    public function nukeDownloads(): Redirect|NotFound
    {
        if (! DevTools::enabled()) {
            return new NotFound();
        }

        $result = $this->downloadCleanup->nukeAll();

        return new Redirect('/settings?nuke=1&files=' . $result['deletedFiles'] . '&items=' . $result['resetItems']);
    }

    /** @return array{files: int, items: int}|null */
    private function nukeResultFromRequest(Request $request): ?array
    {
        if ($request->get('nuke') !== '1') {
            return null;
        }

        $files = $request->get('files');
        $items = $request->get('items');

        return [
            'files' => is_string($files) && $files !== '' ? (int) $files : 0,
            'items' => is_string($items) && $items !== '' ? (int) $items : 0,
        ];
    }
}
