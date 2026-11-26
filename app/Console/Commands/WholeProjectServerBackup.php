<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use ZipArchive;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;
use Illuminate\Support\Facades\Log;

class WholeProjectServerBackup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:whole-project-server-backup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create complete Laravel project files backup in ZIP format';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startedAt = now();

        $basePath = realpath(base_path());

        $backupDirectory = storage_path('app/private/backups/project_backups');

        $fileName = 'be_soft_project_' . now()->format('Y-m-d_H-i-s');

        $zipPath = $backupDirectory . '/' . $fileName . '.zip';

        Log::info('Whole project backup started', [
            'project_path' => $basePath,
            'started_at' => $startedAt->toDateTimeString(),
        ]);

        try {

            if (!is_dir($backupDirectory)) {
                mkdir($backupDirectory, 0755, true);
            }

            $zip = new ZipArchive();

            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {

                Log::error('Unable to create project backup ZIP', [
                    'file' => $zipPath,
                ]);

                $this->error('Unable to create ZIP archive.');

                return self::FAILURE;
            }

            $excludedPaths = [
                '/vendor/',
                '/node_modules/',
                '/.git/',
                '/storage/logs/',
                '/storage/framework/cache/',
                '/storage/framework/sessions/',
                '/storage/framework/views/',
                '/storage/app/private/project_backups/',
                '/bootstrap/cache/',
            ];

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $basePath,
                    FilesystemIterator::SKIP_DOTS
                ),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            $totalFiles = 0;
            $totalSize = 0;

            foreach ($iterator as $file) {

                if (!$file->isFile() || $file->isLink()) {
                    continue;
                }

                $absolutePath = $file->getPathname();

                $relativePath = str_replace(
                    DIRECTORY_SEPARATOR,
                    '/',
                    substr($absolutePath, strlen($basePath) + 1)
                );

                $checkPath = '/' . $relativePath;

                $excluded = false;

                foreach ($excludedPaths as $excludedPath) {
                    if (str_contains($checkPath, $excludedPath)) {
                        $excluded = true;
                        break;
                    }
                }

                if ($excluded) {
                    continue;
                }

                if (!$zip->addFile($absolutePath, $relativePath)) {

                    $zip->close();

                    @unlink($zipPath);

                    Log::error('Failed to add project file to ZIP', [
                        'file' => $relativePath,
                    ]);

                    $this->error('Failed to add file: ' . $relativePath);

                    return self::FAILURE;
                }

                $totalFiles++;
                $totalSize += $file->getSize();
            }

            if (!$zip->close()) {

                @unlink($zipPath);

                Log::error('Unable to finalize project backup ZIP');

                $this->error('Unable to finalize ZIP archive.');

                return self::FAILURE;
            }

            if (!file_exists($zipPath) || filesize($zipPath) === 0) {

                @unlink($zipPath);

                Log::error('Project backup ZIP is empty or missing');

                $this->error('Project backup failed.');

                return self::FAILURE;
            }

            $zipSize = filesize($zipPath);

            Log::info('Whole project backup completed successfully', [
                'backup_file' => $zipPath,
                'total_files' => $totalFiles,
                'original_size_mb' => round($totalSize / 1024 / 1024, 2),
                'zip_size_mb' => round($zipSize / 1024 / 1024, 2),
                'completed_at' => now()->toDateTimeString(),
                'duration_seconds' => $startedAt->diffInSeconds(now()),
            ]);

            $this->newLine();

            $this->info('Project backup created successfully!');
            $this->info('Files: ' . $totalFiles);
            $this->info('Location: ' . $zipPath);
            $this->info('Size: ' . number_format($zipSize / 1024 / 1024, 2) . ' MB');

            return self::SUCCESS;

        } catch (Throwable $e) {

            @unlink($zipPath);

            Log::error('Whole project backup failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->error('Project backup failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
