{{--
    The partner form shared by create and edit: details, logo and the four
    colours on the left, a sticky light and dark preview on the right.

    $partner  ?Partner — null when creating
    $branding array{light: array, dark: array} — ColorHelper values per mode
    $action, $method, $submitLabel
--}}
@php
    $card = 'rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]';
    $label = 'mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400';
    $input = 'h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $isHouse = $partner?->isHouse() ?? false;
    $logo = $partner?->identity?->logo ? $partner->identity->logo_url : null;
    $modes = ['light' => 'Light', 'dark' => 'Dark'];
    $slots = ['primary' => 'Primary', 'secondary' => 'Secondary'];
    $fieldName = fn (string $mode, string $slot) => $mode === 'light' ? "{$slot}_color" : "{$slot}_color_dark";
@endphp

<form action="{{ $action }}" method="POST" enctype="multipart/form-data"
    x-data="partnerForm(@js([
        'name' => old('name', $partner?->name ?? ''),
        'slug' => old('slug', $partner?->slug ?? ''),
        'logo' => $logo,
        'light' => $branding['light'],
        'dark' => $branding['dark'],
        'autoSlug' => $partner === null,
    ]))">
    @csrf
    @unless ($method === 'POST')
        @method($method)
    @endunless

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-error-200 bg-error-50 p-4 dark:border-error-800 dark:bg-error-900/20">
            <ul class="list-inside list-disc space-y-1 text-sm text-error-700 dark:text-error-300">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="space-y-6">
            <section class="{{ $card }} space-y-4">
                <h2 class="font-display text-base font-semibold text-gray-800 dark:text-white/90">Details</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="name" class="{{ $label }}">Name <span class="text-error-500">*</span></label>
                        <input id="name" name="name" type="text" required x-model="f.name" @input="syncSlug()" class="{{ $input }}" />
                        @error('name') <p class="mt-1 text-theme-xs text-error-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="slug" class="{{ $label }}">Slug <span class="text-error-500">*</span></label>
                        <input id="slug" name="slug" type="text" required x-model="f.slug" @input="f.autoSlug = false"
                            @if ($partner) readonly @endif
                            class="{{ $input }} {{ $partner ? 'cursor-not-allowed bg-gray-50 dark:bg-gray-800' : '' }}" />
                        <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">{{ $partner ? 'Cannot be changed.' : 'Made from the name; letters, numbers and dashes.' }}</p>
                        @error('slug') <p class="mt-1 text-theme-xs text-error-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="domain" class="{{ $label }}">Domain</label>
                        <input id="domain" name="domain" type="text" value="{{ old('domain', $partner?->domain) }}" placeholder="partner.example.com" class="{{ $input }}" />
                        @error('domain') <p class="mt-1 text-theme-xs text-error-500">{{ $message }}</p> @enderror
                    </div>
                    <div class="self-end pb-2">
                        @if ($isHouse)
                            <input type="hidden" name="is_active" value="1" />
                            <label class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                                <input type="checkbox" checked disabled class="rounded" /> Active
                            </label>
                            <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">The House Partner is always active.</p>
                        @else
                            <input type="hidden" name="is_active" value="0" />
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $partner?->is_active ?? true)) class="rounded border-gray-300 text-brand-500 dark:border-gray-700" /> Active
                            </label>
                        @endif
                        @error('is_active') <p class="mt-1 text-theme-xs text-error-500">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <section class="{{ $card }} space-y-4">
                <h2 class="font-display text-base font-semibold text-gray-800 dark:text-white/90">Logo</h2>
                <div class="flex items-center gap-4">
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-full bg-gray-100 ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                        <img x-show="f.logo" :src="f.logo" alt="" class="h-full w-full object-cover" />
                        <span x-show="! f.logo" class="text-theme-xs text-gray-400">None</span>
                    </div>
                    <label class="flex flex-1 cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500 hover:border-brand-400 dark:border-gray-700 dark:text-gray-400">
                        <span>Choose a PNG, JPG or SVG, or <span class="text-brand-600 dark:text-brand-400">browse</span></span>
                        <span class="text-theme-xs">Square, at least 256×256, up to 2 MB. Shown round in the app.</span>
                        <input type="file" name="logo" accept="image/png,image/jpeg,image/gif,image/svg+xml" class="hidden" @change="pickLogo($event)" />
                    </label>
                </div>
                @error('logo') <p class="text-theme-xs text-error-500">{{ $message }}</p> @enderror
            </section>

            <section class="{{ $card }} space-y-4">
                <div>
                    <h2 class="font-display text-base font-semibold text-gray-800 dark:text-white/90">Colours</h2>
                    <p class="text-theme-xs text-gray-500 dark:text-gray-400">Primary and secondary for each mode. Backgrounds and text keep their current values.</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($modes as $mode => $modeLabel)
                        <div class="space-y-3 rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                            <div class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $modeLabel }} mode</div>
                            @foreach ($slots as $slot => $slotLabel)
                                @php($field = $fieldName($mode, $slot))
                                <div>
                                    <label for="{{ $field }}" class="{{ $label }}">{{ $slotLabel }} <span class="text-error-500">*</span></label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" x-model="f.{{ $mode }}.{{ $slot }}" aria-label="{{ $modeLabel }} {{ $slotLabel }} picker" class="h-10 w-12 shrink-0 cursor-pointer rounded border border-gray-300 dark:border-gray-700" />
                                        <input id="{{ $field }}" name="{{ $field }}" type="text" required pattern="#[0-9A-Fa-f]{6}" maxlength="7"
                                            value="{{ $branding[$mode][$slot] }}" x-model="f.{{ $mode }}.{{ $slot }}" class="{{ $input }} font-mono uppercase" />
                                    </div>
                                    @error($field) <p class="mt-1 text-theme-xs text-error-500">{{ $message }}</p> @enderror
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </section>

            <div class="flex items-center justify-end gap-2">
                <span x-show="dirty" x-cloak class="mr-auto rounded-full bg-orange-500/15 px-2 py-0.5 text-theme-xs font-medium text-orange-500">Unsaved changes</span>
                <a href="{{ $cancelUrl }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">Cancel</a>
                <button type="submit" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">{{ $submitLabel }}</button>
            </div>
        </div>

        <aside class="lg:sticky lg:top-24 lg:self-start">
            <div class="{{ $card }} space-y-4">
                <h2 class="font-display text-base font-semibold text-gray-800 dark:text-white/90">Preview</h2>
                @foreach ($modes as $mode => $modeLabel)
                    {{-- Partner colours are data here, so they are inline styles, not tokens. --}}
                    <div>
                        <div class="mb-1 text-theme-xs font-medium text-gray-500 dark:text-gray-400">{{ $modeLabel }}</div>
                        <div class="rounded-lg p-3" :style="{ backgroundColor: f.{{ $mode }}.background }">
                            <div class="rounded-md p-3" :style="{ backgroundColor: f.{{ $mode }}.card_background }">
                                <div class="flex items-center gap-2">
                                    <img x-show="f.logo" :src="f.logo" alt="" class="h-6 w-6 rounded-full object-cover" />
                                    <span class="text-sm font-semibold" :style="{ color: f.{{ $mode }}.text_primary }" x-text="f.name || 'Partner name'"></span>
                                </div>
                                <p class="mt-1 text-theme-xs" :style="{ color: f.{{ $mode }}.text_secondary }">Today's workout · Push</p>
                                <div class="mt-2 flex gap-2">
                                    <span class="rounded-md px-2.5 py-1 text-theme-xs font-medium" :style="{ backgroundColor: f.{{ $mode }}.primary, color: f.{{ $mode }}.text_on_primary }">Start</span>
                                    <span class="rounded-md border px-2.5 py-1 text-theme-xs font-medium" :style="{ borderColor: f.{{ $mode }}.secondary, color: f.{{ $mode }}.secondary }">Swap</span>
                                </div>
                            </div>
                        </div>
                        <div class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400">
                            White on primary: <b x-text="contrastWithWhite(f.{{ $mode }}.primary)?.toFixed(1).concat(':1') ?? '—'"></b>
                            <span x-show="(contrastWithWhite(f.{{ $mode }}.primary) ?? Infinity) < 4.5" class="text-orange-500">(below 4.5:1)</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </aside>
    </div>
</form>

@once
    @push('scripts')
        <script>
            function partnerForm(initial) {
                return {
                    f: initial,
                    dirty: false,
                    init() {
                        this.$watch('f', () => this.dirty = true);
                    },
                    syncSlug() {
                        if (this.f.autoSlug) {
                            this.f.slug = this.f.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
                        }
                    },
                    pickLogo(event) {
                        const file = event.target.files[0];
                        if (file) {
                            this.f.logo = URL.createObjectURL(file);
                        }
                    },
                    // WCAG contrast ratio of white text on the given hex colour (e.g. 4.6), null when it is not #rrggbb.
                    contrastWithWhite(hex) {
                        if (! /^#[0-9a-f]{6}$/i.test(hex || '')) {
                            return null;
                        }
                        const channel = (i) => {
                            const c = parseInt(hex.slice(i, i + 2), 16) / 255;
                            return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
                        };
                        const luminance = 0.2126 * channel(1) + 0.7152 * channel(3) + 0.0722 * channel(5);
                        return 1.05 / (luminance + 0.05);
                    },
                };
            }
        </script>
    @endpush
@endonce
