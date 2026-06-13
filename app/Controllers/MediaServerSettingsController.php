<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\MediaServer;
use App\Repositories\SettingsRepository;
use App\Requests\StoreMediaServerRequest;
use App\Requests\UpdateMediaServerRequest;
use App\Requests\UpdateMetadataProviderSettingsRequest;
use App\Services\MediaServer\MediaServerClientFactory;
use App\Services\MediaServer\MediaServerException;
use App\Services\MediaServer\MediaServerSyncService;
use App\Middleware\RequireAuthMiddleware;
use Tempest\Http\Responses\Redirect;
use Tempest\Router\Post;
use Tempest\Router\WithMiddleware;

#[WithMiddleware(RequireAuthMiddleware::class)]
final readonly class MediaServerSettingsController
{
    public function __construct(
        private MediaServerClientFactory $clients,
        private MediaServerSyncService $sync,
        private SettingsRepository $settings,
    ) {
    }

    #[Post('/settings/media-servers')]
    public function store(StoreMediaServerRequest $request): Redirect
    {
        MediaServer::create(
            name: trim($request->name),
            type: $request->type,
            baseUrl: rtrim(trim($request->baseUrl), '/'),
            apiToken: trim($request->apiToken),
            tubecastVideoRoot: rtrim(trim($request->tubecastVideoRoot), '/'),
            tubecastAudioRoot: rtrim(trim($request->tubecastAudioRoot), '/'),
            enabled: $request->enabled,
        );

        return new Redirect('/settings#media-servers');
    }

    #[Post('/settings/media-servers/{server}')]
    public function update(MediaServer $server, UpdateMediaServerRequest $request): Redirect
    {
        $server->name = trim($request->name);
        $server->type = $request->type;
        $server->baseUrl = rtrim(trim($request->baseUrl), '/');
        $server->apiToken = trim($request->apiToken);
        $server->tubecastVideoRoot = rtrim(trim($request->tubecastVideoRoot), '/');
        $server->tubecastAudioRoot = rtrim(trim($request->tubecastAudioRoot), '/');
        $server->enabled = $request->enabled;
        $server->save();

        return new Redirect('/settings#media-servers');
    }

    #[Post('/settings/media-servers/{server}/delete')]
    public function delete(MediaServer $server): Redirect
    {
        $server->delete();

        return new Redirect('/settings#media-servers');
    }

    #[Post('/settings/media-servers/{server}/test')]
    public function test(MediaServer $server): Redirect
    {
        try {
            $this->clients->for($server)->testConnection($server);
            $server->lastSyncError = null;
        } catch (MediaServerException $exception) {
            $server->lastSyncError = $exception->getMessage();
        }

        $server->save();

        return new Redirect('/settings#media-servers');
    }

    #[Post('/settings/media-servers/{server}/sync')]
    public function syncLibraries(MediaServer $server): Redirect
    {
        try {
            $this->sync->sync($server);
        } catch (MediaServerException $exception) {
            $server->lastSyncError = $exception->getMessage();
            $server->save();
        }

        return new Redirect('/settings#media-servers');
    }

    #[Post('/settings/metadata-providers')]
    public function updateMetadataProviders(UpdateMetadataProviderSettingsRequest $request): Redirect
    {
        $this->settings->set('tmdbApiKey', is_string($request->tmdbApiKey) ? trim($request->tmdbApiKey) : '');
        $this->settings->set('tvdbApiKey', is_string($request->tvdbApiKey) ? trim($request->tvdbApiKey) : '');

        return new Redirect('/settings#metadata-providers');
    }
}
