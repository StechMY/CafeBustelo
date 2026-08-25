<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Models\AdminUser;
use App\Models\Setting;
use App\Services\MaintenanceNoticeService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class MaintenanceNoticeAdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_can_save_maintenance_notice_settings(): void
    {
        $admin = AdminUser::query()->create([
            'username' => 'maintenance-admin',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin, 'admin');

        Livewire::test(Dashboard::class)
            ->assertActionExists('maintenanceNoticeSettings')
            ->callAction('maintenanceNoticeSettings', [
                'enabled' => true,
                'starts_at' => '2026-08-25 19:00:00',
                'ends_at' => '2026-08-26 00:00:00',
            ])
            ->assertHasNoActionErrors();

        $this->assertTrue(Setting::bool(MaintenanceNoticeService::SETTING_ENABLED));
        $this->assertSame(
            '2026-08-25 19:00',
            Setting::getValue(MaintenanceNoticeService::SETTING_STARTS_AT)
        );
        $this->assertSame(
            '2026-08-26 00:00',
            Setting::getValue(MaintenanceNoticeService::SETTING_ENDS_AT)
        );
        $this->assertNotEmpty(Setting::getValue(MaintenanceNoticeService::SETTING_REVISION));
    }
}
