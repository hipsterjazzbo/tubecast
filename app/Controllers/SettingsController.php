<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Download\DownloadCleanupService;
use App\Repositories\SettingsRepository;
use App\Requests\UpdateYouTubeApiSettingsRequest;
use App\Requests\UpdateYtDlpSettingsRequest;
use App\Services\Core\DevTools;
use Tempest\Http\Request;
use Tempest\Http\Responses\NotFound;
use Tempest\Http\Responses\Redirect;
use App\Middleware\RequireAuthMiddleware;
use Tempest\Router\Get;
use Tempest\Router\Post;
use Tempest\Router\WithMiddleware;
use Tempest\View\View;

use function Tempest\env;
use function Tempest\View\view;

#[WithMiddleware(RequireAuthMiddleware::class)]
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
    public function updateYtDlp(UpdateYtDlpSettingsRequest $request): Redirect
    {
        $this->settings->set('ytDlpCookiesFile', $request->ytDlpCookiesFile);
        $this->settings->set('ytDlpProxy', $request->ytDlpProxy);

        return new Redirect('/settings');
    }

    #[Post('/settings/youtube-api')]
    public function updateYouTubeApi(UpdateYouTubeApiSettingsRequest $request): Redirect
    {
        $this->settings->set('youtubeApiKey', trim($request->youtubeApiKey));

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
