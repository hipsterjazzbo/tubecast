<?php

declare(strict_types=1);

use App\Models\Feed;
use App\Models\MediaItem;
use App\Models\Source;
use App\Services\Core\ModelId;
use Tests\Support\Fixtures;

describe('Source deletion', function (): void {
    it('removes the source, episodes, feed, and redirects to the index', function (): void {
        $source = Fixtures::source(['title' => 'Delete Me']);
        Fixtures::feed($source);
        Fixtures::mediaItem($source, ['ytId' => 'ep1', 'title' => 'Episode One']);
        Fixtures::mediaItem($source, ['ytId' => 'ep2', 'title' => 'Episode Two']);

        $sourceId = ModelId::int($source->id);

        $this->http->post('/sources/' . $sourceId . '/delete')
            ->assertRedirect('/sources');

        expect(Source::findById($source->id))->toBeNull()
            ->and(MediaItem::count()->where('sourceId = ?', $sourceId)->execute())->toBe(0)
            ->and(Feed::count()->where('sourceId = ?', $sourceId)->execute())->toBe(0);
    });

    it('removes episodes from the dashboard after delete', function (): void {
        $source = Fixtures::source(['title' => 'Vanishing Source']);
        Fixtures::mediaItem($source, ['title' => 'Soon Gone Episode']);

        $sourceId = ModelId::int($source->id);

        $this->http->post('/sources/' . $sourceId . '/delete')
            ->assertRedirect('/sources');

        $this->http->get('/')
            ->assertOk()
            ->assertNotSee('Soon Gone Episode')
            ->assertNotSee('Vanishing Source');
    });
});
