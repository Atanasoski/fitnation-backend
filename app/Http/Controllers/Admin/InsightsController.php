<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\Insights;
use App\Services\Admin\Revenue;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InsightsController extends Controller
{
    /**
     * The Training tab, for the range in ?range= (7, 30 or 90 days; anything
     * else falls back to 30).
     */
    public function training(Request $request): View
    {
        $days = Insights::range($request->query('range'));

        return view('admin.insights.training', ['days' => $days, 'insights' => Insights::summary($days)]);
    }

    /**
     * The Revenue tab: current state of production subscriptions, no range.
     */
    public function revenue(): View
    {
        return view('admin.insights.revenue', ['revenue' => Revenue::summary()]);
    }
}
