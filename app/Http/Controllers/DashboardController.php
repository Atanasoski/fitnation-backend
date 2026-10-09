<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class DashboardController extends Controller
{
    /**
     * The web panel is for super admins: they land on Overview. Anyone else
     * uses the mobile app.
     */
    public function index(): RedirectResponse
    {
        abort_unless(auth()->user()->hasRole('admin'), 403, 'This portal is for gym administrators only. Please use the Fit Nation mobile app.');

        return redirect()->route('admin.overview');
    }
}
