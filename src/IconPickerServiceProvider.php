<?php

namespace Shazzoo\IconPicker;

use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\ServiceProvider;
use Shazzoo\IconPicker\Console\BuildIconManifest;
use Shazzoo\IconPicker\Console\BundleIcons;
use Shazzoo\IconPicker\Forms\Components\IconPicker;
use Shazzoo\IconPicker\Support\IconRepository;
use Shazzoo\IconPicker\Console\SyncIcons;

/**
 * Self-contained so this directory can be lifted into a Composer package:
 * it registers its own views, route and command and reaches for nothing
 * outside its own namespace.
 */
class IconPickerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/icon-picker.php', 'icon-picker');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'icon-picker');

        if ($this->app->runningInConsole()) {
            $this->commands([BuildIconManifest::class, BundleIcons::class, SyncIcons::class]);
        }

        $this->registerScript();
        $this->registerBladeIconSet();
    }

    /**
     * Injects the Alpine component once per panel page.
     *
     * Pushing it from the field's own view would only work for pickers present
     * on first render — one added later by a repeater row would find
     * iconPicker() undefined, because Livewire updates do not re-render the
     * layout's stacks. SCRIPTS_BEFORE puts it in ahead of Alpine starting.
     */
    /**
     * Exposes the installed icons to Blade Icons as the "lucide" set, so they
     * can be named anywhere Filament expects an icon (navigation, actions).
     * Only works because the stored files carry their own paint attributes.
     */
    private function registerBladeIconSet(): void
    {
        $path = IconRepository::bundledPath().'/lucide';

        if (! is_dir($path) || ! class_exists(\BladeUI\Icons\Factory::class)) {
            return;
        }

        $this->callAfterResolving(\BladeUI\Icons\Factory::class, function ($factory) use ($path) {
            $factory->add('lucide', ['path' => $path, 'prefix' => 'lucide']);
        });
    }

    private function registerScript(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::SCRIPTS_BEFORE,
            fn (): string => '<script>'
                .'window.__iconPickerInstalled = '.IconPicker::installedPayload().';'
                .file_get_contents(__DIR__.'/../resources/js/icon-picker.js')
                .'</script>',
        );

        // Inlined for the same reason the CSS is hand-written: Filament's
        // stylesheet is precompiled and never sees this plugin's markup.
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => '<style>'.file_get_contents(__DIR__.'/../resources/css/icon-picker.css').'</style>',
        );
    }
}
