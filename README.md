# Icon Picker

A plugin for [Content Studio Core](https://github.com/Shazzoo/content-studio-core)
that adds icons to a site: a searchable picker field for the admin, an icon
library page to install new icons, and a Blade Icons set so icon names work
anywhere Filament or a theme expects one.

Icons are plain SVG files, read from two places:

- **bundled** — committed inside this package (`resources/icons`), so a small
  starter set is present in every environment without touching writable storage.
- **installed** — written to a configured disk (`icon-picker.disk`), the way
  media is, so icons added from the admin survive a deploy.

## Installation

```bash
composer require shazzoo/icon-picker-plugin
```

Then activate **Icon Picker** in the admin under Plugins.

## What it gives you

| | |
|---|---|
| **Form field** | `Shazzoo\IconPicker\Forms\Components\IconPicker` — a client-side filtered grid, fast across thousands of icons |
| **Icon library** | An admin page to browse the catalogue and install or remove icons |
| **Blade Icons set** | Bundled icons registered as the `lucide` set, usable as `lucide-squirrel` etc. |
| **Repository** | `Shazzoo\IconPicker\Support\IconRepository` for rendering icons from a theme |

## Configuration

Publish nothing to get started; the package config is merged under
`icon-picker`. A site can add its own `config/icon-picker.php` to override:

```php
return [
    'disk' => env('ICON_PICKER_DISK', 'public'),
    'directory' => env('ICON_PICKER_DIRECTORY', 'icons'),

    // Icons rendered from templates rather than from stored content;
    // icons:sync keeps these installed.
    'required' => ['sun', 'moon', 'check'],
];
```

## Commands

| | |
|---|---|
| `icons:sync` | Installs every icon referenced by content plus the `required` list |
| `icons:bundle` | Re-copies the `bundled` list into `resources/icons` (package development) |
| `icons:build` | Rebuilds `resources/icons.json` from `node_modules/lucide-static` (package development) |

`icons:build` reads the icon sources from the site's `node_modules`, so it
needs `lucide-static` installed there.

## Using icons in a theme

```php
use Shazzoo\IconPicker\Support\IconRepository;

IconRepository::find('lucide:check');
```
