<?php

namespace App\Http\Controllers\Director;

use App\Http\Controllers\Controller;
use App\Models\DeclarationNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(): View
    {
        return view('director.notifications', [
            'notifications' => DeclarationNotification::latest('id')->paginate(20),
        ]);
    }
}
