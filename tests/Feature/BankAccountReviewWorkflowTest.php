<?php

namespace Tests\Feature;

use App\Filament\Resources\BankAccountReviewRequests\BankAccountReviewRequestResource;
use App\Models\AdminUser;
use App\Models\BankAccountReviewRequest;
use App\Models\User;
use App\Services\BankAccountReviewService;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BankAccountReviewWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_bank_details_are_pending_until_admin_approval(): void
    {
        Storage::fake('local');
        $user = $this->createUser('review-one');
        $this->mockOtpVerification();

        $response = $this->actingAs($user)->post(route('bank-account.store'), [
            'bank_account_name' => 'Nguyen Hoa',
            'bank_name' => 'TPBank',
            'bank_account_no' => '1234567890',
            'phone' => '912345678',
            'otp_code' => '123456',
            'id_photos' => [
                UploadedFile::fake()->image('front.jpg'),
                UploadedFile::fake()->image('back.jpg'),
            ],
        ]);

        $response->assertRedirect(route('bank-account.create'));
        $this->assertDatabaseMissing('user_bank_accounts', ['user_id' => $user->id]);

        $review = BankAccountReviewRequest::query()->whereBelongsTo($user)->firstOrFail();

        $this->assertTrue($review->isPending());
        $this->assertCount(2, $review->id_photo_paths);
        Storage::disk('local')->assertExists($review->id_photo_paths);
    }

    public function test_approval_binds_the_account_and_allows_duplicate_account_holder_names(): void
    {
        Storage::fake('local');
        $service = app(BankAccountReviewService::class);

        $first = $service->submit(
            $this->createUser('review-first'),
            $this->bankData('1111111111'),
            [UploadedFile::fake()->image('first.jpg')],
        );
        $second = $service->submit(
            $this->createUser('review-second'),
            $this->bankData('2222222222'),
            [UploadedFile::fake()->image('second.jpg')],
        );

        $firstAccount = $service->approve($first, null);
        $secondAccount = $service->approve($second, null);

        $this->assertSame('Nguyen Hoa', $firstAccount->bank_account_name);
        $this->assertSame('Nguyen Hoa', $secondAccount->bank_account_name);
        $this->assertDatabaseCount('user_bank_accounts', 2);
    }

    public function test_rejected_request_can_be_updated_and_resubmitted(): void
    {
        Storage::fake('local');
        $user = $this->createUser('review-resubmit');
        $service = app(BankAccountReviewService::class);

        $review = $service->submit(
            $user,
            $this->bankData('3333333333'),
            [UploadedFile::fake()->image('original.jpg')],
        );
        $originalPhotoPaths = $review->id_photo_paths;

        $service->reject($review, null, 'ID image is unclear.');

        $resubmitted = $service->submit(
            $user,
            $this->bankData('4444444444'),
            [UploadedFile::fake()->image('replacement.jpg')],
        );

        $this->assertTrue($resubmitted->is($review));
        $this->assertTrue($resubmitted->isPending());
        $this->assertSame('4444444444', $resubmitted->bank_account_no);
        $this->assertNull($resubmitted->admin_remark);
        Storage::disk('local')->assertMissing($originalPhotoPaths[0]);
        Storage::disk('local')->assertExists($resubmitted->id_photo_paths[0]);
    }

    public function test_pending_review_is_shown_instead_of_withdraw_form(): void
    {
        Storage::fake('local');
        $user = $this->createUser('review-withdraw');

        app(BankAccountReviewService::class)->submit(
            $user,
            $this->bankData('5555555555'),
            [UploadedFile::fake()->image('pending.jpg')],
        );

        $this->actingAs($user)
            ->get(route('withdraw.create'))
            ->assertRedirect(route('bank-account.create'))
            ->assertSessionHas('warning', __('bank_account.under_review'));
    }

    public function test_only_an_authorized_admin_can_view_private_id_photos(): void
    {
        Storage::fake('local');
        $review = app(BankAccountReviewService::class)->submit(
            $this->createUser('review-photo'),
            $this->bankData('6666666666'),
            [UploadedFile::fake()->image('private.jpg')],
        );

        $this->get($review->idPhotoUrl(0))->assertRedirect();

        $role = Role::query()->firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'admin',
        ]);
        $admin = AdminUser::query()->create([
            'username' => 'review-admin',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $admin->assignRole($role);

        $response = $this->actingAs($admin, 'admin')
            ->get($review->idPhotoUrl(0));

        $response->assertOk();
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        $this->actingAs($admin, 'admin')
            ->get(BankAccountReviewRequestResource::getUrl('view', ['record' => $review]))
            ->assertOk()
            ->assertSee('Nguyen Hoa')
            ->assertSee(__('filament.approve'))
            ->assertSee(__('filament.reject'));

        $this->actingAs($admin, 'admin')
            ->get(BankAccountReviewRequestResource::getUrl('index'))
            ->assertOk()
            ->assertSee(__('filament.approve'))
            ->assertSee(__('filament.reject'));
    }

    private function mockOtpVerification(): void
    {
        $this->mock(OtpService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('normalizePhone')
                ->andReturn('84912345678');
            $mock->shouldReceive('verify')
                ->once()
                ->with('84912345678', 'bank_account', '123456');
        });
    }

    private function createUser(string $username): User
    {
        return User::query()->create([
            'name' => 'Review User',
            'username' => $username,
            'phone' => '84912345678',
            'password' => Hash::make('password'),
            'status' => 'active',
            'withdraw_status' => true,
        ]);
    }

    private function bankData(string $accountNo): array
    {
        return [
            'bank_account_name' => 'Nguyen Hoa',
            'bank_name' => 'TPBank',
            'bank_account_no' => $accountNo,
            'phone' => '84912345678',
            'phone_verified_at' => now(),
            'meta' => ['source' => 'test'],
        ];
    }
}
