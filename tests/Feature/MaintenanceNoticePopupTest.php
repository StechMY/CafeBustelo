<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\MaintenanceNoticeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MaintenanceNoticePopupTest extends TestCase
{
    use RefreshDatabase;

    public function test_notice_uses_only_the_selected_language_and_shows_once_per_login_session(): void
    {
        $this->enableNotice();
        config(['app.supported_locales' => [
            'en' => [],
            'zh_CN' => [],
            'vi' => [],
            'id' => [],
        ]]);
        $user = $this->createUser();

        $response = $this->actingAs($user)
            ->withSession([
                'account_policy_agreed_v1' => true,
                'locale' => 'vi',
            ])
            ->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSee('maintenance-notice-popup', false)
            ->assertSee('Từ 25/08/2026 19:30 theo giờ Malaysia')
            ->assertSee('25/08/2026 19:30 – 26/08/2026 00:30')
            ->assertDontSee('Starting at 25/08/2026 19:30')
            ->assertDontSee('马来西亚时间 25/08/2026 19:30');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('maintenance-notice-popup', false);
    }

    public function test_notice_waits_until_the_mandatory_account_policy_is_accepted(): void
    {
        $this->enableNotice();

        $this->actingAs($this->createUser())
            ->withSession(['locale' => 'en'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('account-policy-title', false)
            ->assertDontSee('maintenance-notice-popup', false);
    }

    public function test_notice_uses_the_indonesian_translation_when_selected(): void
    {
        $this->enableNotice();
        config(['app.supported_locales' => [
            'en' => [],
            'zh_CN' => [],
            'vi' => [],
            'id' => [],
        ]]);

        $this->actingAs($this->createUser())
            ->withSession([
                'account_policy_agreed_v1' => true,
                'locale' => 'id',
            ])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Mulai 25/08/2026 19:30 waktu Malaysia')
            ->assertDontSee('Starting at 25/08/2026 19:30');
    }

    public function test_notice_is_hidden_when_admin_switch_is_off(): void
    {
        Setting::setValue(MaintenanceNoticeService::SETTING_ENABLED, false);

        $this->actingAs($this->createUser())
            ->withSession([
                'account_policy_agreed_v1' => true,
                'locale' => 'en',
            ])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('maintenance-notice-popup', false);
    }

    public function test_a_new_admin_saved_revision_is_shown_once_again(): void
    {
        $this->enableNotice(revision: 'revision-one');
        $this->actingAs($this->createUser())
            ->withSession([
                'account_policy_agreed_v1' => true,
                'locale' => 'en',
            ])
            ->get(route('dashboard'))
            ->assertSee('maintenance-notice-popup', false);

        $this->get(route('dashboard'))
            ->assertDontSee('maintenance-notice-popup', false);

        Setting::setValue(MaintenanceNoticeService::SETTING_REVISION, 'revision-two');

        $this->get(route('dashboard'))
            ->assertSee('maintenance-notice-popup', false);
    }

    private function enableNotice(string $revision = 'test-revision'): void
    {
        Setting::setValue(MaintenanceNoticeService::SETTING_ENABLED, true);
        Setting::setValue(MaintenanceNoticeService::SETTING_STARTS_AT, '2026-08-25 19:30:00');
        Setting::setValue(MaintenanceNoticeService::SETTING_ENDS_AT, '2026-08-26 00:30:00');
        Setting::setValue(MaintenanceNoticeService::SETTING_REVISION, $revision);
    }

    private function createUser(): User
    {
        return User::query()->create([
            'name' => 'Maintenance Notice User',
            'username' => 'maintenance-notice-'.str()->random(8),
            'phone' => '84912345678',
            'password' => Hash::make('password'),
            'status' => 'active',
            'accumulated_recharge' => 0,
            'check_in_status' => false,
        ]);
    }
}
