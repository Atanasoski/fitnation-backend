<?php

namespace App\Http\Controllers\Api;

use App\Enums\JoinPromptDismissalKind;
use App\Http\Controllers\Controller;
use App\Models\JoinPromptDismissal;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class JoinPromptDismissalController extends Controller
{
    /**
     * Record a Join Prompt dismissal for the caller (issue #89): `declined`
     * silences the Partner's prompt permanently, `snoozed` for 24 hours from
     * now. One row per customer×Partner, upserted on repeat — a fresh snooze
     * always resets the window, a decline overwrites a snooze, and a snooze
     * for an already-declined Partner is accepted and ignored: declined is
     * terminal (GLOSSARY: Join Prompt; ADR 0006).
     */
    public function store(Request $request): Response
    {
        $validated = $request->validate([
            'partner_id' => ['required', 'integer', 'exists:partners,id'],
            'kind' => ['required', Rule::enum(JoinPromptDismissalKind::class)],
        ]);

        JoinPromptDismissal::record(
            $request->user()->id,
            (int) $validated['partner_id'],
            JoinPromptDismissalKind::from($validated['kind']),
        );

        return response()->noContent();
    }
}
