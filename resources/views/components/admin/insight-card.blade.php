{{--
    One Insights question: the question, its headline answer (one number and
    one sentence), one chart or list in the slot, and a footnote saying how it
    is counted.
--}}
@props(['question', 'headline', 'sentence', 'footnote'])

<section {{ $attributes->class('flex flex-col rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]') }}>
    <h2 class="font-display text-base font-semibold text-gray-800 dark:text-white/90">{{ $question }}</h2>
    <div class="mt-2 flex flex-wrap items-baseline gap-x-2 gap-y-1">
        <span class="font-display text-3xl font-semibold text-gray-900 dark:text-white">{{ $headline }}</span>
        <span class="text-sm text-gray-500 dark:text-gray-400">{{ $sentence }}</span>
        {{ $comparison ?? '' }}
    </div>
    <div class="mt-3 flex-1">
        {{ $slot }}
    </div>
    <p class="mt-3 border-t border-gray-100 pt-2 text-theme-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">{{ $footnote }}</p>
</section>
