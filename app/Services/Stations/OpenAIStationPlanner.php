<?php

namespace App\Services\Stations;

use App\Models\Exercise;
use App\Models\Partner;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * The real StationPlanner: one OpenAI chat completion grouping a partner's
 * exercise roster into gym-floor Stations, same HTTP pattern and OPENAI_API_KEY
 * as OpenAIGuidanceGenerator. Strictly validated: every proposed exercise id
 * must come from the roster it was given, or the whole plan is rejected.
 */
class OpenAIStationPlanner implements StationPlanner
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
You are planning QR-code stations for a gym floor. A "station" is one physical spot — a machine, rack, or free-weight area — where a member stands and scans a QR code to see the exercises performable right there.

You get a JSON list of the gym's exercises (id, name, equipment, primary muscles). Group them into stations the way the equipment actually stands on a gym floor:

- One station per distinct machine or spot (e.g. "Leg Press", "Cable Station", "Squat Rack", "Dumbbell Area"), NOT one per exercise and NOT one per broad equipment class.
- Exercises sharing one physical machine belong on the same station; order them most-common-first.
- Every station name is short and floor-recognisable (what a member would call the machine).
- Use each exercise id at most once, and only ids from the given list. Leave an exercise out if it fits no sensible station.
- Every station MUST have a name and at least one exercise id. Never emit a station with an empty exercise_ids list — omit it entirely instead.
- exercise_ids are JSON numbers, never strings.

Return ONLY a valid JSON object, no markdown, no commentary:

{"stations": [{"name": "Leg Press", "exercise_ids": [12, 34]}]}
PROMPT;

    public function plan(Partner $partner, Collection $exercises): array
    {
        $key = config('services.openai.key');

        if (empty($key)) {
            throw new StationPlanningFailed('No OpenAI API key configured (services.openai.key / OPENAI_API_KEY).');
        }

        $roster = $exercises->map(fn (Exercise $exercise) => [
            'id' => $exercise->id,
            'name' => $exercise->name,
            'equipment' => $exercise->equipmentType?->name,
            'primary_muscles' => $exercise->primaryMuscleGroups->pluck('name')->values(),
        ])->values();

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$key,
                'Content-Type' => 'application/json',
            ])->timeout(120)->post(config('services.openai.base_url').'/chat/completions', [
                'model' => config('services.openai.guidance_model'),
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                    ['role' => 'user', 'content' => "Gym: {$partner->name}\nExercises:\n".$roster->toJson()],
                ],
                'temperature' => 0.2,
            ]);
        } catch (ConnectionException $e) {
            throw new StationPlanningFailed("OpenAI API unreachable: {$e->getMessage()}", previous: $e);
        }

        if (! $response->successful()) {
            throw new StationPlanningFailed("OpenAI API error (HTTP {$response->status()}): ".$response->body());
        }

        $text = $response->json('choices.0.message.content');

        if (! is_string($text) || trim($text) === '') {
            throw new StationPlanningFailed('OpenAI response contained no message content.');
        }

        return $this->parse($text, $exercises->pluck('id')->all());
    }

    /**
     * @param  list<int>  $allowedIds
     * @return list<ProposedStation>
     */
    private function parse(string $text, array $allowedIds): array
    {
        $json = preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim($text));
        $data = json_decode($json, true);

        if (! is_array($data) || ! isset($data['stations']) || ! is_array($data['stations']) || ! array_is_list($data['stations'])) {
            throw new StationPlanningFailed('OpenAI response was not a {"stations": [...]} object: '.str($text)->limit(200));
        }

        $allowed = array_flip($allowedIds);
        $seen = [];
        $proposals = [];

        foreach ($data['stations'] as $station) {
            $name = is_array($station) ? ($station['name'] ?? null) : null;
            $ids = is_array($station) ? ($station['exercise_ids'] ?? null) : null;

            // The model sometimes pads a long plan with a nameless or empty
            // station despite the prompt; the exercises it holds (if any) stay
            // unstationed and a re-run picks them up, so skip it rather than
            // rejecting the whole plan.
            if (! is_string($name) || trim($name) === '' || ! is_array($ids) || $ids === [] || ! array_is_list($ids)) {
                continue;
            }

            $cleanIds = [];

            foreach ($ids as $id) {
                if (is_string($id) && ctype_digit($id)) {
                    $id = (int) $id;
                }

                if (! is_int($id) || ! isset($allowed[$id])) {
                    throw new StationPlanningFailed("Proposed station \"{$name}\" references an exercise id that was not offered: ".var_export($id, true));
                }
                // The model sometimes repeats an exercise on a second station
                // despite the prompt; the first station keeps it.
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $cleanIds[] = $id;
            }

            if ($cleanIds === []) {
                continue;
            }

            $proposals[] = new ProposedStation(trim($name), $cleanIds);
        }

        if ($proposals === []) {
            throw new StationPlanningFailed('OpenAI proposed no usable stations at all.');
        }

        return $proposals;
    }
}
