<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Services\Admin\Insights;
use App\Services\Admin\Revenue;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Both tabs take ?partner= (a partner id) to narrow every number to that
 * partner's members; missing or unknown means every partner.
 */
class InsightsController extends Controller
{
    /**
     * The Training tab, for the range in ?range= (7, 30 or 90 days; anything
     * else falls back to 30).
     */
    public function training(Request $request): View
    {
        $days = Insights::range($request->query('range'));
        $partner = $this->partner($request);

        return view('admin.insights.training', [
            'days' => $days,
            'insights' => Insights::summary($days, $partner),
            ...$this->partnerFilter($partner),
        ]);
    }

    /**
     * The Revenue tab: current state of production subscriptions, no range.
     */
    public function revenue(Request $request): View
    {
        $partner = $this->partner($request);

        return view('admin.insights.revenue', [
            'revenue' => Revenue::summary($partner),
            ...$this->partnerFilter($partner),
        ]);
    }

    private function partner(Request $request): ?Partner
    {
        return Partner::query()->find($request->integer('partner') ?: null);
    }

    /**
     * @return array{partner: ?Partner, partners: \Illuminate\Support\Collection<int, Partner>}
     */
    private function partnerFilter(?Partner $partner): array
    {
        return [
            'partner' => $partner,
            'partners' => Partner::query()->orderBy('name')->get(['id', 'name']),
        ];
    }
}
