<?php

namespace Shazzoo\IconPicker\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Icons are individual SVG files, read from two places:
 *
 *  - bundled: shipped inside the plugin and committed, for the handful it needs
 *    itself. Read-only, always present, cannot be removed from the admin.
 *  - installed: written to a configured disk (shared between releases, the way
 *    media is), so icons added from the admin survive a deploy.
 *
 * A file is all rendering depends on. The full catalogue (icons.json) is a
 * browsing aid for the admin only: if it is stale or missing you cannot add
 * new icons, but nothing already installed breaks.
 */
class IconRepository
{
    /** @var array<string, array{body: string, viewBox: string, set: string, name: string}> */
    private static array $memo = [];

    private static ?array $installed = null;

    /** Committed inside the plugin. */
    public static function bundledPath(): string
    {
        return dirname(__DIR__, 2).'/resources/icons';
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(config('icon-picker.disk', 'public'));
    }

    public static function directory(): string
    {
        return trim((string) config('icon-picker.directory', 'icons'), '/');
    }

    /** Path on the configured disk, e.g. icons/lucide/layers.svg */
    public static function storagePathFor(string $key): ?string
    {
        $segments = self::segments($key);

        return $segments ? self::directory()."/{$segments[0]}/{$segments[1]}.svg" : null;
    }

    public static function cataloguePath(): string
    {
        return dirname(__DIR__, 2).'/resources/icons.json';
    }

    public static function publicPath(): string
    {
        return 'icon-picker/icons.json';
    }

    // ---------------------------------------------------------------- reading

    /**
     * @return array{0: string, 1: string}|null
     */
    private static function segments(string $key): ?array
    {
        $key = self::normalise($key);

        if (! str_contains($key, ':')) {
            return null;
        }

        [$set, $name] = explode(':', $key, 2);

        return self::safe($set) && self::safe($name) ? [$set, $name] : null;
    }

    public static function bundledPathFor(string $key): ?string
    {
        $segments = self::segments($key);

        return $segments ? self::bundledPath()."/{$segments[0]}/{$segments[1]}.svg" : null;
    }

    public static function isBundled(string $key): bool
    {
        $path = self::bundledPathFor($key);

        return $path !== null && is_file($path);
    }

    /**
     * One icon, read from its own file.
     *
     * @return array{body: string, viewBox: string, set: string, name: string}|null
     */
    public static function find(string $key): ?array
    {
        $key = self::normalise($key);

        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }

        $segments = self::segments($key);

        if (! $segments) {
            return self::$memo[$key] = null;
        }

        [$set, $name] = $segments;

        // Installed wins, so an icon added in the admin can replace a bundled one.
        $storagePath = self::storagePathFor($key);

        if ($storagePath && self::disk()->exists($storagePath)) {
            return self::$memo[$key] = self::parse((string) self::disk()->get($storagePath), $set, $name);
        }

        $bundled = self::bundledPathFor($key);

        if ($bundled && is_file($bundled)) {
            return self::$memo[$key] = self::parse((string) file_get_contents($bundled), $set, $name);
        }

