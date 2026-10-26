<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\Config\Config;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use ZipArchive;

class DatabaseBackupTest extends TestCase
{
    public function test_database_backup_contains_a_restorable_sql_dump(): void
    {
        $sqlite = (new ExecutableFinder)->find('sqlite3');

        if ($sqlite === null) {
            $this->markTestSkipped('The sqlite3 executable is required for the backup integration test.');
        }

        Storage::fake('backups');
        $directory = Storage::disk('backups')->path('fixture');
        File::ensureDirectoryExists($directory);

        try {
            $database = $directory.'/source.sqlite';
            (new Process([
                $sqlite, $database,
                "CREATE TABLE backup_probe (value TEXT); INSERT INTO backup_probe VALUES ('backup-round-trip');",
            ]))->mustRun();

            // Use an isolated fixture, never the application's configured database.
            config([
                'database.connections.backup_fixture' => [
                    'driver' => 'sqlite',
                    'database' => $database,
                    'dump' => ['dump_binary_path' => dirname($sqlite)],
                ],
                'backup.backup.source.databases' => ['backup_fixture'],
                'backup.backup.database_dump_filename_base' => 'connection',
                'backup.backup.temporary_directory' => $directory.'/temp',
                'backup.backup.password' => null,
            ]);
            Config::rebind();

            $this->artisan('backup:run', ['--only-db' => true])
                ->expectsOutputToContain('Backup completed!')
                ->assertSuccessful();

            $archives = Storage::disk('backups')->files('database');
            $this->assertCount(1, $archives);

            $zip = new ZipArchive;
            $this->assertTrue($zip->open(Storage::disk('backups')->path($archives[0])));
            $this->assertSame(1, $zip->numFiles);
            $this->assertStringEndsWith('.sql', $zip->getNameIndex(0));
            $sql = $zip->getFromIndex(0);
            $zip->close();
            $this->assertIsString($sql);

            $restore = new Process([$sqlite, $directory.'/restored.sqlite']);
            $restore->setInput($sql)->mustRun();
            $query = new Process([$sqlite, $directory.'/restored.sqlite', 'SELECT value FROM backup_probe;']);
            $query->mustRun();
            $this->assertSame('backup-round-trip', trim($query->getOutput()));
        } finally {
            Storage::disk('backups')->deleteDirectory('database');
            File::deleteDirectory($directory);
        }
    }
}
