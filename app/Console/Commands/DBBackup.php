<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class DBBackup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:db-backup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create MySQL database backup in ZIP format';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');
        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port');

        Log::info('Database backup started', [
            'database' => $database,
            'started_at' => now()->toDateTimeString(),
        ]);

        $backupDirectory = storage_path('app/private/backups/database_backups');

        if (!is_dir($backupDirectory)) {
            mkdir($backupDirectory, 0755, true);
        }

        $fileName = $database . '_' . now()->format('Y-m-d_H-i-s');

        $sqlPath = $backupDirectory . '/' . $fileName . '.sql';
        $zipPath = $backupDirectory . '/' . $fileName . '.zip';

        // Create MySQL dump
        $command = [
            'mysqldump',
            '--host=' . $host,
            '--port=' . $port,
            '--user=' . $username,
            '--single-transaction',
            '--routines',
            '--triggers',
            '--events',
            '--default-character-set=utf8mb4',
            $database,
        ];

        // Pass password securely through environment variable
        $env = [
            'MYSQL_PWD' => $password ?? '',
        ];

        $process = Process::env($env)
            ->timeout(600)
            ->run($command, function ($type, $output) use ($sqlPath) {
                if ($type === 'out') {
                    file_put_contents($sqlPath, $output, FILE_APPEND);
                }
            });

        if (!$process->successful()) {
            @unlink($sqlPath);

            $this->error('Database backup failed: ' . $process->errorOutput());

            return self::FAILURE;
        }

        if (!file_exists($sqlPath) || filesize($sqlPath) === 0) {
            @unlink($sqlPath);

            Log::error('Database backup failed', [
                'error' => $process->errorOutput(),
            ]);
            $this->error('SQL dump is empty.');

            return self::FAILURE;
        }


        // Create ZIP containing SQL file
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($sqlPath);

            Log::channel('daily')->error('Unable to create database backup ZIP', [
                'file' => $zipPath,
            ]);

            $this->error('Unable to create ZIP file.');

            return self::FAILURE;
        }

        $sqlSize = filesize($sqlPath);

        if (!$zip->addFile($sqlPath, $fileName . '.sql')) {
            $zip->close();
            @unlink($sqlPath);

            Log::channel('daily')->error('Unable to add SQL file to ZIP');

            return self::FAILURE;
        }

        if (!$zip->close()) {
            @unlink($sqlPath);

            Log::channel('daily')->error('Unable to finalize ZIP archive');

            return self::FAILURE;
        }

        // Verify ZIP file
        if (!file_exists($zipPath) || filesize($zipPath) === 0) {
            @unlink($sqlPath);
            @unlink($zipPath);

            Log::channel('daily')->error('ZIP backup is empty or missing');

            return self::FAILURE;
        }

        $zipSize = filesize($zipPath);

        // Remove temporary SQL after ZIP verification
        @unlink($sqlPath);

        // Log successful backup
        Log::info('Database backup completed successfully', [
            'database' => $database,
            'sql_size_kb' => round($sqlSize / 1024, 2),
            'zip_file' => $zipPath,
            'zip_size_kb' => round($zipSize / 1024, 2),
            'completed_at' => now()->toDateTimeString(),
        ]);

        $this->info('Database backup created successfully!');
        $this->info('Backup file: ' . $zipPath);
        $this->info('File size: ' . number_format($zipSize / 1024, 2) . ' KB');

        return self::SUCCESS;

    }
}
