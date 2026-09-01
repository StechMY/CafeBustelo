<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VipLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class VipLevelReachedAtTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_the_first_time_a_member_reaches_every_configured_level(): void
    {
        foreach (range(2, 9) as $level) {
            VipLevel::query()->create([
                'level' => $level,
                'name' => "LV{$level}",
                'required_accumulated_recharge' => ($level - 1) * 100,
                'required_active_team_size' => 1,
            ]);
        }

        $member = $this->createUser('member', ['status' => 'active']);
        $this->createUser('active-downline', [
            'status' => 'active',
            'referrer_id' => $member->id,
        ]);

        Carbon::setTestNow('2026-09-01 10:00:00');
        $member->increment('accumulated_recharge', 100);

        $achievements = $member->vipLevelAchievements()->get()->keyBy('level');
        $this->assertCount(1, $achievements);
        $this->assertSame('2026-09-01 10:00:00', $achievements[2]->reached_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow('2026-09-01 11:00:00');
        $member->increment('accumulated_recharge', 700);

        $achievements = $member->vipLevelAchievements()->get()->keyBy('level');
        $this->assertCount(8, $achievements);
        $this->assertSame('2026-09-01 10:00:00', $achievements[2]->reached_at->format('Y-m-d H:i:s'));

        foreach (range(3, 9) as $level) {
            $this->assertSame('2026-09-01 11:00:00', $achievements[$level]->reached_at->format('Y-m-d H:i:s'));
        }

        $teamMember = $this->createUser('team-member', [
            'status' => 'active',
            'accumulated_recharge' => 800,
        ]);
        $inactiveDownline = $this->createUser('inactive-downline', [
            'status' => 'inactive',
            'referrer_id' => $teamMember->id,
        ]);

        Carbon::setTestNow('2026-09-01 12:00:00');
        $inactiveDownline->forceFill(['status' => 'active'])->save();

        $teamAchievements = $teamMember->vipLevelAchievements()->get();
        $this->assertSame(range(2, 9), $teamAchievements->pluck('level')->all());
        $this->assertTrue($teamAchievements->every(
            fn($achievement): bool => $achievement->reached_at->format('Y-m-d H:i:s') === '2026-09-01 12:00:00'
        ));

        $existingMember = User::withoutEvents(fn(): User => $this->createUser('existing-member', [
            'status' => 'active',
            'accumulated_recharge' => 800,
        ]));
        User::withoutEvents(fn(): User => $this->createUser('existing-downline', [
            'status' => 'active',
            'referrer_id' => $existingMember->id,
        ]));

        Carbon::setTestNow('2026-09-01 13:00:00');
        $existingMember->increment('accumulated_recharge', 1);

        $this->assertCount(0, $existingMember->vipLevelAchievements);
    }

    private function createUser(string $username, array $attributes = []): User
    {
        return User::query()->create([
            'name' => $username,
            'username' => $username,
            'password' => Hash::make('password'),
            ...$attributes,
        ]);
    }
}
