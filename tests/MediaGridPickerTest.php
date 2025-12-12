<?php

namespace Backstage\UploadcareField\Tests;

use Backstage\UploadcareField\Forms\Components\MediaGridPicker;
use Backstage\UploadcareField\Livewire\MediaGridPicker as LivewireMediaGridPicker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Orchestra\Testbench\TestCase;

class MediaGridPickerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up the media model configuration
        config(['backstage.media.model' => 'Backstage\\Models\\Media']);
    }

    public function test_form_component_can_initialize_with_default_values(): void
    {
        $picker = new MediaGridPicker('test_field');

        $this->assertEquals(12, $picker->getPerPage());
    }

    public function test_form_component_can_change_per_page(): void
    {
        $picker = new MediaGridPicker('test_field');

        $picker->perPage(24);

        $this->assertEquals(24, $picker->getPerPage());
    }

    public function test_livewire_component_can_initialize(): void
    {
        $component = Livewire::test(LivewireMediaGridPicker::class, [
            'fieldName' => 'test_field',
            'perPage' => 12,
        ]);

        $component->assertSet('fieldName', 'test_field')
            ->assertSet('perPage', 12);
    }

    public function test_livewire_component_can_update_per_page(): void
    {
        $component = Livewire::test(LivewireMediaGridPicker::class, [
            'fieldName' => 'test_field',
            'perPage' => 12,
        ]);

        $component->call('updatePerPage', 24)
            ->assertSet('perPage', 24);
    }

    public function test_livewire_component_dispatches_media_selected_event(): void
    {
        $component = Livewire::test(LivewireMediaGridPicker::class, [
            'fieldName' => 'test_field',
            'perPage' => 12,
        ]);

        $media = [
            'id' => 'test-id',
            'filename' => 'test.jpg',
            'cdn_url' => 'https://ucarecdn.com/test-uuid/',
        ];

        $component->call('selectMedia', $media)
            ->assertDispatched('media-selected', [
                'fieldName' => 'test_field',
                'media' => $media,
            ]);
    }
}
