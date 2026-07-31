<?php

namespace App\Data\Dashboard;

use Carbon\CarbonImmutable;

final readonly class DashboardFilterData
{
    public function __construct(
        public CarbonImmutable $dateFrom,
        public CarbonImmutable $dateTo,
        public string $virtualMode,
    ) {}

    public static function fromFilamentFilters(array $filters): self
    {
        $timezone = (string) config('app.timezone');
        $today = CarbonImmutable::now($timezone);

        $dateFrom = filled($filters['startDate'] ?? null)
            ? CarbonImmutable::parse($filters['startDate'], $timezone)
            : $today;
        $dateTo = filled($filters['endDate'] ?? null)
            ? CarbonImmutable::parse($filters['endDate'], $timezone)
            : $today;

        return new self(
            dateFrom: $dateFrom->startOfDay(),
            dateTo: $dateTo->endOfDay(),
            virtualMode: $filters['virtualMode'] ?? 'exclude_virtual',
        );
    }

    public function toResponseArray(): array
    {
        return [
            'date_from' => $this->dateFrom->toDateString(),
            'date_to' => $this->dateTo->toDateString(),
            'virtual_mode' => $this->virtualMode,
        ];
    }
}
