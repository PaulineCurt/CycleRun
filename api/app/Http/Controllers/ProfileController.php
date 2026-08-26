<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'birth_date' => ['sometimes', 'nullable', 'date'],
            'typical_cycle_length' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:255'],
            'typical_menses_length' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:255'],
        ]);

        $request->user()->update($data);

        return response()->json($request->user());
    }
}
