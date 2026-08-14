<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\UserProducts\UserProductResource;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\UserProductsRelationManager;
use App\Models\AdminUser;
use App\Models\Product;
use App\Models\User;
use App\Models\UserProduct;
use App\Policies\UserProductPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PDO;
use ReflectionClass;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserProductResourceTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    protected function setUp(): void
    {
        $connection = 'sqlite';
        $database = ':memory:';

        if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $database = (string) getenv('USER_PRODUCT_RESOURCE_TEST_DATABASE');

            if ($database === '') {
                $this->markTestSkipped(
                    'SQLite PDO is unavailable and USER_PRODUCT_RESOURCE_TEST_DATABASE is not set.',
                );
            }

            $connection = 'mysql';
        }

        putenv("DB_CONNECTION={$connection}");
        putenv("DB_DATABASE={$database}");
        $_ENV['DB_CONNECTION'] = $connection;
        $_ENV['DB_DATABASE'] = $database;
        $_SERVER['DB_CONNECTION'] = $connection;
        $_SERVER['DB_DATABASE'] = $database;

        parent::setUp();

        config([
            'app.locale' => 'en',
            'database.default' => $connection,
            "database.connections.{$connection}.database" => $database,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_resource_is_read_only_and_uses_only_view_permissions(): void
    {
        $this->assertSame(['index', 'view'], array_keys(UserProductResource::getPages()));
        $this->assertFalse(config('filament-shield.policies.merge'));
        $this->assertSame(
            ['viewAny', 'view'],
            config('filament-shield.resources.manage')[UserProductResource::class],
        );

        $resourcePolicyMethods = collect((new ReflectionClass(UserProductPolicy::class))->getMethods())
            ->pluck('name')
            ->intersect(config('filament-shield.policies.methods'))
            ->values()
            ->all();

        $this->assertSame(
            ['viewAny', 'view'],
            $resourcePolicyMethods,
        );
    }

    public function test_list_and_view_permissions_are_independent(): void
    {
        $userProduct = $this->createUserProduct($this->createUser());
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'admin')
            ->get('/admin/user-products')
            ->assertForbidden();

        $admin->givePermissionTo($this->permission('ViewAny:UserProduct'));

        $this->actingAs($admin, 'admin')
            ->get('/admin/user-products')
            ->assertOk();

        $this->actingAs($admin, 'admin')
            ->get('/admin/user-products/'.$userProduct->getKey())
            ->assertForbidden();

        $admin->givePermissionTo($this->permission('View:UserProduct'));

        $this->actingAs($admin, 'admin')
            ->get('/admin/user-products/'.$userProduct->getKey())
            ->assertOk();
    }

    public function test_restricted_support_only_sees_managed_downline_products(): void
    {
        $managedUser = $this->createUser();
        $downline = $this->createUser($managedUser);
        $unrelated = $this->createUser();
        $allowedProduct = $this->createUserProduct($downline);
        $blockedProduct = $this->createUserProduct($unrelated);

        $admin = $this->createAdmin($managedUser);
        $supportRole = Role::findOrCreate('Support', 'admin');
        $admin->assignRole($supportRole);
        $admin->givePermissionTo([
            $this->permission('ViewAny:UserProduct'),
            $this->permission('View:UserProduct'),
        ]);

        $this->actingAs($admin, 'admin');

        $this->assertSame(
            [$allowedProduct->getKey()],
            UserProductResource::getEloquentQuery()->pluck('user_products.id')->all(),
        );

        $this->get('/admin/user-products/'.$allowedProduct->getKey())->assertOk();
        $this->get('/admin/user-products/'.$blockedProduct->getKey())->assertNotFound();
    }

    public function test_hourly_progress_and_deleted_product_snapshot_are_rendered(): void
    {
        $product = $this->createProduct([
            'name' => 'Live Product Name',
            'claim_interval_hours' => 6,
            'total_claims' => null,
        ]);
        $userProduct = $this->createUserProduct(
            $this->createUser(),
            $product,
            [
                'claim_interval_hours_snapshot' => 6,
                'duration_days_snapshot' => 2,
                'total_claims_snapshot' => null,
                'claimed_days' => 3,
                'purchased_at' => '2026-07-02 11:17:00',
                'meta' => [
                    'product_name_snapshot' => 'Snapshot Hourly Product',
                    'product_code_snapshot' => 'SNAP-HOURLY',
                    'payment_wallet_type' => 'recharge',
                ],
            ],
        );
        $product->delete();

        $admin = $this->createAdmin();
        $admin->givePermissionTo([
            $this->permission('ViewAny:UserProduct'),
            $this->permission('View:UserProduct'),
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get('/admin/user-products/'.$userProduct->getKey());

        $response
            ->assertOk()
            ->assertSee('Snapshot Hourly Product')
            ->assertSee('SNAP-HOURLY')
            ->assertSee('Jul 2, 2026')
            ->assertSee('3 / 8');
    }

    public function test_resource_query_eager_loads_user_and_product(): void
    {
        $this->createUserProduct($this->createUser());
        $admin = $this->createAdmin();
        $admin->givePermissionTo($this->permission('ViewAny:UserProduct'));
        $this->actingAs($admin, 'admin');

        $record = UserProductResource::getEloquentQuery()->firstOrFail();

        $this->assertTrue($record->relationLoaded('user'));
        $this->assertTrue($record->relationLoaded('product'));
    }

    public function test_user_relation_manager_requires_user_product_list_permission(): void
    {
        $user = $this->createUser();
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        $this->assertFalse(UserProductsRelationManager::canViewForRecord(
            $user,
            ViewUser::class,
        ));

        $admin->givePermissionTo($this->permission('ViewAny:UserProduct'));

        $this->assertTrue(UserProductsRelationManager::canViewForRecord(
            $user,
            ViewUser::class,
        ));
    }

    private function createAdmin(?User $managedUser = null): AdminUser
    {
        $this->sequence++;

        return AdminUser::query()->create([
            'username' => 'resource-admin-'.$this->sequence,
            'password' => Hash::make('password'),
            'managed_user_id' => $managedUser?->getKey(),
            'status' => 'active',
        ]);
    }

    private function createUser(?User $referrer = null): User
    {
        $this->sequence++;

        return User::query()->create([
            'name' => 'Resource User '.$this->sequence,
            'username' => 'resource-user-'.$this->sequence,
            'phone' => '6000'.$this->sequence,
            'password' => Hash::make('password'),
            'referrer_id' => $referrer?->getKey(),
            'referral_code' => 'RESOURCE'.$this->sequence,
            'status' => 'active',
        ]);
    }

    private function createProduct(array $attributes = []): Product
    {
        $this->sequence++;

        return Product::query()->create(array_merge([
            'name' => 'Resource Product '.$this->sequence,
            'code' => 'RESOURCE-P'.$this->sequence,
            'product_type' => Product::TYPE_NORMAL,
            'price' => 100,
            'daily_income' => 10,
            'duration_days' => 10,
            'claim_interval_hours' => 24,
            'total_claims' => 10,
            'status' => 'active',
        ], $attributes));
    }

    private function createUserProduct(
        User $user,
        ?Product $product = null,
        array $attributes = [],
    ): UserProduct {
        $product ??= $this->createProduct();
        $purchasedAt = now()->subDay();

        return UserProduct::query()->create(array_merge([
            'user_id' => $user->getKey(),
            'product_id' => $product->getKey(),
            'price_snapshot' => $product->price,
            'daily_income_snapshot' => $product->daily_income,
            'duration_days_snapshot' => $product->duration_days,
            'claim_interval_hours_snapshot' => $product->claim_interval_hours,
            'total_claims_snapshot' => $product->total_claims,
            'product_type_snapshot' => $product->product_type,
            'claimed_days' => 0,
            'missed_days' => 0,
            'purchased_at' => $purchasedAt,
            'next_claim_at' => $purchasedAt->copy()->addHours((int) $product->claim_interval_hours),
            'expires_at' => $purchasedAt->copy()->addDays((int) $product->duration_days),
            'status' => 'active',
            'meta' => [
                'product_name_snapshot' => $product->name,
                'product_code_snapshot' => $product->code,
                'payment_wallet_type' => 'recharge',
            ],
        ], $attributes));
    }

    private function permission(string $name): Permission
    {
        return Permission::findOrCreate($name, 'admin');
    }
}
