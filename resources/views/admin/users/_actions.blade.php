{{-- The super admin's actions on this user. Each one confirms before it posts. --}}
@php
    $input = 'w-full rounded-lg border border-gray-300 bg-transparent px-3 py-1.5 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90';
    $primary = 'rounded-lg bg-brand-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-600';
    $secondary = 'rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5';
@endphp
<section class="{{ $card }}">
    <h2 class="{{ $heading }}">Actions</h2>

    @unless ($user->trashed())
        <form method="POST" action="{{ route('admin.users.complimentary-access.store', $user) }}" class="mt-3 space-y-2"
            onsubmit="return confirm('Give Complimentary Access until this date?')">
            @csrf
            <div class="text-theme-xs font-medium text-gray-500 dark:text-gray-400">{{ $user->hasComplimentaryAccess() ? 'Extend' : 'Grant' }} Complimentary Access</div>
            <input type="date" name="until" required min="{{ now()->addDay()->toDateString() }}" value="{{ old('until', $user->hasComplimentaryAccess() ? $user->grace_period_ends_at->toDateString() : null) }}" class="{{ $input }}" aria-label="Until">
            <input type="text" name="reason" required maxlength="1000" placeholder="Reason" value="{{ old('reason') }}" class="{{ $input }}" aria-label="Reason">
            <button type="submit" class="{{ $primary }} w-full">{{ $user->hasComplimentaryAccess() ? 'Extend' : 'Grant' }} Complimentary Access</button>
        </form>

        @if ($user->hasComplimentaryAccess())
            <form method="POST" action="{{ route('admin.users.complimentary-access.destroy', $user) }}" class="mt-2 space-y-2"
                onsubmit="return confirm('End Complimentary Access now?')">
                @csrf
                @method('DELETE')
                <input type="text" name="reason" maxlength="1000" placeholder="Reason (optional)" class="{{ $input }}" aria-label="Reason for ending">
                <button type="submit" class="{{ $secondary }} w-full">End Complimentary Access now</button>
            </form>
        @endif

        <form method="POST" action="{{ route('admin.users.partner.update', $user) }}" class="mt-4 space-y-2 border-t border-gray-100 pt-4 dark:border-gray-800"
            onsubmit="return confirm('Move this user to the chosen partner?')">
            @csrf
            @method('PATCH')
            <div class="text-theme-xs font-medium text-gray-500 dark:text-gray-400">Change partner</div>
            <select name="partner_id" required class="{{ $input }}" aria-label="New partner">
                <option value="">Choose a partner…</option>
                @foreach ($activePartners as $partner)
                    @continue($partner->id === $user->partner_id)
                    <option value="{{ $partner->id }}" @selected((int) old('partner_id') === $partner->id)>{{ $partner->name }}</option>
                @endforeach
            </select>
            <input type="text" name="reason" required maxlength="1000" placeholder="Reason" class="{{ $input }}" aria-label="Reason for the move">
            <button type="submit" class="{{ $secondary }} w-full">Change partner</button>
        </form>

        @unless ($user->hasVerifiedEmail())
            <form method="POST" action="{{ route('admin.users.verification.send', $user) }}" class="mt-4 border-t border-gray-100 pt-4 dark:border-gray-800"
                onsubmit="return confirm('Send the verification email again?')">
                @csrf
                <button type="submit" class="{{ $secondary }} w-full">Resend verification</button>
            </form>
        @endunless
    @endunless

    <div class="{{ $user->trashed() ? 'mt-3' : 'mt-4 border-t border-gray-100 pt-4 dark:border-gray-800' }}">
        @if ($user->trashed())
            <form method="POST" action="{{ route('admin.users.restore', $user) }}"
                onsubmit="return confirm('Restore this user? They can sign in again.')">
                @csrf
                <button type="submit" class="{{ $primary }} w-full">Restore user</button>
            </form>
        @else
            <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                onsubmit="return confirm('Deactivate this user? They are signed out everywhere and cannot sign in until restored.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="w-full rounded-lg border border-error-300 px-3 py-1.5 text-sm font-medium text-error-600 hover:bg-error-50 dark:border-error-700 dark:text-error-400 dark:hover:bg-error-500/10">Deactivate user</button>
            </form>
        @endif
    </div>
</section>
