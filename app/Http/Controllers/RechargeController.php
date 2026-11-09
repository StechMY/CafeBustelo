<?php

namespace App\Http\Controllers;

use App\Models\RechargeMethod;
use App\Models\RechargeRequest;
use App\Models\Wallet;
use App\Support\RechargeAmountRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
        $rechargeMethods = RechargeMethod::query()
            ->where('status', RechargeMethod::STATUS_ACTIVE)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('recharge.create', [
            'user' => $request->user(),
            'rechargeMethods' => $rechargeMethods,
            'gatewayAvailable' => false, // QC168 is no longer offered for new deposits.
            'ydpayAvailable' => app(\App\Services\PaymentGateway\YdPayGateway::class)->isAvailable(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'amount' => RechargeAmountRules::rules(),
            'recharge_method_id' => [
                'required',
                'integer',
                Rule::exists('recharge_methods', 'id')->where('status', RechargeMethod::STATUS_ACTIVE),
            ],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'receipt' => [
                'required',
                'file',
                'image',
                'max:5120',
            ],
        ]);

        $rechargeMethod = RechargeMethod::query()
            ->whereKey($data['recharge_method_id'])
            ->where('status', RechargeMethod::STATUS_ACTIVE)
            ->firstOrFail();

        $receiptDisk = 'do_spaces';

        $receiptPath = $request->file('receipt')
            ->storePublicly('recharge-receipts/'.now()->format('Y/m'), $receiptDisk);

        $rechargeRequest = RechargeRequest::query()->create([
            'user_id' => $request->user()->id,
            'recharge_method_id' => $rechargeMethod->id,

            'amount' => $data['amount'],
            'deposit_method' => RechargeRequest::METHOD_MANUAL,
            'receipt_path' => $receiptPath,
            'receipt_disk' => $receiptDisk,

            // snapshot admin payment info
            'payment_method_name' => $rechargeMethod->name,
            'payment_bank_name' => $rechargeMethod->bank_name,
            'payment_account_name' => $rechargeMethod->account_name,
            'payment_account_no' => $rechargeMethod->account_no,

            // backward compatibility with existing admin screens
            'bank_name' => $rechargeMethod->bank_name,

            'reference_no' => $data['reference_no'] ?? null,
            'status' => 'pending',
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
