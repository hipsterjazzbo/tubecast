<?php

declare(strict_types=1);

use App\Services\MediaServer\MediaServerPathMapper;
use App\Services\MediaServer\TitleSanitizer;

describe('TitleSanitizer', function (): void {
    it('removes filesystem unsafe characters', function (): void {
        $sanitizer = new TitleSanitizer();

        expect($sanitizer->sanitize('Critical Role: Campaign 3 / Ep 1'))->toBe('Critical Role Campaign 3 Ep 1');
    });

    it('returns Untitled for blank input', function (): void {
        $sanitizer = new TitleSanitizer();

        expect($sanitizer->sanitize('   '))->toBe('Untitled');
    });
});

describe('MediaServerPathMapper', function (): void {
    it('maps tubecast paths under configured roots to remote paths', function (): void {
        $mapper = new MediaServerPathMapper();
        $server = new App\Models\MediaServer();
        $server->tubecastVideoRoot = '/media/video';
        $server->tubecastAudioRoot = '/media/audio';

        $library = new App\Models\MediaServerLibrary();
        $library->remoteRoot = '/mnt/nas/youtube';

        $source = Tests\Support\Fixtures::unsavedSource();

        $remote = $mapper->mapForSource(
            $source,
            $server,
            $library,
            '/media/video/Show/Season 01/episode.mp4',
        );

        expect($remote)->toBe('/mnt/nas/youtube/Show/Season 01/episode.mp4');
    });

    it('maps audio paths using the audio root', function (): void {
        $mapper = new MediaServerPathMapper();
        $server = new App\Models\MediaServer();
        $server->tubecastVideoRoot = '/media/video';
        $server->tubecastAudioRoot = '/media/audio';

        $library = new App\Models\MediaServerLibrary();
        $library->remoteRoot = '/mnt/nas/podcasts';

        $remote = $mapper->mapAudioPath(
            $server,
            '/media/audio/Channel/episode.mp3',
            $library,
        );

        expect($remote)->toBe('/mnt/nas/podcasts/Channel/episode.mp3');
    });

    it('returns null when the tubecast path is outside the configured root', function (): void {
        $mapper = new MediaServerPathMapper();
        $server = new App\Models\MediaServer();
        $server->tubecastVideoRoot = '/media/video';

        $library = new App\Models\MediaServerLibrary();
        $library->remoteRoot = '/mnt/nas/youtube';

        $source = Tests\Support\Fixtures::unsavedSource();

        expect($mapper->mapForSource($source, $server, $library, '/other/episode.mp4'))->toBeNull();
    });
});
