<?php

namespace App\Http\Requests\Api\Internal;

use App\Data\Dashboard\DashboardFilterData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ManagementDashboardRequest extends FormRequest
{
    private const MAX_RANGE_DAYS = 366;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $today = CarbonImmutable::now((string) config('app.timezone'))->toDateString();

        $this->merge([
            'date_from' => filled($this->input('date_from')) ? $this->input('date_from') : $today,
            'date_to' => filled($this->input('date_to')) ? $this->input('date_to') : $today,
            'virtual_mode' => filled($this->input('virtual_mode'))
                ? $this->input('virtual_mode')
                : 'exclude_virtual',
        ]);
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'virtual_mode' => [
                'nullable',
                Rule::in(['include_all', 'exclude_virtual', 'only_virtual']),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['date_from', 'date_to'])) {
                    return;
                }

                $timezone = (string) config('app.timezone');
                $from = CarbonImmutable::parse($this->string('date_from')->toString(), $timezone);
                $to = CarbonImmutable::parse($this->string('date_to')->toString(), $timezone);
                $inclusiveDays = (int) $from->diffInDays($to) + 1;

                if ($inclusiveDays > self::MAX_RANGE_DAYS) {
                    $validator->errors()->add(
                        'date_to',
                        'The selected date range may not exceed '.self::MAX_RANGE_DAYS.' days.',
                    );
                }
            },
        ];
    }

    public function filters(): DashboardFilterData
    {
        $timezone = (string) config('app.timezone');

        return new DashboardFilterData(
            dateFrom: CarbonImmutable::parse($this->validated('date_from'), $timezone)->startOfDay(),
            dateTo: CarbonImmutable::parse($this->validated('date_to'), $timezone)->endOfDay(),
            virtualMode: $this->validated('virtual_mode'),
        );
    }
}
