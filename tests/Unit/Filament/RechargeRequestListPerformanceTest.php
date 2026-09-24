<?php

namespace Tests\Unit\Filament;

use App\Filament\Resources\RechargeRequests\Pages\ListRechargeRequests;
use App\Filament\Resources\RechargeRequests\Tables\RechargeRequestsTable;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Table;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class RechargeRequestListPerformanceTest extends TestCase
{
    public function test_receipt_previews_generate_urls_without_remote_existence_checks(): void
    {
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldNotReceive('exists');
        $disk->shouldReceive('temporaryUrl')->once()
            ->with('receipts/example.png', Mockery::any())
            ->andReturn('https://storage.test/receipts/example.png?signature=example');
        Storage::shouldReceive('disk')->with('do_spaces')->andReturn($disk);

        $table = RechargeRequestsTable::configure(Table::make(new ListRechargeRequests));
        $column = $table->getColumn('receipt_path');

        $this->assertInstanceOf(ImageColumn::class, $column);
        $this->assertSame(
            'https://storage.test/receipts/example.png?signature=example',
            $column->getImageUrl('receipts/example.png'),
        );
    }
}
