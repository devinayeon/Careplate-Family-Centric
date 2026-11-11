<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/** The two AI screens of the user area. They only render; every AI call goes to /ai/v1/* from the browser. */
class AiPageController extends Controller
{
    public function scan(): Response
    {
        return Inertia::render('User/Pindai', [
            'aiEnabled' => (bool) config('ai.enabled'),
            'scanLimitPerDay' => (int) config('ai.quotas.scan.per_day'),
        ]);
    }

    public function chat(): Response
    {
        return Inertia::render('User/ChatGizi', [
            'aiEnabled' => (bool) config('ai.enabled'),
        ]);
    }
}
