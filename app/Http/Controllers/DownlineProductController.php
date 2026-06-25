<?php

namespace App\Http\Controllers;

use App\Services\DownlineProductReportService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class DownlineProductController extends Controller
{
    public function index(Request $request, DownlineProductReportService $reportService): View
    {
        $report = $reportService->reportForUser($request->user());
        $groupsPaginator = $reportService->paginateGroups(
            groups: $report['groups'],
            perPage: 12,
            page: LengthAwarePaginator::resolveCurrentPage(),
            path: $request->url(),
            query: $request->query(),
        );

        return view('downline-products.index', [
            'user' => $request->user(),
            'groups' => $groupsPaginator,
            'summary' => $report['summary'],
            'commissionRates' => $report['commission_rates'],
        ]);
    }
}
