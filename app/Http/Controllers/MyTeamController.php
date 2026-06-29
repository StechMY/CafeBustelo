<?php

namespace App\Http\Controllers;

use App\Services\MyTeamReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MyTeamController extends Controller
{
    public function index(Request $request, MyTeamReportService $myTeamReportService): View
    {
        $user = $request->user();
        $overview = $myTeamReportService->overview($user);

        return view('my-team.index', [
            'user' => $user,
            'inviteUrl' => $overview['invite_url'],
            'stats' => $overview['stats'],
            'commissionRates' => $overview['commission_rates'],
        ]);
    }

    public function level(Request $request, string $level, MyTeamReportService $myTeamReportService): View
    {
        $user = $request->user();
        $status = $request->query('status', 'all');
        $report = $myTeamReportService->level(
            user: $user,
            level: $level,
            status: $status,
            page: (int) $request->query('page', 1),
            path: route('my-team.level', $level),
            query: $request->query(),
        );

        return view('my-team.level', [
            'user' => $user,
            'level' => $report['level'],
            'levelNumber' => $report['level_number'],
            'levelLabel' => $report['level_label'],
            'status' => $status,
            'downlineUsers' => $report['downline_users'],
            'commissionRate' => $report['commission_rate'],
        ]);
    }
}
