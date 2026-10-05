@extends('layouts.app')

@section('title', $partner->name)

@php
    $dark = [
        'primary' => $darkBranding['Primary'] ?? null,
        'secondary' => $darkBranding['Secondary'] ?? null,
        'background' => $darkBranding['Background'] ?? null,
        'card_background' => $darkBranding['Card Background'] ?? null,
        'text_primary' => $darkBranding['Text Primary'] ?? null,
        'text_secondary' => $darkBranding['Text Secondary'] ?? null,
        'text_on_primary' => $darkBranding['Text On Primary'] ?? null,
    ];
    $logo = $partner->identity?->logo ? $partner->identity->logo_url : null;
@endphp

@section('content')
    <div class="space-y-4">
        <a href="{{ route('admin.partners.index') }}" class="text-sm text-brand-600 hover:underline dark:text-brand-400">← Partners</a>

        @if (session('success'))
            <div class="rounded-lg border border-success-200 bg-success-50 p-4 dark:border-success-800 dark:bg-success-900/20">
                <p class="text-sm text-success-800 dark:text-success-200">{{ session('success') }}</p>
            </div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg border border-error-200 bg-error-50 p-4 dark:border-error-800 dark:bg-error-900/20">
                <p class="text-sm text-error-700 dark:text-error-300">{{ $errors->first() }}</p>
            </div>
        @endif

        <div class="flex flex-wrap items-center gap-3">
            <h1 class="font-display text-xl font-semibold text-gray-900 dark:text-white">{{ $partner->name }}</h1>
            <span class="rounded-full bg-brand-50 px-2.5 py-0.5 text-theme-xs font-medium text-brand-700 dark:bg-brand-500/15 dark:text-brand-400">{{ $partner->kind()->label() }}</span>
            @include('admin.partners._status', ['partner' => $partner])
            <div class="ml-auto flex items-center gap-2">
                <a href="{{ route('partners.edit', $partner) }}" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">Edit partner &amp; branding</a>
                @unless ($partner->isHouse() && $partner->is_active)
                <form method="POST" action="{{ route('admin.partners.active.update', $partner) }}"
                    onsubmit="return confirm(@js($partner->is_active ? "Deactivate {$partner->name}? Nothing is deleted; you can reactivate it." : "Reactivate {$partner->name}?"))">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="active" value="{{ $partner->is_active ? 0 : 1 }}" />
                    @if ($partner->is_active)
                        <button type="submit" class="rounded-lg border border-error-300 px-3 py-1.5 text-sm text-error-600 hover:bg-error-50 dark:border-error-700 dark:text-error-400 dark:hover:bg-error-500/10">Deactivate partner</button>
                    @else
                        <button type="submit" class="rounded-lg border border-brand-300 px-3 py-1.5 text-sm text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-400 dark:hover:bg-brand-500/10">Reactivate partner</button>
                    @endif
                </form>
                @endunless
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <section class="rounded-xl border border-gray-200 bg-white p-4 text-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <h2 class="font-display font-semibold text-gray-900 dark:text-white">Plan &amp; sponsorship</h2>
                <dl class="mt-2 space-y-1 text-gray-700 dark:text-gray-300">
                    <div>Plan: <b>{{ $partner->plan ? ucfirst($partner->plan->value) : '—' }}</b></div>
                    <div>Sponsorship until: <b>{{ $partner->plan_expires_at?->format('j M Y') ?? '—' }}</b></div>
                    <div>Members: <b>{{ number_format($memberCount) }}</b> · active this week <b>{{ number_format($activeThisWeek) }}</b></div>
                </dl>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-4 text-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <h2 class="font-display font-semibold text-gray-900 dark:text-white">Admins</h2>
                @forelse ($admins as $admin)
                    <p class="mt-2 text-gray-800 dark:text-white/90">{{ $admin->name }} <span class="block text-theme-xs text-gray-500 dark:text-gray-400">{{ $admin->email }}</span></p>
                @empty
                    <p class="mt-2 text-gray-500 dark:text-gray-400">No partner admins.</p>
                @endforelse
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-4 text-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <h2 class="font-display font-semibold text-gray-900 dark:text-white">Branding</h2>
                <div class="mt-2 flex gap-3">
                    @include('admin.partners._branding-preview', ['mode' => 'Light', 'colors' => $lightBranding, 'logo' => $logo, 'name' => $partner->name])
                    @include('admin.partners._branding-preview', ['mode' => 'Dark', 'colors' => $dark, 'logo' => $logo, 'name' => $partner->name])
                </div>
            </section>
        </div>

        <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <h2 class="font-display text-sm font-semibold text-gray-900 dark:text-white">Members</h2>
            @if ($members->isEmpty())
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No members yet.</p>
            @else
                <table class="mt-2 w-full text-sm">
                    <tbody>
                        @foreach ($members as $member)
                            <tr class="border-t border-gray-100 dark:border-gray-800">
                                <td class="py-2">
                                    <a href="{{ route('admin.users.show', $member) }}" class="font-medium text-gray-800 hover:text-brand-600 hover:underline dark:text-white/90 dark:hover:text-brand-400">{{ $member->name }}</a>
                                    <span class="block text-theme-xs text-gray-500 dark:text-gray-400">{{ $member->email }}</span>
                                </td>
                                <td class="py-2"><x-admin.activity-chip :status="$statuses[$member->id]" /></td>
                                <td class="py-2"><x-admin.access-chip :access="$access[$member->id]" /></td>
                                <td class="whitespace-nowrap py-2 text-theme-xs text-gray-500 dark:text-gray-400">joined {{ $member->created_at->format('j M Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
            <a href="{{ route('admin.users.index', ['partner' => $partner->id]) }}" class="mt-2 inline-block text-sm text-brand-600 hover:underline dark:text-brand-400">All members →</a>
        </section>
    </div>
@endsection
