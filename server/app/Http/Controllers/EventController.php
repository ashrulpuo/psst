<?php

namespace App\Http\Controllers;

use App\Growth\GrowthEvents;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    /** Count a client-side growth event. Only the name is read from the request; nothing about the caller is stored. */
    public function store(Request $request): Response
    {
        $name = $request->validate([
            'name' => ['required', 'string', Rule::in(GrowthEvents::CLIENT_NAMES)],
        ])['name'];

        GrowthEvents::record($name);

        return response()->noContent();
    }
}
