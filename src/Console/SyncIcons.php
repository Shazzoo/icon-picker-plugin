<?php

namespace Shazzoo\IconPicker\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Shazzoo\IconPicker\Support\IconRepository;

/**
 * Installs every icon referenced by stored content.
 *
 * Run it in deploy or CI: it turns "an icon silently renders as nothing" into
 * a build failure, which is the whole point of keeping icons as files.
 */
class SyncIcons extends Command
{
    protected $signature = 'icons:sync {--check : Report what is missing without installing anything}';

    protected $description = 'Install every icon referenced by page content, and report any that cannot be resolved.';

    public function handle(): int
    {
        $referenced = $this->referencedKeys();

        if ($referenced === []) {
            $this->info('No icon references found in content.');

            return self::SUCCESS;
        }

        $installed = [];
        $missing = [];

        foreach ($referenced as $key) {
            if (IconRepository::isInstalled($key)) {
                continue;
            }

            if ($this->option('check')) {
                $missing[] = $key;

                continue;
            }

            if (IconRepository::install($key)) {
                $installed[] = $key;
            } else {
                $missing[] = $key;
            }
        }

        $this->line(sprintf(
            '  %d referenced, %d already present, %d installed.',
            count($referenced),
            count($referenced) - count($installed) - count($missing),
            count($installed),
        ));

        foreach ($installed as $key) {
            $this->line('  + '.$key);
        }

        if ($missing !== []) {
            $this->error('Referenced icons that are not available:');

            foreach ($missing as $key) {
                $this->error('  '.$key.($this->option('check') ? ' (not installed)' : ' (not in the catalogue)'));
            }

            return self::FAILURE;
        }

        $this->info('All referenced icons are installed.');

        return self::SUCCESS;
    }

    /**
     * Every distinct icon key stored in page content.
     *
     * @return array<int, string>
     */
    private function referencedKeys(): array
    {
        $keys = array_merge(
            (array) config('icon-picker.bundled', []),
            (array) config('icon-picker.required', []),
        );

        foreach (DB::table('pages')->pluck('content') as $content) {
            $decoded = is_string($content) ? json_decode($content, true) : $content;

            if (! is_array($decoded)) {
                continue;
            }

            $this->collect($decoded, $keys);
        }

        $keys = array_map([IconRepository::class, 'normalise'], $keys);

        return array_values(array_unique(array_filter($keys)));
    }

    /**
     * @param  array<mixed>  $node
     * @param  array<int, string>  $keys
     */
    private function collect(array $node, array &$keys): void
    {
        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $this->collect($value, $keys);

                continue;
            }

            if ($key === 'icon' && is_string($value) && trim($value) !== '') {
                $keys[] = $value;
            }
        }
    }
}
