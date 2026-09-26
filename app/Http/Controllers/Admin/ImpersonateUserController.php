<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonateUserController extends Controller
{
    public function __invoke(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->hasValidRelativeSignature(), 403);

        $admin = Auth::guard('admin')->user();

        abort_unless($admin, 403);

        $nonce = $request->query('nonce');
        $sessionNonce = $request->session()->get('admin_impersonation_nonce.'.$admin->id);
        abort_unless(
            is_string($nonce) && is_string($sessionNonce) && $sessionNonce !== ''
            && hash_equals($sessionNonce, $nonce),
            403,
        );

        if ($user->status === 'suspended') {
            return redirect()
                ->back()
                ->with('error', __('filament.cannot_login_suspended_user'));
        }

        session([
            'impersonated_by_admin_id' => $admin->id,
            'impersonated_by_admin_class' => get_class($admin),
            'impersonated_user_id' => $user->id,
            'impersonated_at' => now()->toDateTimeString(),
        ]);

        Auth::guard('web')->login($user);

        $request->session()->regenerate();

        return redirect()
            ->route('dashboard')
            ->with('success', __('filament.logged_in_as_user', [
                'username' => $user->username,
            ]));
    }
}
