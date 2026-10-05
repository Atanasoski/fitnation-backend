<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Partner;
use App\Models\Role;
use App\Models\User;
use App\Services\PartnerExerciseFileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Locks how a partner admin writes their own Partner Override, links and
 * unlinks an exercise, before that write moves into a shared module (spec
 * 023, ticket 04). Written against the unchanged controller.
 */
class PartnerOverrideCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    private Partner $partner;

    private User $partnerAdmin;

    private Exercise $exercise;

    private PartnerExerciseFileService $files;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        $this->files = new PartnerExerciseFileService;
        $this->partner = Partner::factory()->create(['slug' => 'gym']);
        $this->exercise = Exercise::factory()->create(['name' => 'Bench Press']);
        $this->partnerAdmin = User::factory()->create(['partner_id' => $this->partner->id]);
        $this->partnerAdmin->roles()->attach(
            Role::firstOrCreate(['slug' => 'partner_admin'], ['name' => 'Partner Admin'])->id
        );
    }

    public function test_a_partner_admin_sets_description_image_and_video(): void
    {
        $this->partner->exercises()->attach($this->exercise->id);

        $this->actingAs($this->partnerAdmin)
            ->put(route('exercises.updatePartner', $this->exercise), [
                'description' => 'Our cues',
                'image' => UploadedFile::fake()->image('photo.jpg'),
                'video' => UploadedFile::fake()->create('clip.mp4', 100, 'video/mp4'),
            ])
            ->assertRedirect(route('partner.exercises.show', $this->exercise))
            ->assertSessionHas('success', 'Exercise customization updated successfully!');

        $imagePath = $this->files->getImagePath($this->partner, $this->exercise, 'jpg');
        $videoPath = $this->files->getVideoPath($this->partner, $this->exercise, 'mp4');
        $this->assertSame(
            ['description' => 'Our cues', 'image' => $imagePath, 'video' => $videoPath],
            $this->pivot()
        );
        Storage::assertExists([$imagePath, $videoPath]);
    }

    public function test_an_absent_description_keeps_the_stored_one_and_an_empty_one_clears_it(): void
    {
        $this->partner->exercises()->attach($this->exercise->id, [
            'description' => 'Kept', 'image' => 'gym/old.jpg', 'video' => null,
        ]);

        $this->actingAs($this->partnerAdmin)
            ->put(route('exercises.updatePartner', $this->exercise), [])
            ->assertRedirect(route('partner.exercises.show', $this->exercise));
        $this->assertSame(['description' => 'Kept', 'image' => 'gym/old.jpg', 'video' => null], $this->pivot());

        $this->actingAs($this->partnerAdmin)
            ->put(route('exercises.updatePartner', $this->exercise), ['description' => ''])
            ->assertRedirect(route('partner.exercises.show', $this->exercise));
        $this->assertSame(['description' => null, 'image' => 'gym/old.jpg', 'video' => null], $this->pivot());
    }

    public function test_remove_video_deletes_the_file_and_returns_to_the_edit_page(): void
    {
        $videoPath = $this->files->getVideoPath($this->partner, $this->exercise, 'mp4');
        Storage::put($videoPath, 'video');
        $this->partner->exercises()->attach($this->exercise->id, [
            'description' => 'Cues', 'image' => null, 'video' => $videoPath,
        ]);

        $this->actingAs($this->partnerAdmin)
            ->put(route('exercises.updatePartner', $this->exercise), ['remove_video' => '1'])
            ->assertRedirect(route('partner.exercises.edit', $this->exercise))
            ->assertSessionHas('success', 'Custom video removed.');

        $this->assertSame(['description' => 'Cues', 'image' => null, 'video' => null], $this->pivot());
        Storage::assertMissing($videoPath);
    }

    public function test_writing_an_override_for_an_unlinked_exercise_links_it(): void
    {
        $this->actingAs($this->partnerAdmin)
            ->put(route('exercises.updatePartner', $this->exercise), ['description' => 'New'])
            ->assertRedirect(route('partner.exercises.show', $this->exercise));

        $this->assertSame(['description' => 'New', 'image' => null, 'video' => null], $this->pivot());
    }

    public function test_only_a_partner_admin_writes_an_override(): void
    {
        $this->actingAs(User::factory()->create(['partner_id' => $this->partner->id]))
            ->put(route('exercises.updatePartner', $this->exercise), ['description' => 'Sneaky'])
            ->assertForbidden();

        $this->assertNull($this->pivot());
    }

    public function test_link_attaches_with_no_override(): void
    {
        $this->actingAs($this->partnerAdmin)
            ->from(route('partner.exercises.index'))
            ->post(route('exercises.link', $this->exercise))
            ->assertRedirect(route('partner.exercises.index'))
            ->assertSessionHas('success', 'Exercise linked successfully!');

        $this->assertSame(['description' => null, 'image' => null, 'video' => null], $this->pivot());
    }

    public function test_unlink_deletes_the_override_files_and_detaches(): void
    {
        $imagePath = $this->files->getImagePath($this->partner, $this->exercise, 'jpg');
        $videoPath = $this->files->getVideoPath($this->partner, $this->exercise, 'mp4');
        Storage::put($imagePath, 'image');
        Storage::put($videoPath, 'video');
        $this->partner->exercises()->attach($this->exercise->id, [
            'description' => 'Cues', 'image' => $imagePath, 'video' => $videoPath,
        ]);

        $this->actingAs($this->partnerAdmin)
            ->post(route('exercises.unlink', $this->exercise))
            ->assertRedirect(route('partner.exercises.index'))
            ->assertSessionHas('success', 'Exercise unlinked successfully!');

        $this->assertNull($this->pivot());
        Storage::assertMissing([$imagePath, $videoPath]);
    }

    /**
     * @return array{description: ?string, image: ?string, video: ?string}|null
     */
    private function pivot(): ?array
    {
        $row = $this->partner->exercises()->find($this->exercise->id)?->pivot;

        return $row === null ? null : [
            'description' => $row->description,
            'image' => $row->image,
            'video' => $row->video,
        ];
    }
}
