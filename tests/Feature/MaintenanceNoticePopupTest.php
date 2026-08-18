<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MaintenanceNoticePopupTest extends TestCase
{
    use RefreshDatabase;

    public function test_notice_uses_only_the_selected_language_and_shows_once_per_login_session(): void
    {
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
            ->assertSee('Tối nay, từ 19:00 theo giờ Malaysia')
            ->assertDontSee('Tonight, starting from 7:00 PM Malaysia Time')
            ->assertDontSee('今晚马来西亚时间');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('maintenance-notice-popup', false);
    }

    public function test_notice_waits_until_the_mandatory_account_policy_is_accepted(): void
    {
        $this->actingAs($this->createUser())
            ->withSession(['locale' => 'en'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('account-policy-title', false)
            ->assertDontSee('maintenance-notice-popup', false);
    }

    public function test_notice_uses_the_indonesian_translation_when_selected(): void
    {
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
            ->assertSee('Malam ini, mulai pukul 19.00 waktu Malaysia')
            ->assertDontSee('Tonight, starting from 7:00 PM Malaysia Time');
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
