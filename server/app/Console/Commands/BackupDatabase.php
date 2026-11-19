<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class BackupDatabase extends Command
{
    protected $signature = 'psst:backup';

    protected $description = 'Dump the database, encrypt it, upload it to the backups disk and prune old backups';

    public function handle(): int
    {
        $passphrase = (string) config('psst.backup.passphrase');
        if ($passphrase === '') {
            $this->error('BACKUP_PASSPHRASE is not set; refusing to write an unencrypted backup.');
            $this->reportFailure();

            return self::FAILURE;
        }

        $name = 'psst-'.now()->utc()->format('Ymd-His').'.sql.gz.enc';
        $tmp = tempnam(sys_get_temp_dir(), 'psst-backup-');

        try {
            $connection = config('database.connections.'.config('database.default'));

            // Credentials and the passphrase go through the child's environment, never its argv.
            $result = Process::timeout(3600)
                ->env([
                    'PGHOST' => (string) $connection['host'],
                    'PGPORT' => (string) $connection['port'],
                    'PGUSER' => (string) $connection['username'],
                    'PGPASSWORD' => (string) $connection['password'],
                    'PGDATABASE' => (string) $connection['database'],
                    'BACKUP_PASSPHRASE' => $passphrase,
                    'PSST_BACKUP_OUT' => $tmp,
                ])
                ->run(['bash', '-o', 'pipefail', '-c', 'pg_dump --no-owner --no-privileges | gzip | openssl enc -aes-256-cbc -pbkdf2 -salt -pass env:BACKUP_PASSPHRASE -out "$PSST_BACKUP_OUT"']);

            if ($result->failed()) {
                $this->error('Backup failed: '.trim($result->errorOutput()));
                $this->reportFailure();

                return self::FAILURE;
            }

            $disk = Storage::disk(config('psst.backup.disk'));
            $stream = fopen($tmp, 'rb');
            try {
                $disk->writeStream($name, $stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            $this->prune($disk);
            $this->info("Uploaded {$name}");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Backup failed: '.$e->getMessage());
            $this->reportFailure();

            return self::FAILURE;
        } finally {
            @unlink($tmp);
        }
    }

    /** Reaches the error tracker. Generic on purpose: the process error output can contain hostnames. */
    private function reportFailure(): void
    {
        report(new RuntimeException('psst:backup failed'));
    }

    private function prune(Filesystem $disk): void
    {
        $cutoff = now()->subDays((int) config('psst.backup.keep_days'))->getTimestamp();

        foreach ($disk->files() as $file) {
            if (preg_match('/^psst-\d{8}-\d{6}\.sql\.gz\.enc$/', $file) && $disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
            }
        }
    }
}
