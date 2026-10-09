<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\Overview;
use Illuminate\View\View;

class OverviewController extends Controller
{
    public function index(): View
    {
        return view('admin.overview', ['overview' => Overview::summary()]);
    }
}
