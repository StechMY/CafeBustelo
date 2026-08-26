<?php

namespace App\Http\Controllers\PaymentGateway;

use App\Http\Controllers\Controller;
use App\Services\PaymentGateway\Qc168WithdrawalCallbackService;
use Illuminate\Http\Request;
use JsonException;
use Throwable;

class Qc168WithdrawalCallbackController extends Controller
{
    public function __invoke(Request $request, Qc168WithdrawalCallbackService $callbackService)
    {
        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return response('invalid json', 400)->header('Content-Type', 'text/plain');
        }

        if (! is_array($payload)) {
            return response('invalid payload', 400)->header('Content-Type', 'text/plain');
        }

        try {
            $outcome = $callbackService->handleCallback(
                $payload,
                $request->header('Oc-Signature'),
            );
        } catch (Throwable $exception) {
            report($exception);

            return response('error', 500)->header('Content-Type', 'text/plain');
        }

        if ($outcome['accepted']) {
            return response('success', 200)->header('Content-Type', 'text/plain');
        }

        return response($outcome['result'], $outcome['httpStatus'])
            ->header('Content-Type', 'text/plain');
    }
}
