<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\DownlineProductGroupResource;
use App\Services\DownlineProductReportService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DownlineProductController extends Controller
{
    public function index(Request $request, DownlineProductReportService $reportService): JsonResponse
    {
        $report = $reportService->reportForUser($request->user());
        $paginator = $reportService->paginateGroups(
            groups: $report['groups'],
            perPage: (int) $request->query('per_page', 12),
            page: (int) $request->query('page', 1),
            path: $request->url(),
            query: $request->query(),
        );

        return ApiResponse::success([
            'summary' => $report['summary'],
            'data' => DownlineProductGroupResource::collection($paginator->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
