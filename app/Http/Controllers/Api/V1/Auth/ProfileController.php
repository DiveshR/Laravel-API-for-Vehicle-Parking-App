<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Requests\Api\V1\ProfileUpdateRequest;

/**
 * @group Auth
 */
class ProfileController extends Controller
{
        public function show(Request $request)
    {
        return response()->json($request->user()->only('name', 'email'));
    }

        public function update(ProfileUpdateRequest $request)
    {
        $validatedData = [
            'name' => $request->name,
            'email' => $request->email,
        ];

        auth()->user()->update($validatedData);
 
        return response()->json($validatedData, Response::HTTP_ACCEPTED);
    }
}
