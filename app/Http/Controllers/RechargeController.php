<?php

namespace App\Http\Controllers;

use App\Models\RechargeRequest;
use App\Models\Wallet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class RechargeController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $rechargeWallet = Wallet::query()
            ->where('user_id', $user->id)
            ->where('type', Wallet::TYPE_RECHARGE)
            ->first();

        $requests = RechargeRequest::query()
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(10);

        return view('recharge.index', [
            'user' => $user,
            'rechargeBalance' => $rechargeWallet?->balance ?? 0,
            'requests' => $requests,
        ]);
    }

    public function create(Request $request): View
    {
        return view('recharge.create', [
            'user' => $request->user(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:99999999'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'receipt' => [
                'required',
                'file',
                'image',
                'max:5120',
            ],
        ]);

        $receiptPath = $request->file('receipt')
            ->storePublicly('recharge-receipts/' . now()->format('Y/m'), 'do_spaces');

        $rechargeRequest = RechargeRequest::query()->create([
            'user_id' => $request->user()->id,
            'amount' => $data['amount'],
            'receipt_path' => $receiptPath,
            'receipt_disk' => 'do_spaces',
            'bank_name' => $data['bank_name'] ?? null,
            'reference_no' => $data['reference_no'] ?? null,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        return redirect()
            ->route('recharge.show', $rechargeRequest)
            ->with('success', __('recharge.submitted_successfully'));
    }

    public function show(Request $request, RechargeRequest $rechargeRequest): View
    {
        abort_unless((int) $rechargeRequest->user_id === (int) $request->user()->id, 403);

        return view('recharge.show', [
            'rechargeRequest' => $rechargeRequest,
        ]);
    }
}