<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
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

        $backupDirectory = storage_path('app/private/database_backups');

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

            $this->error('SQL dump is empty.');

            return self::FAILURE;
        }

        // Create ZIP containing SQL file
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($sqlPath);

            $this->error('Unable to create ZIP file.');

            return self::FAILURE;
        }

        $zip->addFile($sqlPath, $fileName . '.sql');
        $zip->close();

        // Remove original SQL after successful ZIP creation
        @unlink($sqlPath);

        $this->info('Database backup created successfully!');
        $this->info('Backup file: ' . $zipPath);
        $this->info('File size: ' . number_format(filesize($zipPath) / 1024, 2) . ' KB');

        return self::SUCCESS;
    }
}
