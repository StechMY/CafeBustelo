<?php

namespace App\Http\Controllers;

use App\Models\BankAccountReviewRequest;
use App\Support\FilamentSupportScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BankAccountReviewDocumentController extends Controller
{
    public function __invoke(
        Request $request,
        BankAccountReviewRequest $bankAccountReviewRequest,
        int $photo,
    ): StreamedResponse {
        $admin = auth('admin')->user();

        abort_unless($admin && Gate::forUser($admin)->allows('view', $bankAccountReviewRequest), 403);
        abort_unless(FilamentSupportScope::canAccessUserId($bankAccountReviewRequest->user_id), 403);

        $path = ($bankAccountReviewRequest->id_photo_paths ?? [])[$photo] ?? null;

        abort_unless(is_string($path) && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
