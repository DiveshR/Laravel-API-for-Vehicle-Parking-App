<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use App\Http\Requests\Api\V1\PasswordUpdateRequest;

/**
 * @group Auth
 */
class PasswordUpdateController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(PasswordUpdateRequest $request)
    {
                auth()->user()->update([
            'password' => bcrypt($request->input('password')),
        ]);

                return response()->json([
            'message' => 'Your password has been updated.',
        ], Response::HTTP_ACCEPTED);
    }
}
