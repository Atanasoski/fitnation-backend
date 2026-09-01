<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Partner;
use App\Services\Exercise\PartnerExerciseView;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Guards issue 014: a partner's view of an exercise is resolved in one place,
 * and resolving it does not depend on the caller having remembered to
 * eager-load the partners relation.
 */
class PartnerExerciseViewTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Model::preventLazyLoading(false);

        parent::tearDown();
    }

    public function test_partner_overrides_win_over_the_exercise_defaults(): void
    {
        [$exercise, $partner] = $this->exerciseWithOverrides([
            'description' => 'Partner description',
            'image' => 'partner/image.jpg',
            'video' => 'partner/video.mp4',
        ]);

        $view = PartnerExerciseView::of($exercise, $partner);

        $this->assertSame('Partner description', $view->description);
        $this->assertSame(Storage::url('partner/image.jpg'), $view->imageUrl);
        $this->assertSame(Storage::url('partner/video.mp4'), $view->videoUrl);
    }

    public function test_the_override_flags_say_which_fields_the_partner_has_set(): void
    {
        [$exercise, $partner] = $this->exerciseWithOverrides([
            'description' => 'Partner description',
            'image' => null,
            'video' => 'partner/video.mp4',
        ]);

        $view = PartnerExerciseView::of($exercise, $partner);

        $this->assertTrue($view->hasDescriptionOverride);
        $this->assertFalse($view->hasImageOverride);
        $this->assertTrue($view->hasVideoOverride);

        $none = PartnerExerciseView::of($exercise, null);

        $this->assertFalse($none->hasDescriptionOverride);
        $this->assertFalse($none->hasImageOverride);
        $this->assertFalse($none->hasVideoOverride);
    }

    public function test_a_partner_without_overrides_gets_the_exercise_defaults(): void
    {
        [$exercise, $partner] = $this->exerciseWithOverrides([
            'description' => null,
            'image' => null,
            'video' => null,
        ]);

        $view = PartnerExerciseView::of($exercise, $partner);

        $this->assertSame('Default description', $view->description);
        $this->assertSame(Storage::url('default/image.jpg'), $view->imageUrl);
        $this->assertSame(Storage::url('default/video.mp4'), $view->videoUrl);
    }

    public function test_no_partner_gets_the_exercise_defaults(): void
    {
        [$exercise] = $this->exerciseWithOverrides([
            'description' => 'Partner description',
            'image' => 'partner/image.jpg',
            'video' => 'partner/video.mp4',
        ]);

        $view = PartnerExerciseView::of($exercise, null);

        $this->assertSame('Default description', $view->description);
        $this->assertSame(Storage::url('default/image.jpg'), $view->imageUrl);
        $this->assertSame(Storage::url('default/video.mp4'), $view->videoUrl);
    }

    public function test_an_absent_default_and_no_override_resolve_to_null(): void
    {
        $exercise = Exercise::factory()->create([
            'description' => null,
            'image' => null,
            'video' => null,
        ]);
        $partner = Partner::factory()->create();
        $partner->exercises()->attach($exercise->id, [
            'description' => null,
            'image' => null,
            'video' => null,
        ]);

        $view = PartnerExerciseView::of($exercise->fresh(), $partner);

        $this->assertNull($view->description);
        $this->assertNull($view->imageUrl);
        $this->assertNull($view->videoUrl);
    }

    public function test_another_partners_override_is_not_returned(): void
    {
        [$exercise, $partner] = $this->exerciseWithOverrides([
            'description' => 'Partner description',
            'image' => 'partner/image.jpg',
            'video' => 'partner/video.mp4',
        ]);

        $other = Partner::factory()->create();
        $other->exercises()->attach($exercise->id, [
            'description' => 'Other description',
            'image' => 'other/image.jpg',
            'video' => 'other/video.mp4',
        ]);

        $mine = PartnerExerciseView::of(Exercise::find($exercise->id), $partner);
        $theirs = PartnerExerciseView::of(Exercise::find($exercise->id), $other);

        $this->assertSame('Partner description', $mine->description);
        $this->assertSame(Storage::url('partner/image.jpg'), $mine->imageUrl);
        $this->assertSame(Storage::url('partner/video.mp4'), $mine->videoUrl);

        $this->assertSame('Other description', $theirs->description);
        $this->assertSame(Storage::url('other/image.jpg'), $theirs->imageUrl);
        $this->assertSame(Storage::url('other/video.mp4'), $theirs->videoUrl);
    }

    public function test_a_partner_not_linked_to_the_exercise_gets_the_defaults(): void
    {
        [$exercise] = $this->exerciseWithOverrides([
            'description' => 'Partner description',
            'image' => 'partner/image.jpg',
            'video' => 'partner/video.mp4',
        ]);

        $stranger = Partner::factory()->create();

        $view = PartnerExerciseView::of(Exercise::find($exercise->id), $stranger);

        $this->assertSame('Default description', $view->description);
    }

    /**
     * The precondition test. An exercise read without `with('partners')` must
     * answer exactly as one read with it — the old three methods silently
     * returned defaults here, which looks identical to a partner who has set
     * no override.
     */
    public function test_the_answer_does_not_depend_on_the_caller_eager_loading_partners(): void
    {
        [$exercise, $partner] = $this->exerciseWithOverrides([
            'description' => 'Partner description',
            'image' => 'partner/image.jpg',
            'video' => 'partner/video.mp4',
        ]);

        // A second exercise so the result set has more than one row: Laravel
        // only stamps the strict-mode flag onto models hydrated in company,
        // since a lone model's lazy load is not the N+1 it guards against.
        Exercise::factory()->create();

        // Strict mode turns the implicit lazy load into an exception, so the
        // module has to satisfy the precondition itself rather than be rescued
        // by the framework. On before hydration: the flag is stamped there.
        Model::preventLazyLoading();

        $eagerLoaded = Exercise::with('partners')->get()->firstWhere('id', $exercise->getKey());
        $bare = Exercise::all()->firstWhere('id', $exercise->getKey());

        $expected = PartnerExerciseView::of($eagerLoaded, $partner);
        $actual = PartnerExerciseView::of($bare, $partner);

        // Each of the three, spelled out: an equality assertion alone would
        // hold even if the module resolved no override at all.
        $this->assertSame('Partner description', $actual->description);
        $this->assertSame(Storage::url('partner/image.jpg'), $actual->imageUrl);
        $this->assertSame(Storage::url('partner/video.mp4'), $actual->videoUrl);

        $this->assertSame($expected->description, $actual->description);
        $this->assertSame($expected->imageUrl, $actual->imageUrl);
        $this->assertSame($expected->videoUrl, $actual->videoUrl);
    }

    /**
     * The one hole the module cannot close, pinned so it is a documented
     * contract rather than a surprise: a `partners` relation the caller
     * eager-loaded constrained to a *different* partner is indistinguishable
     * from "this partner is not linked". Constrain the eager load to the
     * partner you are going to ask about, or leave it unconstrained.
     */
    public function test_a_relation_eager_loaded_for_another_partner_reads_as_no_link(): void
    {
        [$exercise, $partner] = $this->exerciseWithOverrides([
            'description' => 'Partner description',
            'image' => 'partner/image.jpg',
            'video' => 'partner/video.mp4',
        ]);

        $other = Partner::factory()->create();
        $other->exercises()->attach($exercise->id, ['description' => null, 'image' => null, 'video' => null]);

        $misloaded = Exercise::with(['partners' => fn ($q) => $q->where('partners.id', $other->getKey())])
            ->findOrFail($exercise->getKey());

        $this->assertSame('Default description', PartnerExerciseView::of($misloaded, $partner)->description);

        $unconstrained = Exercise::with('partners')->findOrFail($exercise->getKey());

        $this->assertSame('Partner description', PartnerExerciseView::of($unconstrained, $partner)->description);
    }

    /**
     * The second branch: reached through the partner, the exercise carries the
     * pivot on itself rather than in a loaded relation.
     */
    public function test_an_exercise_reached_through_the_partner_resolves_from_its_own_pivot(): void
    {
        [, $partner] = $this->exerciseWithOverrides([
            'description' => 'Partner description',
            'image' => 'partner/image.jpg',
            'video' => 'partner/video.mp4',
        ]);

        $viaPartner = $partner->exercises()->first();

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $view = PartnerExerciseView::of($viaPartner, $partner);

        $this->assertSame('Partner description', $view->description);
        $this->assertSame(Storage::url('partner/image.jpg'), $view->imageUrl);
        $this->assertSame(Storage::url('partner/video.mp4'), $view->videoUrl);
        $this->assertSame(0, $queries, 'A pivot already on the model must not be re-fetched.');
    }

    public function test_resolving_many_exercises_at_once_costs_one_query(): void
    {
        $partner = Partner::factory()->create();

        $exercises = Exercise::factory()->count(5)->create([
            'description' => 'Default description',
        ]);

        foreach ($exercises as $index => $exercise) {
            $partner->exercises()->attach($exercise->id, [
                'description' => "Override {$index}",
                'image' => null,
                'video' => null,
            ]);
        }

        $bare = Exercise::all();

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $views = PartnerExerciseView::forExercises($bare, $partner);

        $this->assertSame(1, $queries, 'Batch resolution must not fan out a query per exercise.');
        foreach ($exercises as $index => $exercise) {
            $this->assertSame("Override {$index}", $views[$exercise->id]->description);
        }
    }

    /**
     * @param  array{description: ?string, image: ?string, video: ?string}  $overrides
     * @return array{0: Exercise, 1: Partner}
     */
    private function exerciseWithOverrides(array $overrides): array
    {
        $exercise = Exercise::factory()->create([
            'description' => 'Default description',
            'image' => 'default/image.jpg',
            'video' => 'default/video.mp4',
        ]);

        $partner = Partner::factory()->create();
        $partner->exercises()->attach($exercise->id, $overrides);

        return [$exercise->fresh(), $partner];
    }
}
