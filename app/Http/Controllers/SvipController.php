<?php

namespace App\Http\Controllers;

use App\Models\SvipPackage;
use App\Services\SvipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class SvipController extends Controller
{
    public function purchase(
        Request $request,
        SvipPackage $package,
        SvipService $svipService,
    ): RedirectResponse {
        try {
            $data = $request->validate([
                'wallet_type' => ['required', 'string', 'in:recharge,withdrawable'],
            ]);

            $svipService->purchase(
                user: $request->user(),
                package: $package,
                walletType: $data['wallet_type'],
            );

            return back()->with('success', __('svip.purchase_success'));
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', __('svip.purchase_failed'));
        }
    }
}