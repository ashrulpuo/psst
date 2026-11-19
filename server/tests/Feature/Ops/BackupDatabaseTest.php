<?php

namespace Tests\Feature\Ops;

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class BackupDatabaseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['psst.backup.disk' => 'backups', 'psst.backup.passphrase' => 'correct horse battery staple', 'psst.backup.keep_days' => 13]);
        Storage::fake('backups');
    }

    public function test_it_uploads_an_encrypted_dump_and_prunes_old_ones(): void
    {
        $disk = Storage::disk('backups');
        $disk->put('psst-20200101-030000.sql.gz.enc', 'old');
        $disk->put('psst-20200201-030000.sql.gz.enc', 'recent');
        touch($disk->path('psst-20200101-030000.sql.gz.enc'), now()->subDays(20)->getTimestamp());
        touch($disk->path('psst-20200201-030000.sql.gz.enc'), now()->subDays(3)->getTimestamp());

        $seen = null;
        Process::fake(function (PendingProcess $process) use (&$seen) {
            $seen = $process;
            file_put_contents($process->environment['PSST_BACKUP_OUT'], 'ciphertext');

            return Process::result(exitCode: 0);
        });

        $this->artisan('psst:backup')->assertExitCode(0);

        $created = collect($disk->files())->reject(fn ($f) => str_contains($f, '2020'));
        $this->assertCount(1, $created);
        $this->assertMatchesRegularExpression('/^psst-\d{8}-\d{6}\.sql\.gz\.enc$/', $created->first());
        $this->assertSame('ciphertext', $disk->get($created->first()));
        $this->assertFalse($disk->exists('psst-20200101-030000.sql.gz.enc'));
        $this->assertTrue($disk->exists('psst-20200201-030000.sql.gz.enc'));

        // Secrets travel in the child's environment only, never in the command line.
        $this->assertSame('correct horse battery staple', $seen->environment['BACKUP_PASSPHRASE']);
        $this->assertArrayHasKey('PGPASSWORD', $seen->environment);
        $this->assertStringNotContainsString('correct horse', (string) json_encode($seen->command));
        $this->assertFileDoesNotExist($seen->environment['PSST_BACKUP_OUT']);
    }

    public function test_it_fails_without_uploading_when_pg_dump_fails(): void
    {
        Process::fake(fn () => Process::result(errorOutput: 'pg_dump: boom', exitCode: 1));

        Exceptions::fake();

        $this->artisan('psst:backup')->assertExitCode(1);

        $this->assertSame([], Storage::disk('backups')->files());
        Exceptions::assertReported(fn (RuntimeException $e) => $e->getMessage() === 'psst:backup failed');
    }

    public function test_it_refuses_to_run_without_a_passphrase(): void
    {
        config(['psst.backup.passphrase' => '']);
        Process::fake();

        $this->artisan('psst:backup')->assertExitCode(1);

        Process::assertNothingRan();
        $this->assertSame([], Storage::disk('backups')->files());
    }
}
