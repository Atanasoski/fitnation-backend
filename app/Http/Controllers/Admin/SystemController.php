<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Services\Admin\Admins;
use App\Services\System\FailedJobs;
use App\Services\System\Fleet;
use App\Webhooks\RevenueCat\FailedWebhookCalls;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SystemController extends Controller
{
    public function __construct(private FailedJobs $failedJobs) {}

    public function index(): View
    {
        return view('admin.system.index', [
            'failedJobs' => $this->failedJobs->latest(),
            'failedJobCount' => $this->failedJobs->count(),
            'failedWebhooks' => FailedWebhookCalls::query()->latest('id')->paginate(25, ['*'], 'webhooks_page'),
            'fleet' => Fleet::summary(),
            'admins' => Admins::list(),
            'partners' => Partner::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function retryJob(string $id): RedirectResponse
    {
        abort_unless($this->failedJobs->exists($id), 404);

        $this->failedJobs->retry($id);

        return redirect()->route('admin.system')->with('success', 'The job is back on the queue.');
    }

    public function forgetJob(string $id): RedirectResponse
    {
        abort_unless($this->failedJobs->exists($id), 404);

        $this->failedJobs->forget($id);

        return redirect()->route('admin.system')->with('success', 'The failed job was deleted.');
    }

    public function replayWebhook(int $id): RedirectResponse
    {
        FailedWebhookCalls::replay(FailedWebhookCalls::query()->findOrFail($id));

        return redirect()->route('admin.system')->with('success', "Webhook call #{$id} was replayed.");
    }

    public function replayAllWebhooks(): RedirectResponse
    {
        $calls = FailedWebhookCalls::query()->get();
        $calls->each(fn ($call) => FailedWebhookCalls::replay($call));

        return redirect()->route('admin.system')
            ->with('success', "{$calls->count()} webhook call(s) were replayed.");
    }
}
