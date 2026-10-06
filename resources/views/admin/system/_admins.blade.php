{{-- Admins: Admins::list(), with grant and revoke (spec 024 ticket 06). --}}
@php
    use App\Services\Admin\Admins;
    $input = 'h-9 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
@endphp
<section id="admins" class="scroll-mt-24 overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <header class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-800 sm:px-6">
        <div>
            <h2 class="font-display text-base font-semibold text-gray-800 dark:text-white/90">Admins</h2>
            <p class="text-theme-xs text-gray-500 dark:text-gray-400">Who can sign in to this panel or a partner panel.</p>
        </div>
        <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-theme-xs font-medium text-gray-700 dark:bg-white/5 dark:text-gray-300">
            {{ count($admins) }}
        </span>
    </header>

    <div class="max-w-full overflow-x-auto custom-scrollbar">
        <table class="w-full text-sm">
            <tbody>
                @foreach ($admins as $admin)
                    @php
                        $isYou = $admin['user']->is(auth()->user());
                        $isSuperAdmin = $admin['role'] === Admins::SUPER_ADMIN;
                    @endphp
                    <tr class="border-t border-gray-100 first:border-t-0 dark:border-gray-800">
                        <td class="px-5 py-2.5 sm:px-6">
                            <span class="font-medium text-gray-900 dark:text-white">{{ $admin['user']->name }}</span>
                            @if ($isYou)
                                <span class="text-theme-xs text-gray-400">(you)</span>
                            @endif
                            <span class="block text-theme-xs text-gray-400">{{ $admin['user']->email }}</span>
                        </td>
                        <td class="px-3 py-2.5">
                            <span @class([
                                'whitespace-nowrap rounded-full px-2 py-0.5 text-theme-xs',
                                'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300' => $isSuperAdmin,
                                'bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-gray-300' => ! $isSuperAdmin,
                            ])>{{ Admins::ROLES[$admin['role']] }}@unless ($isSuperAdmin) · {{ $admin['partner']?->name ?? 'no partner' }}@endunless</span>
                        </td>
                        <td class="whitespace-nowrap px-3 py-2.5 text-gray-600 dark:text-gray-400" title="{{ $admin['since']?->toDateTimeString() }}">
                            {{ $admin['since'] ? 'since '.$admin['since']->format('j M Y') : 'since before records' }}
                        </td>
                        <td class="whitespace-nowrap px-5 py-2.5 text-right sm:px-6">
                            @unless ($isYou && $isSuperAdmin)
                                <form method="POST" action="{{ route('admin.system.admins.destroy', [$admin['user'], $admin['role']]) }}" class="inline"
                                    onsubmit="return confirm({{ Js::from("Revoke {$admin['user']->name}'s ".Admins::label($admin['role']).' role? They lose access on their next request.') }})">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-theme-xs font-medium text-error-600 hover:underline dark:text-error-400">Revoke</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <form method="POST" action="{{ route('admin.system.admins.store') }}" x-data="{ role: @js(old('role', Admins::SUPER_ADMIN)) }"
        onsubmit="return confirm('Grant this role? The user leaves every app-user count.')"
        class="border-t border-gray-100 px-5 py-3 dark:border-gray-800 sm:px-6">
        @csrf
        <div class="flex flex-wrap gap-2">
            <input type="email" name="email" required value="{{ old('email') }}" placeholder="Email of an existing user" aria-label="Email"
                class="{{ $input }} min-w-0 flex-1">
            <select name="role" x-model="role" aria-label="Role" class="{{ $input }}">
                @foreach (Admins::ROLES as $slug => $label)
                    <option value="{{ $slug }}">{{ $label }}</option>
                @endforeach
            </select>
            <select name="partner_id" x-show="role === @js(Admins::PARTNER_ADMIN)" x-cloak :disabled="role !== @js(Admins::PARTNER_ADMIN)" :required="role === @js(Admins::PARTNER_ADMIN)" aria-label="Partner" class="{{ $input }}">
                <option value="">Choose a partner…</option>
                @foreach ($partners as $partner)
                    <option value="{{ $partner->id }}" @selected((int) old('partner_id') === $partner->id)>{{ $partner->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="h-9 rounded-lg bg-brand-500 px-3 text-sm font-medium text-white hover:bg-brand-600">Grant</button>
        </div>
        <p class="mt-2 text-theme-xs text-gray-500 dark:text-gray-400">
            A granted user is staff: they leave every app-user count (Overview, Users, Insights). A partner admin is moved to the partner they manage.
        </p>
    </form>
</section>
