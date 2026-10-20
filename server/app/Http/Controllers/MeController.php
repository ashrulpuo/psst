<?php

namespace App\Http\Controllers;

use App\Actions\Account\DeleteAccount;
use App\Http\Requests\DeleteAccountRequest;
use App\Http\Requests\SetUsernameRequest;
use App\Http\Requests\UpdatePreferencesRequest;
use App\Http\Resources\MeResource;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class MeController extends Controller
{
    public function show(Request $request): MeResource
    {
        return new MeResource($request->user());
    }

    /**
     * Claim a handle. One-time on purpose: shared links must not break or be sniped after the fact.
     */
    public function setUsername(SetUsernameRequest $request): MeResource
    {
        $user = $request->user();

        abort_if($user->username !== null, 409, 'Your username is already set.');

        try {
            $user->forceFill(['username' => $request->validated('username')])->save();
        } catch (UniqueConstraintViolationException) {
            // Lost a race with another claimant between validation and write.
            throw ValidationException::withMessages(['username' => 'That username is taken. Try another.']);
        }

        return new MeResource($user);
    }

    public function updatePreferences(UpdatePreferencesRequest $request): MeResource
    {
        $user = $request->user();
        $user->forceFill(['push_previews' => $request->validated('push_previews')])->save();

        return new MeResource($user);
    }

    /**
     * Erase the account for good. Admins must be demoted first: their audit trail must stay attributable.
     */
    public function destroy(DeleteAccountRequest $request, DeleteAccount $deleteAccount): Response
    {
        $user = $request->user();

        abort_if($user->isAdmin(), 403, 'Admin accounts cannot be deleted here. Ask another admin to change your role first.');

        $deleteAccount->delete($user);

        // The session (cookie clients) is invalid now; tokens were deleted with the user.
        if ($request->hasSession()) {
            $request->session()->invalidate();
        }

        return response()->noContent();
    }
}
