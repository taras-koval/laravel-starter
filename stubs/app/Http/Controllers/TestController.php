<?php

namespace App\Http\Controllers;

use DateTimeZone;
use Illuminate\Http\JsonResponse;

class TestController extends Controller
{
    public function index(): JsonResponse
    {
        $timezones = collect(DateTimeZone::listIdentifiers())
            ->groupBy(fn ($tz) => explode('/', $tz)[0])
            ->map(fn ($group) => $group->values())
            ->toArray();

        return response()->json($timezones);
    }
}
