<?php

namespace Shazzoo\IconPicker\Forms\Components;

use Filament\Forms\Components\Field;
use Shazzoo\IconPicker\Support\IconRepository;

/**
 * Picks an icon from a searchable grid rather than a name dropdown.
 *
 * The grid itself is client side: the manifest is fetched once per page and
 * filtered in the browser, so typing stays instant across a few thousand icons.
 */
class IconPicker extends Field
{
    protected string $view = 'icon-picker::forms.components.icon-picker';

    /** @var array<int, string> */
    protected array $sets = [];

    protected int $resultLimit = 240;

    /**
     * Restrict the grid to particular sets, e.g. ->sets(['lucide']).
     *
     * @param  array<int, string>  $sets
     */
    public function sets(array $sets): static
    {
        $this->sets = $sets;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getSets(): array
    {
        return $this->sets;
    }

    public function resultLimit(int $limit): static
    {
        $this->resultLimit = $limit;

        return $this;
    }

    public function getResultLimit(): int
    {
        return $this->resultLimit;
    }

    /**
     * The installed icons, inlined once per page by the service provider.
     *
     * Only what the project actually has is offered here; adding more is a
     * deliberate step on the icon library page.
     */
    public static function installedPayload(): string
    {
        $icons = [];

        foreach (IconRepository::installed() as $key => $icon) {
            $icons[] = [
                'key' => $key,
                'name' => $icon['name'],
                'set' => $icon['set'],
                'viewBox' => $icon['viewBox'],
                'body' => $icon['body'],
            ];
        }

        return json_encode($icons, JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    public function getLibraryUrl(): ?string
    {
        return \Shazzoo\IconPicker\Filament\Pages\IconLibrary::getUrl(panel: 'admin');
    }

    public function getInstalledCount(): int
    {
        return IconRepository::installedCount();
    }

    /**
     * Inline SVG for the current value, used for the preview button.
     */
    public function getPreview(): ?array
    {
        $value = $this->getState();

        if (! is_string($value) || $value === '') {
            return null;
        }

        $icon = IconRepository::find($value);

        if (! $icon) {
            return null;
        }

        return [
            'body' => $icon['body'],
            'viewBox' => $icon['viewBox'],
            'filled' => IconRepository::isFilled($value),
            'key' => IconRepository::normalise($value),
        ];
    }
}
