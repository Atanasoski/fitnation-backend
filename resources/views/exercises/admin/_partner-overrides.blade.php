{{--
    Partner Overrides in the exercise slide-over (spec 023, ticket 04): every
    partner linked to the exercise, with what its members see, editable or
    clearable; and linking or unlinking a partner. Reads come from
    PartnerExerciseView. Expects $exercise, $overrides (linked: partner + view
    pairs, unlinked: partners) and $back.
--}}
@php
    $heading = 'mb-2 text-theme-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400';
    $badge = 'rounded bg-brand-500/10 px-1.5 text-theme-xs text-brand-700 dark:text-brand-300';
    $error = 'mt-1 text-theme-xs text-error-600 dark:text-error-400';
@endphp

<section class="space-y-3 border-t border-gray-200 px-6 py-5 dark:border-gray-800">
    <div class="{{ $heading }}">Partner Overrides</div>
    <p class="text-theme-xs text-gray-500 dark:text-gray-400">
        A linked partner can replace the description, image or video for its members. Anything left blank shows the catalogue version.
    </p>

    @if ($overrides['linked']->isEmpty())
        <p class="text-sm text-gray-400">Not linked to any partner.</p>
    @else
        <div class="divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-gray-800 dark:border-gray-800">
            @foreach ($overrides['linked'] as ['partner' => $partner, 'view' => $view])
                @php $formId = 'override-'.$partner->id; @endphp
                <div class="space-y-2 p-3 text-sm" x-data="{ open: false }">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium text-gray-800 dark:text-white/90">{{ $partner->name }}</span>
                        @if ($view->hasDescriptionOverride) <span class="{{ $badge }}">description</span> @endif
                        @if ($view->hasImageOverride) <span class="{{ $badge }}">image</span> @endif
                        @if ($view->hasVideoOverride) <span class="{{ $badge }}">video</span> @endif
                        @unless ($view->hasDescriptionOverride || $view->hasImageOverride || $view->hasVideoOverride)
                            <span class="text-theme-xs text-gray-400">catalogue version</span>
                        @endunless
                        <button type="button" @click="open = ! open" class="ml-auto text-theme-xs font-medium text-brand-600 hover:underline dark:text-brand-400" x-text="open ? 'Close' : 'Edit'">Edit</button>
                    </div>

                    @if ($view->hasDescriptionOverride)
                        <p x-show="! open" class="text-theme-xs text-gray-500 dark:text-gray-400">“{{ \Illuminate\Support\Str::limit($view->description, 160) }}”</p>
                    @endif

                    <div x-show="open" x-cloak class="space-y-2">
                        <form id="{{ $formId }}" method="POST" enctype="multipart/form-data" action="{{ route('exercises.partners.update', [$exercise, $partner]) }}" class="space-y-2">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="back" value="{{ $back }}">
                            <label for="{{ $formId }}-description" class="block text-theme-xs font-medium text-gray-600 dark:text-gray-400">Description</label>
                            <textarea id="{{ $formId }}-description" name="description" rows="2" maxlength="5000" placeholder="Blank = the catalogue description"
                                class="w-full rounded-lg border border-gray-300 bg-transparent p-2 text-sm text-gray-800 focus:border-brand-300 focus:outline-hidden dark:border-gray-700 dark:text-white/90">{{ $view->hasDescriptionOverride ? $view->description : '' }}</textarea>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <div class="mb-1 text-theme-xs font-medium text-gray-600 dark:text-gray-400">Image</div>
                                    @if ($view->hasImageOverride && $view->imageUrl)
                                        <img src="{{ $view->imageUrl }}" alt="" class="mb-1 aspect-video w-full rounded-md object-cover">
                                        <label class="mb-1 flex items-center gap-1.5 text-theme-xs text-gray-600 dark:text-gray-400"><input type="checkbox" name="remove_image" value="1"> Remove own image</label>
                                    @endif
                                    <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp" class="w-full text-theme-xs text-gray-600 dark:text-gray-400" aria-label="{{ $partner->name }} image">
                                </div>
                                <div>
                                    <div class="mb-1 text-theme-xs font-medium text-gray-600 dark:text-gray-400">Video</div>
                                    @if ($view->hasVideoOverride && $view->videoUrl)
                                        <video src="{{ $view->videoUrl }}" controls preload="none" class="mb-1 aspect-video w-full rounded-md bg-gray-900"></video>
                                        <label class="mb-1 flex items-center gap-1.5 text-theme-xs text-gray-600 dark:text-gray-400"><input type="checkbox" name="remove_video" value="1"> Remove own video</label>
                                    @endif
                                    <input type="file" name="video" accept="video/mp4,video/webm,video/ogg" class="w-full text-theme-xs text-gray-600 dark:text-gray-400" aria-label="{{ $partner->name }} video">
                                </div>
                            </div>
                        </form>
                        <div class="flex items-center gap-3">
                            <button type="submit" form="{{ $formId }}" class="rounded-lg bg-brand-500 px-3 py-1.5 text-theme-xs font-medium text-white hover:bg-brand-600">Save override</button>
                            <form method="POST" action="{{ route('exercises.partners.clear', [$exercise, $partner]) }}"
                                onsubmit="return confirm(@js('Clear '.$partner->name.'’s override? Its members will see the catalogue description, image and video.'))">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="back" value="{{ $back }}">
                                <button type="submit" class="text-theme-xs text-gray-600 hover:underline dark:text-gray-400">Clear override</button>
                            </form>
                            <form method="POST" action="{{ route('exercises.partners.unlink', [$exercise, $partner]) }}" class="ml-auto"
                                onsubmit="return confirm(@js('Unlink '.$partner->name.'? This hides the exercise from its members, and deletes its override.'))">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="back" value="{{ $back }}">
                                <button type="submit" class="text-theme-xs text-error-500 hover:underline">Unlink</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @error('image', 'override') <p class="{{ $error }}">{{ $message }}</p> @enderror
    @error('video', 'override') <p class="{{ $error }}">{{ $message }}</p> @enderror
    @error('description', 'override') <p class="{{ $error }}">{{ $message }}</p> @enderror

    @if ($overrides['unlinked']->isNotEmpty())
        <form method="POST" action="{{ route('exercises.partners.link', $exercise) }}" class="flex items-center gap-2">
            @csrf
            <input type="hidden" name="back" value="{{ $back }}">
            <select name="partner_id" required aria-label="Partner to link"
                class="h-8 rounded-lg border border-gray-300 bg-transparent px-2 text-theme-xs text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                <option value="">Link to partner…</option>
                @foreach ($overrides['unlinked'] as $partner)
                    <option value="{{ $partner->id }}">{{ $partner->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="text-theme-xs font-medium text-brand-600 hover:underline dark:text-brand-400">Link</button>
        </form>
        @error('partner_id', 'override') <p class="{{ $error }}">{{ $message }}</p> @enderror
    @endif
</section>
