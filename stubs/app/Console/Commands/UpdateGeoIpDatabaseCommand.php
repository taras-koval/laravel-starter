<?php

namespace App\Console\Commands;

use FilesystemIterator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

class UpdateGeoIpDatabaseCommand extends Command
{
    protected $signature = 'app:update-geoip-database';

    protected $description = 'Download or update the MaxMind GeoLite2-City database';

    private const string EDITION = 'GeoLite2-City';
    private const string TARGET_DIR = 'geo';
    private const string TARGET_FILE = 'GeoLite2-City.mmdb';

    public function handle(): int
    {
        $licenseKey = config('services.maxmind.license_key');

        if (!$licenseKey) {
            $this->error('MAXMIND_LICENSE_KEY is not set. Get a free key at https://www.maxmind.com/en/geolite2/signup');

            return self::FAILURE;
        }

        $accountId = config('services.maxmind.account_id');

        if (!$accountId) {
            $this->error('MAXMIND_ACCOUNT_ID is not set.');

            return self::FAILURE;
        }

        $targetPath = storage_path('app/' . self::TARGET_DIR . '/' . self::TARGET_FILE);

        if (file_exists($targetPath)) {
            $age = (int) Carbon::createFromTimestamp(filemtime($targetPath))->diffInDays(absolute: true);
            $this->info("Current database is $age day(s) old.");

            if ($age < 7) {
                $this->info('Database is fresh, skipping download.');

                return self::SUCCESS;
            }
        }

        $this->info('Downloading GeoLite2-City database...');

        try {
            $response = Http::withBasicAuth($accountId, $licenseKey)
                ->timeout(60)
                ->withOptions(['sink' => $tempTarGz = tempnam(sys_get_temp_dir(), 'geoip_') . '.tar.gz'])
                ->get('https://download.maxmind.com/geoip/databases/' . self::EDITION . '/download', [
                    'suffix' => 'tar.gz',
                ]);

            if ($response->failed()) {
                $this->error("Download failed: HTTP {$response->status()}");

                return self::FAILURE;
            }

            $this->info('Extracting...');

            $extractDir = sys_get_temp_dir() . '/geoip_extract_' . uniqid();
            @mkdir($extractDir, 0755, true);

            $result = Process::run(['tar', '-xzf', $tempTarGz, '-C', $extractDir]);

            if ($result->failed()) {
                $this->error('Extraction failed: ' . $result->errorOutput());

                return self::FAILURE;
            }

            $mmdbFile = $this->findMmdbFile($extractDir);

            if (!$mmdbFile) {
                $this->error('Could not find .mmdb file in the archive.');

                return self::FAILURE;
            }

            $targetDir = dirname($targetPath);
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            copy($mmdbFile, $targetPath);

            $this->info("Database saved to $targetPath");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Failed: {$e->getMessage()}");

            return self::FAILURE;
        } finally {
            @unlink($tempTarGz);

            if (isset($extractDir) && is_dir($extractDir)) {
                $this->deleteDirectory($extractDir);
            }
        }
    }

    private function findMmdbFile(string $directory): ?string
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'mmdb') {
                return $file->getPathname();
            }
        }

        return null;
    }

    private function deleteDirectory(string $dir): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }

        @rmdir($dir);
    }
}