        return self::$memo[$key] = null;
    }

    public static function has(string $key): bool
    {
        return self::find($key) !== null;
    }

    public static function body(string $key): string
    {
        return self::find($key)['body'] ?? '';
    }

    public static function viewBox(string $key): string
    {
        return self::find($key)['viewBox'] ?? '0 0 24 24';
    }

    /**
     * Every bundled set is stroked; kept so callers do not have to care if a
     * filled set is ever added back.
     */
    public static function isFilled(string $key): bool
    {
        return false;
    }

    /**
     * Every installed icon, for the picker grid.
     *
     * @return array<string, array{body: string, viewBox: string, set: string, name: string}>
     */
    public static function installed(): array
    {
        if (self::$installed !== null) {
            return self::$installed;
        }

        $out = [];

        foreach (glob(self::bundledPath().'/*/*.svg') ?: [] as $file) {
            $set = basename(dirname($file));
            $name = basename($file, '.svg');

            $out[$set.':'.$name] = self::parse((string) file_get_contents($file), $set, $name);
        }

        foreach (self::disk()->files(self::directory(), true) as $file) {
            if (! str_ends_with($file, '.svg')) {
                continue;
            }

            $set = basename(dirname($file));
            $name = basename($file, '.svg');

            $out[$set.':'.$name] = self::parse((string) self::disk()->get($file), $set, $name);
        }

        ksort($out);

        return self::$installed = $out;
    }

    public static function isInstalled(string $key): bool
    {
        return isset(self::installed()[self::normalise($key)]);
    }

    public static function installedCount(): int
    {
        return count(self::installed());
    }

    // -------------------------------------------------------------- catalogue

    /**
     * The full set available to install. Admin-only; never on the render path.
     *
     * @return array<string, array{body: string, viewBox: string, set: string, name: string}>
     */
    public static function catalogue(): array
    {
        $path = self::cataloguePath();

        return is_file($path)
            ? (json_decode((string) file_get_contents($path), true) ?: [])
            : [];
    }

    /**
     * Copies an icon out of the catalogue into the project.
     */
    public static function install(string $key): bool
    {
        $key = self::normalise($key);
        $entry = self::catalogue()[$key] ?? null;
        $path = self::storagePathFor($key);

        if (! $entry || ! $path) {
            return false;
        }

        self::disk()->put($path, self::render($entry));

        unset(self::$memo[$key]);
        self::$installed = null;

        return true;
    }

    /**
     * Removes an installed icon. Bundled icons stay: the plugin needs them.
     */
    public static function uninstall(string $key): bool
    {
        $path = self::storagePathFor($key);

        if (! $path || ! self::disk()->exists($path)) {
            return false;
        }

        self::disk()->delete($path);

        unset(self::$memo[self::normalise($key)]);
        self::$installed = null;

        return true;
    }

    // ----------------------------------------------------------------- naming

    /**
     * Short names the theme used before icons were namespaced, mapped to the
     * Lucide icons they actually rendered.
     */
    private const LEGACY_ALIASES = [
        'building' => 'building-2',
        'bulb' => 'lightbulb',
        'chart' => 'chart-column',
        'chat' => 'message-square',
        'close' => 'x',
        'cycle' => 'refresh-cw',
        'grid' => 'layout-grid',
        'life' => 'life-buoy',
        'pen' => 'pen-line',
        'shield' => 'shield-check',
    ];

    public static function normalise(string $key): string
    {
        $key = trim($key);

        if ($key === '' || str_contains($key, ':')) {
            return $key;
        }

        $slug = Str::of($key)->slug()->value();

        return 'lucide:'.(self::LEGACY_ALIASES[$slug] ?? $slug);
    }

    // ----------------------------------------------------------------- helpers

    /**
     * @return array{body: string, viewBox: string, set: string, name: string}
     */
    private static function parse(string $svg, string $set, string $name): array
    {
        preg_match('/<svg[^>]*>(.*)<\/svg>/s', $svg, $m);
        preg_match('/viewBox="([^"]+)"/', $svg, $v);

        return [
            'set' => $set,
            'name' => $name,
            'viewBox' => $v[1] ?? '0 0 24 24',
            'body' => trim($m[1] ?? ''),
        ];
    }

    /**
     * Stored as a complete, self-sufficient SVG: valid on its own, readable in
     * a diff, and correct in any renderer that drops the file in as-is (Blade
     * Icons, a browser tab). The theme's own component reads just the body and
     * viewBox and applies its own stroke width.
     */
    public static function render(array $entry): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="'.$entry['viewBox'].'"'
            .' fill="none" stroke="currentColor" stroke-width="2"'
            .' stroke-linecap="round" stroke-linejoin="round">'
            ."\n".$entry['body']."\n"
            .'</svg>'."\n";
    }

    private static function safe(string $segment): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9-]*$/i', $segment);
    }
}
