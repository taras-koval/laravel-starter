<?php

namespace TarasKoval\LaravelStarter\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class StarterPublishCommand extends Command
{
    protected $signature = 'starter:publish';

    protected $description = 'Publish the Laravel starter files into the current project.';

    /**
     * Directories mirrored entirely (always overwritten), keyed by stub path.
     *
     * @var array<string, string>
     */
    protected array $directories = [
        'app' => 'app',
        'resources' => 'resources',
        'tests' => 'tests',
        '.cursor/rules' => '.cursor/rules',
        '.github' => '.github',
    ];

    /**
     * Individual files always overwritten, keyed by stub path.
     *
     * @var array<string, string>
     */
    protected array $files = [
        'bootstrap/app.php' => 'bootstrap/app.php',
        'routes/auth.php' => 'routes/auth.php',
        'database/factories/UserFactory.php' => 'database/factories/UserFactory.php',
        'database/migrations/0001_01_01_000000_create_users_table.php' => 'database/migrations/0001_01_01_000000_create_users_table.php',
        'config/app.php' => 'config/app.php',
        'config/auth.php' => 'config/auth.php',
        'config/cache.php' => 'config/cache.php',
        'config/cors.php' => 'config/cors.php',
        'config/filesystems.php' => 'config/filesystems.php',
        'config/laravellocalization.php' => 'config/laravellocalization.php',
        'config/logging.php' => 'config/logging.php',
        'config/sanctum.php' => 'config/sanctum.php',
        'config/services.php' => 'config/services.php',
        '.env.example' => '.env.example',
        '.gitignore' => '.gitignore',
        'boost.json' => 'boost.json',
        'package.json' => 'package.json',
        'phpunit.xml' => 'phpunit.xml',
        'pint.json' => 'pint.json',
    ];

    /**
     * Files copied only when the destination is missing or lacks a marker string.
     *
     * @var array<int, array{stub: string, destination: string, needle: string, label: string}>
     */
    protected array $filesWhenMissing = [
        [
            'stub' => 'routes/api.php',
            'destination' => 'routes/api.php',
            'needle' => '<?php',
            'label' => 'API routes',
        ],
        [
            'stub' => 'routes/web.php',
            'destination' => 'routes/web.php',
            'needle' => 'LaravelLocalization::setLocale()',
            'label' => 'localized web routes',
        ],
        [
            'stub' => 'routes/console.php',
            'destination' => 'routes/console.php',
            'needle' => "Schedule::command('app:update-geoip-database')",
            'label' => 'scheduled GeoIP update command',
        ],
        [
            'stub' => 'README.md',
            'destination' => 'README.md',
            'needle' => 'Trusted Proxies',
            'label' => 'Trusted Proxies documentation',
        ],
    ];

    /**
     * Migrations published with a fresh timestamp, skipped when one already matches the pattern.
     *
     * @var array<int, array{stub: string, destination: string, pattern: string, filename: string}>
     */
    protected array $migrations = [
        [
            'stub' => 'database/migrations/create_personal_access_tokens_table.php',
            'destination' => 'database/migrations',
            'pattern' => 'create_personal_access_tokens',
            'filename' => 'create_personal_access_tokens_table.php',
        ],
    ];

    public function handle(Filesystem $files): int
    {
        $published = 0;

        foreach ($this->directories as $stub => $destination) {
            $published += $this->publishDirectory($files, $stub, $destination);
        }

        foreach ($this->files as $stub => $destination) {
            $published += $this->publishFile($files, $stub, $destination) ? 1 : 0;
        }

        foreach ($this->filesWhenMissing as $file) {
            $published += $this->publishFileWhenMissing($files, $file) ? 1 : 0;
        }

        foreach ($this->migrations as $migration) {
            $published += $this->publishMigration($files, $migration) ? 1 : 0;
        }

        $this->newLine();
        $this->components->info("Published {$published} starter file(s).");

        return self::SUCCESS;
    }

    protected function publishDirectory(Filesystem $files, string $stub, string $destination): int
    {
        $source = $this->stubsPath($stub);

        if (!$files->isDirectory($source)) {
            $this->components->warn("No stubs found for directory [{$stub}].");

            return 0;
        }

        $count = 0;

        foreach ($files->allFiles($source) as $file) {
            $relativePath = str_replace('\\', '/', $file->getRelativePathname());

            $this->copy($files, $file->getPathname(), $destination . '/' . $relativePath);

            $count++;
        }

        return $count;
    }

    protected function publishFile(Filesystem $files, string $stub, string $destination): bool
    {
        $source = $this->stubsPath($stub);

        if (!$files->exists($source)) {
            $this->components->warn("No stub found for [{$destination}].");

            return false;
        }

        $this->copy($files, $source, $destination);

        return true;
    }

    /**
     * @param  array{stub: string, destination: string, needle: string, label: string}  $file
     */
    protected function publishFileWhenMissing(Filesystem $files, array $file): bool
    {
        $source = $this->stubsPath($file['stub']);

        if (!$files->exists($source)) {
            $this->components->warn("No stub found for [{$file['destination']}].");

            return false;
        }

        $target = base_path($file['destination']);

        if ($files->exists($target) && str_contains($files->get($target), $file['needle'])) {
            $this->components->twoColumnDetail("{$file['destination']} — {$file['label']}", '<fg=yellow>ALREADY PRESENT</>');

            return false;
        }

        $this->copy($files, $source, $file['destination']);

        return true;
    }

    /**
     * @param  array{stub: string, destination: string, pattern: string, filename: string}  $migration
     */
    protected function publishMigration(Filesystem $files, array $migration): bool
    {
        $source = $this->stubsPath($migration['stub']);

        if (!$files->exists($source)) {
            $this->components->warn("No stub found for migration [{$migration['filename']}].");

            return false;
        }

        $destinationDir = base_path($migration['destination']);

        if ($files->isDirectory($destinationDir) && $files->glob($destinationDir . '/*' . $migration['pattern'] . '*.php') !== []) {
            $this->components->twoColumnDetail("{$migration['destination']}/*{$migration['pattern']}*", '<fg=yellow>ALREADY PRESENT</>');

            return false;
        }

        $relativePath = $migration['destination'] . '/' . date('Y_m_d_His') . '_' . $migration['filename'];

        $this->copy($files, $source, $relativePath);

        return true;
    }

    protected function copy(Filesystem $files, string $source, string $destination): void
    {
        $target = base_path($destination);

        $files->ensureDirectoryExists(dirname($target));
        $files->copy($source, $target);

        $this->components->twoColumnDetail($destination, '<fg=green>PUBLISHED</>');
    }

    protected function stubsPath(string $relativePath): string
    {
        return dirname(__DIR__, 3) . '/stubs/' . $relativePath;
    }
}
