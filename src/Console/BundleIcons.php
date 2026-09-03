<?php

namespace Shazzoo\IconPicker\Console;

use Illuminate\Console\Command;
use Shazzoo\IconPicker\Support\IconRepository;

/**
 * Writes the configured bundled icons into the plugin itself.
 *
 * These are committed rather than written to storage, so they exist in every
 * environment without a writable disk being populated first. Run this after
 * editing the bundled list in config/icon-picker.php.
 */
class BundleIcons extends Command
{
    protected $signature = 'icons:bundle {--prune : Delete bundled files that are no longer listed}';

    protected $description = 'Write the configured default icons into the plugin.';

    public function handle(): int
    {
        $catalogue = IconRepository::catalogue();

        if ($catalogue === []) {
            $this->error('The catalogue is empty — run icons:build first.');

            return self::FAILURE;
        }

        $wanted = [];
        $missing = [];
        $written = 0;

        foreach ((array) config('icon-picker.bundled', []) as $key) {
            $key = IconRepository::normalise($key);
            $entry = $catalogue[$key] ?? null;
            $path = IconRepository::bundledPathFor($key);

            if (! $entry || ! $path) {
                $missing[] = $key;

                continue;
            }

            $wanted[$path] = true;

            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }

            $svg = IconRepository::render($entry);

            // Only touch files that actually differ, to keep diffs quiet.
            if (! is_file($path) || file_get_contents($path) !== $svg) {
                file_put_contents($path, $svg);
                $written++;
            }
        }

        $pruned = 0;

        if ($this->option('prune')) {
            foreach (glob(IconRepository::bundledPath().'/*/*.svg') ?: [] as $file) {
                if (! isset($wanted[$file])) {
                    unlink($file);
                    $pruned++;
                }
            }
        }

        $this->line(sprintf(
            '  %d bundled, %d written, %d unchanged%s.',
            count($wanted),
            $written,
            count($wanted) - $written,
            $this->option('prune') ? ", {$pruned} pruned" : '',
        ));

        if ($missing !== []) {
            $this->error('Not found in the catalogue:');

            foreach ($missing as $key) {
                $this->error('  '.$key);
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
