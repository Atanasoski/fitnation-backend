<?php

namespace App\Http\Requests;

use App\Enums\PaywallDiscountTier;
use App\Models\Partner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePartnerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $partner = $this->route('partner');

        return $this->user()->can('update', $partner);
    }

    /**
     * The admin edits benefits as a one-per-line textarea; validation and
     * storage both want the array (GLOSSARY: Join Prompt). Blank lines — and
     * blank entries, should a client post the array directly — are dropped, so
     * they never count towards the "at least one benefit" enable gate.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('benefits')) {
            return;
        }

        $lines = is_array($this->input('benefits'))
            ? $this->input('benefits')
            : preg_split('/\r\n|\r|\n/', (string) $this->input('benefits'));

        $this->merge([
            'benefits' => array_values(array_filter(
                array_map(fn ($line) => trim((string) $line), $lines),
                fn (string $line) => $line !== '',
            )),
        ]);
    }

    /**
     * Whether this submission carries the form's Join Prompt section. With
     * every Gym Location row removed only the section's hidden marker is
     * posted; older posts without the section leave the locations alone.
     */
    public function gymLocationsSubmitted(): bool
    {
        return $this->has('gym_locations') || $this->boolean('gym_locations_submitted');
    }

    /**
     * Enabling the Join Prompt requires at least one Gym Location and one
     * benefit, so a half-configured gym can never produce an empty prompt
     * (issue #86). Disabling is always allowed. Counts come from the submitted
     * form when it carries the section, from the partner otherwise.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->boolean('geo_prompt_enabled')) {
                return;
            }

            /** @var Partner $partner */
            $partner = $this->route('partner');

            $locations = $this->gymLocationsSubmitted()
                ? count($this->input('gym_locations', []))
                : $partner->gymLocations()->count();
            $benefits = $this->has('benefits')
                ? count($this->input('benefits', []))
                : count($partner->benefits ?? []);

            if ($locations < 1) {
                $validator->errors()->add('geo_prompt_enabled', 'The Join Prompt needs at least one Gym Location before it can be enabled.');
            }

            if ($benefits < 1) {
                $validator->errors()->add('geo_prompt_enabled', 'The Join Prompt needs at least one benefit before it can be enabled.');
            }
        });
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $partnerId = $this->route('partner');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('partners')->ignore($partnerId), 'alpha_dash'],
            'domain' => ['nullable', 'string', 'max:255', Rule::unique('partners')->ignore($partnerId)],
            'is_active' => ['boolean'],
            'paywall_discount_tier' => ['nullable', Rule::enum(PaywallDiscountTier::class)],

            // Join Prompt configuration
            'geo_prompt_enabled' => ['boolean'],
            'benefits' => ['array'],
            'benefits.*' => ['string', 'max:255'],
            'gym_locations' => ['array'],
            'gym_locations.*.id' => ['nullable', 'integer'],
            'gym_locations.*.label' => ['required', 'string', 'max:255'],
            'gym_locations.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'gym_locations.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'gym_locations.*.radius_m' => ['nullable', 'integer', 'min:10', 'max:100000'],

            // Identity fields
            'primary_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,svg', 'max:2048'],
            'font_family' => ['nullable', 'string', 'max:255'],
            'background_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'card_background_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'text_primary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'text_secondary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'text_on_primary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'success_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'warning_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'danger_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'border_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'background_pattern' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,svg', 'max:2048'],
            'primary_color_dark' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color_dark' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'background_color_dark' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'card_background_color_dark' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'text_primary_color_dark' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'text_secondary_color_dark' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'text_on_primary_color_dark' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'success_color_dark' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'warning_color_dark' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'danger_color_dark' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color_dark' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'border_color_dark' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'primary_color.regex' => 'The primary color must be in hex format (e.g., #ff6b35).',
            'secondary_color.regex' => 'The secondary color must be in hex format (e.g., #4ecdc4).',
            'background_color.regex' => 'The background color must be in hex format (e.g., #ffffff).',
            'card_background_color.regex' => 'The card background color must be in hex format (e.g., #f8f9fa).',
            'text_primary_color.regex' => 'The text primary color must be in hex format (e.g., #212529).',
            'text_secondary_color.regex' => 'The text secondary color must be in hex format (e.g., #6c757d).',
            'text_on_primary_color.regex' => 'The text on primary color must be in hex format (e.g., #ffffff).',
            'success_color.regex' => 'The success color must be in hex format (e.g., #10dc60).',
            'warning_color.regex' => 'The warning color must be in hex format (e.g., #ffce00).',
            'danger_color.regex' => 'The danger color must be in hex format (e.g., #f04141).',
            'accent_color.regex' => 'The accent color must be in hex format (e.g., #8ac34a).',
            'border_color.regex' => 'The border color must be in hex format (e.g., #dee2e6).',
            'primary_color_dark.regex' => 'The primary color (dark) must be in hex format (e.g., #fa812d).',
            'secondary_color_dark.regex' => 'The secondary color (dark) must be in hex format (e.g., #292a2c).',
            'background_color_dark.regex' => 'The background color (dark) must be in hex format (e.g., #121212).',
            'card_background_color_dark.regex' => 'The card background color (dark) must be in hex format (e.g., #1e1e1e).',
            'text_primary_color_dark.regex' => 'The text primary color (dark) must be in hex format (e.g., #ffffff).',
            'text_secondary_color_dark.regex' => 'The text secondary color (dark) must be in hex format (e.g., #b0b0b0).',
            'text_on_primary_color_dark.regex' => 'The text on primary color (dark) must be in hex format (e.g., #ffffff).',
            'success_color_dark.regex' => 'The success color (dark) must be in hex format (e.g., #4ade80).',
            'warning_color_dark.regex' => 'The warning color (dark) must be in hex format (e.g., #fff94f).',
            'danger_color_dark.regex' => 'The danger color (dark) must be in hex format (e.g., #ff6b6b).',
            'accent_color_dark.regex' => 'The accent color (dark) must be in hex format (e.g., #fff94f).',
            'border_color_dark.regex' => 'The border color (dark) must be in hex format (e.g., #3a3a3a).',
            'slug.alpha_dash' => 'The slug may only contain letters, numbers, dashes and underscores.',
            'logo.image' => 'The logo must be an image file.',
            'logo.mimes' => 'The logo must be a file of type: jpeg, jpg, png, gif, svg.',
            'logo.max' => 'The logo may not be greater than 2MB.',
            'background_pattern.image' => 'The background pattern must be an image file.',
            'background_pattern.mimes' => 'The background pattern must be a file of type: jpeg, jpg, png, gif, svg.',
            'background_pattern.max' => 'The background pattern may not be greater than 2MB.',
        ];
    }
}
