<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfileQuickActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('SQLite PDO driver is unavailable.');
        }
        foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:'] as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
        parent::setUp();
    }

    #[DataProvider('downloadUrls')]
    public function test_profile_handles_missing_and_configured_download_links(?string $url): void
    {
        if ($url === null) Setting::query()->where('key', 'app_download_url')->delete();
        else Setting::setValue('app_download_url', $url);
        $user = User::query()->create([
            'name' => 'Profile User', 'username' => 'profile-user', 'password' => 'password',
            'referral_code' => 'PROFILE', 'status' => 'active', 'order_status' => true, 'accumulated_recharge' => 0,
        ]);
        $response = $this->actingAs($user)->get('/profile')->assertOk();
        if ($url) $response->assertSee('href="'.$url.'"', false);
        $response->assertSee('href="'.route('recharge.index').'"', false);
    }

    public static function downloadUrls(): array
    {
        return [[null], ['https://example.com/app']];
    }
}
