<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Support\Api\ApiFormatter;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class ContactController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success([
            'items' => Contact::query()
                ->where('status', Contact::STATUS_ACTIVE)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (Contact $contact): array => ApiFormatter::contact($contact))
                ->values(),
        ]);
    }
}
