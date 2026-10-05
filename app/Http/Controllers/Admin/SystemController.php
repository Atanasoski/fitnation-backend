<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class SystemController extends Controller
{
    public function index(): View
    {
        return view('admin.system.index');
    }
}
