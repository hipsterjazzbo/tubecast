<?php

declare(strict_types=1);

use App\Enums\MediaItemStatus;
use App\Support\ModelId;
use Tests\Support\Fixtures;

describe('Global navigation', function (): void {
    it('shows navigation on every page', function (): void {
        foreach (['/', '/sources', '/settings'] as $path) {
            $this->http->get($path)
                ->assertOk()
                ->assertSee('Dashboard')
                ->assertSee('Sources')
                ->assertSee('Settings');
        }
    });

    it('links the logo area to the dashboard', function (): void {
        $this->http->get('/sources')
            ->assertOk()
            ->assertSee('href="/"', false);
    });
});

describe('Dashboard UI', function (): void {
    it('displays stat cards and recent episode list', function (): void {
        $source = Fixtures::source(['title' => 'UI Test Source']);
        Fixtures::mediaItem($source, [
            'title' => 'Recent UI Episode',
            'status' => MediaItemStatus::Indexed,
        ]);

        $response = $this->http->get('/')->assertOk();

        $response->assertSee('Overview of your TubeCast library')
            ->assertSee('Recent episodes')
            ->assertSee('Recent UI Episode')
            ->assertSee('View sources');
    });
});

describe('Sources UI', function (): void {
    it('shows create source form', function (): void {
        $this->http->get('/sources/create')
            ->assertOk()
            ->assertSee('Add source')
            ->assertSee('What to save')
            ->assertSee('Download mode')
            ->assertSee('name="saveVideo"', false)
            ->assertSee('name="includeShorts"', false);
    });

    it('shows source cards with episode counts', function (): void {
        $source = Fixtures::source(['title' => 'Card Source']);
        Fixtures::mediaItem($source);
        Fixtures::mediaItem($source, ['ytId' => 'ep2', 'title' => 'Second']);

        $sourceId = ModelId::int($source->id);

        $this->http->get('/sources')
            ->assertOk()
            ->assertSee('Card Source')
            ->assertSee('/sources/' . $sourceId, false);
    });
});

describe('Source detail UI', function (): void {
    it('renders episode panel with edit link', function (): void {
        $source = Fixtures::source(['title' => 'Detail Source']);
        Fixtures::feed($source);
        Fixtures::mediaItem($source, ['title' => 'Listed Episode']);

        $sourceId = ModelId::int($source->id);

        $this->http->get('/sources/' . $sourceId)
            ->assertOk()
            ->assertSee('/sources/' . $sourceId . '/edit', false)
            ->assertSee('Episodes')
            ->assertSee('Listed Episode')
            ->assertSee('id="episodes-panel"', false)
            ->assertSee('hx-get="/sources/' . $sourceId . '/episodes/partial?sort=newest&amp;showFiltered=0"', false);
    });

    it('renders edit form with all settings', function (): void {
        $source = Fixtures::source(['title' => 'Editable Source']);
        $sourceId = ModelId::int($source->id);

        $this->http->get('/sources/' . $sourceId . '/edit')
            ->assertOk()
            ->assertSee('Edit source')
            ->assertSee('name="saveVideo"', false)
            ->assertSee('name="includeShorts"', false)
            ->assertSee('name="downloadMode"', false)
            ->assertSee('name="titleRegex"', false);
    });

    it('shows activity status and episode filters on the source page', function (): void {
        $source = Fixtures::source();
        Fixtures::mediaItem($source, ['status' => MediaItemStatus::Completed]);

        $sourceId = ModelId::int($source->id);

        $this->http->get('/sources/' . $sourceId)
            ->assertOk()
            ->assertSee('Filter:')
            ->assertSee('id="source-stats"', false)
            ->assertSee('id="episode-filters"', false)
            ->assertSee('Show excluded');
    });

    it('shows excluded badge for filtered episodes', function (): void {
        $source = Fixtures::source();
        Fixtures::mediaItem($source, [
            'ytId' => 'short1',
            'title' => 'Short clip',
            'durationSeconds' => 30,
            'status' => MediaItemStatus::Filtered,
            'metadataJson' => json_encode([
                'id' => 'short1',
                'title' => 'Short clip',
                'duration' => 30,
                'webpage_url' => 'https://www.youtube.com/shorts/short1',
                'live_status' => 'not_live',
            ], JSON_THROW_ON_ERROR),
        ]);

        $sourceId = ModelId::int($source->id);

        $this->http->get('/sources/' . $sourceId . '/episodes/partial?showFiltered=1')
            ->assertOk()
            ->assertSee('Excluded:');
    });

    it('shows index-only badge when neither format is saved', function (): void {
        Fixtures::source([
            'title' => 'Index Only UI',
            'saveVideo' => false,
            'saveAudio' => false,
        ]);

        $this->http->get('/sources')
            ->assertOk()
            ->assertSee('Index only');
    });
});

describe('Settings UI', function (): void {
    it('renders yt-dlp and youtube api settings forms', function (): void {
        $this->http->get('/settings')
            ->assertOk()
            ->assertSee('yt-dlp overrides')
            ->assertSee('YouTube Data API')
            ->assertSee('action="/settings/yt-dlp"', false)
            ->assertSee('action="/settings/youtube-api"', false);
    });
});
