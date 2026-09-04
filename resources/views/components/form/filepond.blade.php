@props([
    'name',
    'id' => null,
    'label' => null,
    'hint' => null,
    'accept' => 'image/jpeg,image/png,image/webp',
    'maxFileSize' => '5MB',
    'allowMultiple' => false,
    'currentFileUrl' => null,
    'required' => false,
    'allowCrop' => false,
    'cropAspectRatio' => null,
    'resizeTargetWidth' => null,
    'resizeTargetHeight' => null,
    'resizeMode' => 'cover',
])

@php
    $inputId = $id ?? $name;
@endphp

<div class="space-y-1.5">
    @if($label)
        <label for="{{ $inputId }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
            {{ $label }}
            @if($required)
                <span class="text-error-500">*</span>
            @endif
        </label>
    @endif

    @if($currentFileUrl)
        <div class="mb-3 flex items-center gap-3">
            <img src="{{ $currentFileUrl }}" alt="Current file" class="h-20 w-auto rounded-lg border border-gray-200 object-cover dark:border-gray-700">
            <span class="text-sm text-gray-600 dark:text-gray-400">Current file</span>
        </div>
    @endif

    <input type="file"
        name="{{ $name }}"
        id="{{ $inputId }}"
        accept="{{ $accept }}"
        {{ $required ? 'required' : '' }}
        data-filepond
        data-max-file-size="{{ $maxFileSize }}"
        data-allow-multiple="{{ $allowMultiple ? 'true' : 'false' }}"
        data-allow-crop="{{ $allowCrop ? 'true' : 'false' }}"
        data-crop-aspect-ratio="{{ $cropAspectRatio ?? '' }}"
        data-allow-resize="{{ ($resizeTargetWidth || $resizeTargetHeight) ? 'true' : 'false' }}"
        data-resize-target-width="{{ $resizeTargetWidth ?? '' }}"
        data-resize-target-height="{{ $resizeTargetHeight ?? '' }}"
        data-resize-mode="{{ $resizeMode ?? 'cover' }}"
        data-accept="{{ $accept }}"
        class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-gray-800 outline-none transition focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:border-gray-800 dark:bg-white/3 dark:text-white/90 dark:focus:border-brand-500">

    @if($hint)
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="mt-1 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
    @enderror
</div>
