<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_images', function (Blueprint $table): void {
            $table->id();
            $table->string('page', 32);
            $table->string('title');
            $table->string('image_path', 2048);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
            $table->index(['page', 'is_enabled', 'sort_order']);
        });

        // These originals are uploaded to do_spaces before this migration is deployed.
        $originals = [
            'dashboard' => ['week_en.jpg', 'week_vi.jpg'],
            'my-team' => ['level_en.jpg', 'level_vi.jpg'],
            'products' => ['product_en.jpg', 'product_vi.jpg'],
            'profile' => ['product_commission_en.jpg', 'product_commission_vi.jpg'],
        ];

        foreach ($originals as $page => $files) {
            foreach ($files as $index => $file) {
                DB::table('page_images')->insert([
                    'page' => $page,
                    'title' => $file,
                    'image_path' => 'page-images/defaults/'.$file,
                    'sort_order' => $index + 1,
                    'is_enabled' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete'] as $action) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $action.':PageImage',
                'guard_name' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('page_images');

        DB::table('permissions')
            ->where('guard_name', 'admin')
            ->whereIn('name', [
                'ViewAny:PageImage', 'View:PageImage', 'Create:PageImage',
                'Update:PageImage', 'Delete:PageImage',
            ])
            ->delete();
    }
};
