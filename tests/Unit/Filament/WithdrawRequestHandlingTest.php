<?php

namespace Tests\Unit\Filament;

use App\Filament\Resources\WithdrawRequests\Pages\ListWithdrawRequests;
use App\Filament\Resources\WithdrawRequests\Tables\WithdrawRequestsTable;
use App\Models\WithdrawRequest;
use App\Services\PaymentGateway\Qc168Gateway;
use App\Services\Settings\Qc168Settings;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Tests\TestCase;

class WithdrawRequestHandlingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('en');
        Carbon::setTestNow('2026-10-02 12:00:00');
        $this->mock(Qc168Gateway::class)->shouldReceive('isWithdrawalAvailable')->andReturn(false);
        $this->mock(Qc168Settings::class)->shouldReceive('withdrawReleaseHours')->andReturn(24);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[DataProvider('handlingCases')]
    public function test_handling_distinguishes_review_submission_and_gateway_results(array $attributes, string $label): void
    {
        $column = $this->handlingColumn($attributes);

        $this->assertStringContainsString($label, $column->getTooltip());
    }

    public static function handlingCases(): array
    {
        return [
            'pending review' => [['status' => 'pending'], 'Not handled · Awaiting review'],
            'review rejected' => [['status' => 'rejected'], 'Handled · Rejected'],
            'future schedule' => [['payout_status' => 'waiting_release', 'gateway_release_at' => '2026-10-02 15:00:00'], 'Approved · Scheduled'],
            'schedule due now' => [['payout_status' => 'waiting_release', 'gateway_release_at' => '2026-10-02 12:00:00'], 'Not submitted · Awaiting dispatch'],
            'overdue schedule' => [['payout_status' => 'waiting_release', 'gateway_release_at' => '2026-10-01 12:00:00'], 'Not submitted · Awaiting dispatch'],
            'claimed but uncertain' => [['payout_status' => 'submitted', 'gateway_payment_status' => 'create_pending'], 'Attempted · Confirmation needed'],
            'submission without acknowledgement' => [['payout_status' => 'submitted'], 'Attempted · Confirmation needed'],
            'gateway accepted none' => [['payout_status' => 'submitted', 'gateway_payment_status' => 'none'], 'Submitted · Awaiting gateway'],
            'gateway pending' => [['payout_status' => 'pending'], 'Submitted · Awaiting gateway'],
            'gateway preparing' => [['payout_status' => 'preparing'], 'Submitted · Awaiting gateway'],
            'gateway processing' => [['payout_status' => 'processing'], 'Submitted · Awaiting gateway'],
            'failed retry with old submitted timestamp' => [['payout_status' => 'create_failed', 'gateway_submitted_at' => '2026-10-01 10:00:00'], 'Attempted · Submission failed'],
            'payout failed' => [['payout_status' => 'failed'], 'Handled · Payout unsuccessful'],
            'payout rejected' => [['payout_status' => 'rejected'], 'Handled · Payout unsuccessful'],
            'payout expired' => [['payout_status' => 'overdue'], 'Handled · Payout unsuccessful'],
            'paid with stale error' => [['payout_status' => 'completed', 'gateway_fail_reason' => 'Old timeout'], 'Handled · Paid'],
            'cancelled with stale uncertain status' => [['payout_status' => 'cancelled', 'gateway_payment_status' => 'create_pending'], 'Handled · Cancelled / Refunded'],
            'payment after refund' => [['payout_status' => 'refund_review', 'gateway_payment_status' => 'completed'], 'Financial review required'],
            'manual completion' => [['gateway_provider' => null, 'approval_mode' => 'manual', 'payout_status' => 'completed'], 'Handled · Manual approval'],
            'legacy manual approval' => [['gateway_provider' => null], 'Handled · Manual approval'],
            'unknown status' => [['payout_status' => 'unrecognized'], 'Check payout records'],
        ];
    }

    public function test_processing_explanation_includes_submission_time_without_claiming_gateway_failure(): void
    {
        $column = $this->handlingColumn([
            'payout_status' => 'processing',
            'gateway_payment_status' => 'processing',
            'gateway_submitted_at' => '2026-10-02 10:35:04',
        ]);

        $description = $column->getTooltip();
        $this->assertStringContainsString('Gateway Submitted At: 02 Oct 2026 10:35', $description);
        $this->assertStringContainsString('not yet returned a final result', $description);
        $this->assertStringContainsString('does not establish a gateway fault', $description);
    }

    public function test_submission_failure_shows_recorded_reason_without_reusing_an_old_submission_time(): void
    {
        $column = $this->handlingColumn([
            'payout_status' => 'create_failed',
            'gateway_fail_reason' => 'QC168 rejected the request: 商户余额不足',
            'gateway_submitted_at' => '2026-10-01 10:35:04',
        ]);

        $this->assertStringContainsString('商户余额不足', $column->getTooltip());
        $this->assertStringNotContainsString('Gateway Submitted At:', $column->getTooltip());
    }

    public function test_completed_payout_does_not_show_stale_error_as_an_active_problem(): void
    {
        $column = $this->handlingColumn(['payout_status' => 'completed', 'gateway_fail_reason' => 'Old timeout']);

        $this->assertStringNotContainsString('Old timeout', $column->getTooltip());
    }

    public function test_compact_columns_keep_bank_after_amount_and_schedule_sortable(): void
    {
        $table = WithdrawRequestsTable::configure(Table::make(new ListWithdrawRequests));

        $this->assertNull($table->getColumn('platform_handling'));
        $this->assertNull($table->getColumn('gateway_last_synced_at'));
        $this->assertSame([
            'user.username', 'amount', 'bank_name', 'withdrawal_summary_status',
            'gateway_release_at', 'created_at',
        ], array_slice(array_keys($table->getColumns()), 0, 6));
        $this->assertTrue($table->getColumn('gateway_release_at')->isSortable());
        $this->assertFalse($table->getColumn('gateway_release_at')->isToggleable());
    }

    private function handlingColumn(array $attributes): TextColumn
    {
        $record = new WithdrawRequest(array_merge([
            'status' => 'approved',
            'gateway_provider' => 'qc168',
        ], $attributes));
        $record->id = 1;
        $page = new ListWithdrawRequests;
        $table = WithdrawRequestsTable::configure(Table::make($page));
        (new ReflectionProperty($page, 'table'))->setValue($page, $table);
        $column = $table->getColumn('withdrawal_summary_status');
        $this->assertInstanceOf(TextColumn::class, $column);

        return $column->record($record);
    }
}
