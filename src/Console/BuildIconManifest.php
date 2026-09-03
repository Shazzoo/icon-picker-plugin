<?php

namespace Shazzoo\IconPicker\Console;

use Illuminate\Console\Command;
use Shazzoo\IconPicker\Support\IconRepository;

/**
 * Compiles the bundled icon sets into a single manifest.
 *
 * Reading SVGs off disk at request time would tie the plugin to node_modules
 * and to a specific Heroicons package layout; the manifest keeps it standalone
 * and makes it portable when this moves into a Composer package.
 */
class BuildIconManifest extends Command
{
    protected $signature = 'icons:build';

    protected $description = 'Compile the Lucide and Heroicons sets into the icon picker manifest.';

    public function handle(): int
    {
        $sources = [
            'lucide' => [
                'path' => base_path('node_modules/lucide-static/icons'),
                'pattern' => '*.svg',
                'name' => fn (string $file) => basename($file, '.svg'),
            ],
        ];

        $manifest = [];
        $missing = [];

        foreach ($sources as $set => $source) {
            if (! is_dir($source['path'])) {
                $missing[] = $set.' ('.$source['path'].')';

                continue;
            }

            $files = glob($source['path'].'/'.$source['pattern']) ?: [];

            foreach ($files as $file) {
                $svg = (string) file_get_contents($file);
                $body = $this->innerMarkup($svg);

                if ($body === '') {
                    continue;
                }

                $name = $source['name']($file);

                $manifest[$set.':'.$name] = [
                    'set' => $set,
                    'name' => $name,
                    'viewBox' => $this->viewBox($svg),
                    'body' => $body,
                ];
            }

            $this->line(sprintf('  %-12s %d icons', $set, count($files)));
        }

        // Refuse to write a partial manifest. Skipping a missing source would
        // quietly drop a whole set — a build without node_modules would leave
        // every Lucide icon on the site rendering nothing, with no error.
        if ($missing !== []) {
            $this->error('Icon source missing, refusing to write a partial manifest:');

            foreach ($missing as $m) {
                $this->error('  '.$m);
            }

            return self::FAILURE;
        }

        if ($manifest === []) {
            $this->error('No icons found — nothing written.');

            return self::FAILURE;
        }

        ksort($manifest);

        $json = json_encode($manifest, JSON_UNESCAPED_SLASHES);

        file_put_contents(IconRepository::cataloguePath(), $json);

        // Published so the browser fetches it straight off the web server: the
        // CMS registers a greedy {slug?} route that swallows app routes.
        $public = public_path(IconRepository::publicPath());

        if (! is_dir(dirname($public))) {
            mkdir(dirname($public), 0755, true);
        }

        file_put_contents($public, $json);

        $this->info(sprintf(
            'Wrote %d icons (%s KB) to %s',
            count($manifest),
            number_format(filesize(IconRepository::cataloguePath()) / 1024),
            IconRepository::cataloguePath(),
        ));

        return self::SUCCESS;
    }

    /**
     * Everything inside the root <svg>, with the sizing attributes dropped so
     * the consuming component controls dimensions and colour.
     */
    private function innerMarkup(string $svg): string
    {
        if (! preg_match('/<svg[^>]*>(.*)<\/svg>/s', $svg, $m)) {
            return '';
        }

        // Returned verbatim. Stripping width/height here would also strip them
        // from child <rect> elements, which is how they are sized — a rect
        // without them draws nothing.
        return trim($m[1]);
    }

    private function viewBox(string $svg): string
    {
        return preg_match('/viewBox="([^"]+)"/', $svg, $m) ? $m[1] : '0 0 24 24';
    }
}
