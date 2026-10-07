<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\PageImages\PageImageResource;
use App\Filament\Resources\PageImages\Pages\ManagePageImages;
use App\Models\AdminUser;
use App\Models\PageImage;
use App\Models\Setting;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PageImagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('do_spaces');

        foreach (PageImage::all() as $image) {
            Storage::disk('do_spaces')->put(
                $image->image_path,
                UploadedFile::fake()->image('original.jpg')->getContent(),
            );
        }
    }

    public function test_migration_imports_all_eight_originals_in_their_page_order(): void
    {
        $this->assertDatabaseCount('page_images', 8);

        foreach ([
            'dashboard' => ['week_en.jpg', 'week_vi.jpg'],
            'my-team' => ['level_en.jpg', 'level_vi.jpg'],
            'products' => ['product_en.jpg', 'product_vi.jpg'],
            'profile' => ['product_commission_en.jpg', 'product_commission_vi.jpg'],
        ] as $page => $files) {
            $this->assertSame(
                array_map(fn (string $file): string => 'page-images/defaults/'.$file, $files),
                PageImage::query()->forPage($page)->pluck('image_path')->all(),
            );
        }
    }

    public function test_all_four_customer_pages_render_their_cloud_images(): void
    {
        Setting::setValue('app_download_url', 'https://example.test/app.apk');

        $user = User::query()->create([
            'name' => 'Page Images Customer',
            'username' => 'page-images-customer',
            'password' => 'password',
            'status' => 'active',
            'accumulated_recharge' => 0,
        ]);

        $this->actingAs($user);

        foreach (['dashboard', 'my-team', 'products', 'profile'] as $page) {
            $response = $this->get('/'.$page)->assertOk();

            foreach (PageImage::query()->forPage($page)->get() as $image) {
                $response->assertSee($image->image_url);
            }

            $response->assertDontSee('/customer_pic/');
        }
    }

    public function test_multiple_images_are_sorted_and_disabled_or_deleted_images_stay_hidden(): void
    {
        PageImage::query()->create([
            'page' => 'dashboard',
            'title' => 'First extra image',
            'image_path' => 'page-images/extra.jpg',
            'sort_order' => 0,
            'is_enabled' => true,
        ]);
        PageImage::query()->create([
            'page' => 'dashboard',
            'title' => 'Hidden image',
            'image_path' => 'page-images/hidden.jpg',
            'is_enabled' => false,
        ]);

        $html = view('partials.page-images', ['page' => 'dashboard'])->render();

        $this->assertSame(3, substr_count($html, '<img'));
        $this->assertLessThan(strpos($html, 'week_en.jpg'), strpos($html, 'extra.jpg'));
        $this->assertStringNotContainsString('hidden.jpg', $html);
        $this->assertStringNotContainsString('level_en.jpg', $html);

        PageImage::query()->where('page', 'dashboard')->delete();

        $this->assertStringNotContainsString(
            '<img',
            view('partials.page-images', ['page' => 'dashboard'])->render(),
        );
    }

    public function test_admin_can_view_create_replace_and_delete_images_on_spaces(): void
    {
        $admin = $this->admin();
        $admin->assignRole(Role::findOrCreate('super_admin', 'admin'));
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $dashboard = Livewire::test(Dashboard::class)->assertActionVisible('pageImages');
        $this->assertSame(
            PageImageResource::getUrl(),
            $dashboard->instance()->getAction('pageImages')->getUrl(),
        );

        $manager = Livewire::test(ManagePageImages::class)
            ->callAction('create', [
                'page' => 'dashboard',
                'title' => 'New page image',
                'image_path' => UploadedFile::fake()->image('new.jpg'),
                'sort_order' => 3,
                'is_enabled' => true,
            ])
            ->assertHasNoActionErrors();

        $image = PageImage::query()->where('title', 'New page image')->firstOrFail();
        Storage::disk('do_spaces')->assertExists($image->image_path);

        $manager
            ->loadTable()
            ->mountAction(TestAction::make('view')->table($image))
            ->assertActionMounted(TestAction::make('view')->table($image))
            ->unmountAction()
            ->mountAction(TestAction::make('edit')->table($image))
            ->fillForm(['image_path' => []])
            ->fillForm([
                'page' => 'profile',
                'title' => 'Replaced page image',
                'image_path' => UploadedFile::fake()->image('replacement.jpg'),
                'sort_order' => 4,
                'is_enabled' => false,
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $originalPath = $image->image_path;
        $image->refresh();
        $this->assertSame('profile', $image->page);
        $this->assertNotSame($originalPath, $image->image_path);
        $this->assertFalse($image->is_enabled);
        Storage::disk('do_spaces')->assertExists($image->image_path);

        $manager->callAction(TestAction::make('delete')->table($image));
        $this->assertModelMissing($image);
    }

    public function test_image_management_requires_admin_permissions(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin')
            ->get('/admin/page-images')
            ->assertForbidden();

        $admin->givePermissionTo(['ViewAny:PageImage', 'View:PageImage']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $image = PageImage::query()->firstOrFail();

        Livewire::test(ManagePageImages::class)
            ->assertActionHidden('create')
            ->assertActionVisible(TestAction::make('view')->table($image))
            ->assertActionHidden(TestAction::make('edit')->table($image))
            ->assertActionHidden(TestAction::make('delete')->table($image));
    }

    public function test_non_image_uploads_are_rejected(): void
    {
        $admin = $this->admin();
        $admin->assignRole(Role::findOrCreate('super_admin', 'admin'));
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ManagePageImages::class)
            ->callAction('create', [
                'page' => 'dashboard',
                'title' => 'Invalid upload',
                'image_path' => UploadedFile::fake()->create('document.txt', 1, 'text/plain'),
                'sort_order' => 3,
                'is_enabled' => true,
            ])
            ->assertHasActionErrors(['image_path']);

        $this->assertDatabaseMissing('page_images', ['title' => 'Invalid upload']);
    }

    private function admin(): AdminUser
    {
        return AdminUser::query()->create([
            'username' => 'page-images-admin',
            'password' => 'password',
            'status' => 'active',
        ]);
    }
}
