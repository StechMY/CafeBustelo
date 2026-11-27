<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\WithdrawRequest;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class YdPayReturnEvidenceController extends Controller
{
    public function __invoke(WithdrawRequest $withdrawRequest): Response
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser && $admin->status === 'active', 403);
        Gate::forUser($admin)->authorize('confirmYdPayReturn', $withdrawRequest);

        $proof = $withdrawRequest->ydpayPayoutReturnEvidence()->whereNotNull('document_sha256')->firstOrFail();
        $extension = match ($proof->document_mime) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => abort(404),
        };
        $contents = base64_decode($proof->document_base64, true);
        abort_unless($contents !== false && hash_equals($proof->document_sha256, hash('sha256', $contents)), 404);

        return response($contents, 200, [
            'Content-Type' => $proof->document_mime,
            'Content-Disposition' => 'attachment; filename="ydpay-return-'.$withdrawRequest->id.'.'.$extension.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
