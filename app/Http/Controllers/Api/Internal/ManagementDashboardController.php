<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Internal\ManagementDashboardRequest;
use App\Services\Dashboard\CafeDashboardMetricsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class ManagementDashboardController extends Controller
{
    public function __invoke(
        ManagementDashboardRequest $request,
        CafeDashboardMetricsService $metricsService,
    ): JsonResponse {
        $filters = $request->filters();
        $metrics = $metricsService->dashboard($filters);
        $timezone = (string) config('app.timezone');

        $payload = [
            'schema_version' => '1.0',
            'platform' => [
                'code' => config('dashboard.platform.code'),
                'name' => config('dashboard.platform.name'),
            ],
            'generated_at' => CarbonImmutable::now($timezone)->toIso8601String(),
            'timezone' => $timezone,
            'currency' => [
                'code' => config('dashboard.currency_code'),
                'symbol' => config('app.currency'),
            ],
            'filters' => $filters->toResponseArray(),
            'common' => $metrics['common'],
            'sections' => $metrics['sections'],
        ];

        return response()->json(
            $payload,
            200,
            ['Cache-Control' => 'private, no-store'],
            JSON_PRESERVE_ZERO_FRACTION,
        );
    }
}
