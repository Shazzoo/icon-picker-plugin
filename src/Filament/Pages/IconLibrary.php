<?php

namespace Shazzoo\IconPicker\Filament\Pages;

use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Filament\Pages\Page;
use Shazzoo\IconPicker\Support\IconRepository;

/**
 * Browse the full catalogue and add icons to the project.
 *
 * Installing writes the SVG into resources/icons, which is what the picker and
 * the front end read. Nothing here is on the render path: if the catalogue is
 * missing you simply cannot add icons.
 */
class IconLibrary extends Page
{
    // Resolved through the plugin's own Blade Icons set, not Heroicons.
    protected static string|\BackedEnum|null $navigationIcon = 'lucide-squirrel';

    protected static ?string $navigationLabel = 'Icon library';

    protected static ?string $title = 'Icon library';

    protected static string|\UnitEnum|null $navigationGroup = 'Plugins';

    protected string $view = 'icon-picker::filament.pages.icon-library';

    public string $query = '';

    /** all | in-project | available */
    public string $filter = 'all';

    /** How many more icons each scroll to the bottom reveals. */
    public int $perPage = 120;

    /** How many are currently rendered; grows as you scroll. */
    public int $loaded = 120;

    /** @var array<int, string> */
    public array $installed = [];

    /** Icon awaiting a confirmed removal, if any. */
    public ?string $confirming = null;

    /** @var array<int, string> Where that icon is currently used. */
    public array $confirmingUsage = [];

    public function mount(): void
    {
        $this->refreshInstalled();
    }

    public function updatedQuery(): void
    {
        $this->loaded = $this->perPage;
    }

    public function updatedFilter(): void
    {
        $this->loaded = $this->perPage;
    }

    public function loadMore(): void
    {
        // Compared against matchCount directly: hasMore is a computed property
        // and would be memoised at its pre-increment value for this request,
        // leaving the "scroll for more" note showing after the last batch.
        if ($this->matchCount > $this->loaded) {
            $this->loaded += $this->perPage;
        }
    }

    public function getHasMoreProperty(): bool
    {
        return $this->matchCount > $this->loaded;
    }

    public function install(string $key): void
    {
        if (IconRepository::install($key)) {
            $this->refreshInstalled();

            Notification::make()->title($key.' added')->success()->send();

            return;
        }

        Notification::make()->title('Could not add '.$key)->danger()->send();
    }

    public function uninstall(string $key): void
    {
        // Shipped with the plugin, so there is no storage copy to delete.
        if (IconRepository::isBundled($key)) {
            Notification::make()
                ->title('This icon ships with the plugin')
                ->body('Remove it from the bundled list in config/icon-picker.php instead.')
                ->warning()
                ->send();

            return;
        }

        $usage = $this->usageFor($key);

        // Removing an icon that is on a page would leave a blank space there,
        // so make the consequence visible before it happens.
        if ($usage !== []) {
            $this->confirming = $key;
            $this->confirmingUsage = $usage;

            return;
        }

        $this->remove($key);
    }

    public function confirmRemove(): void
    {
        if ($this->confirming !== null) {
            $this->remove($this->confirming);
        }

        $this->cancelRemove();
    }

    public function cancelRemove(): void
    {
        $this->confirming = null;
        $this->confirmingUsage = [];
    }

    private function remove(string $key): void
    {
        if (IconRepository::uninstall($key)) {
            $this->refreshInstalled();

            Notification::make()->title($key.' removed')->success()->send();

            return;
        }

        Notification::make()->title('Could not remove '.$key)->danger()->send();
    }

    /**
     * Human-readable list of the places an icon is currently used.
     *
     * @return array<int, string>
     */
    public function usageFor(string $key): array
    {
        $key = IconRepository::normalise($key);
        $usage = [];

        foreach ((array) config('icon-picker.required', []) as $required) {
            if (IconRepository::normalise($required) === $key) {
                $usage[] = 'Theme templates (listed in config/icon-picker.php)';

                break;
            }
        }

        foreach (DB::table('pages')->get(['slug', 'title', 'content']) as $page) {
            $content = json_decode((string) $page->content, true);

            if (! is_array($content)) {
                continue;
            }

            $blocks = [];

            foreach ($content as $block) {
                if ($this->blockUses($block['data'] ?? [], $key)) {
                    $blocks[] = $block['type'] ?? 'block';
                }
            }

            if ($blocks !== []) {
                $usage[] = ($page->title ?: $page->slug).' — '.implode(', ', array_unique($blocks));
            }
        }

        return $usage;
    }

    /**
     * @param  array<mixed>  $node
     */
    private function blockUses(array $node, string $key): bool
    {
        foreach ($node as $k => $value) {
            if (is_array($value)) {
                if ($this->blockUses($value, $key)) {
                    return true;
                }

                continue;
            }

            if ($k === 'icon' && is_string($value) && IconRepository::normalise($value) === $key) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{key: string, name: string, set: string, viewBox: string, body: string}>
     */
    public function getResultsProperty(): array
    {
        $out = [];

        foreach (IconRepository::catalogue() as $key => $icon) {
            if (! $this->matches($key)) {
                continue;
            }

            $out[] = $icon + ['key' => $key];

            if (count($out) >= $this->loaded) {
                break;
            }
        }

        return $out;
    }

    /**
     * Whether an icon passes the current search and filter.
     */
    private function matches(string $key): bool
    {
        $query = trim(mb_strtolower($this->query));

        if ($query !== '' && ! str_contains(mb_strtolower($key), $query)) {
            return false;
        }

        $isInstalled = in_array($key, $this->installed, true);

        return match ($this->filter) {
            'in-project' => $isInstalled,
            'available' => ! $isInstalled,
            default => true,
        };
    }

    public function getMatchCountProperty(): int
    {
        $n = 0;

        foreach (array_keys(IconRepository::catalogue()) as $key) {
            if ($this->matches($key)) {
                $n++;
            }
        }

        return $n;
    }

    public function getCatalogueCountProperty(): int
    {
        return count(IconRepository::catalogue());
    }

    public function getCatalogueMissingProperty(): bool
    {
        return IconRepository::catalogue() === [];
    }

    private function refreshInstalled(): void
    {
        $this->installed = array_keys(IconRepository::installed());
    }
}
