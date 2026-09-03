<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Required icons
    |--------------------------------------------------------------------------
    |
    | Icons referenced from templates rather than from stored content. Nothing
    | scans Blade files, so anything hardcoded in a layout has to be listed
    | here for icons:sync to keep it installed.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Icons added from the admin are written here rather than into the plugin,
    | so they survive a deploy the same way media does. Point this at a disk
    | whose root is shared between releases.
    |
    */

    'disk' => env('ICON_PICKER_DISK', 'public'),

    'directory' => env('ICON_PICKER_DIRECTORY', 'icons'),

    'required' => [],

    /*
    |--------------------------------------------------------------------------
    | Bundled icons
    |--------------------------------------------------------------------------
    |
    | Shipped inside the plugin and committed, so they are present in every
    | environment without touching writable storage. A general-purpose starter
    | set plus whatever the plugin itself needs. Run icons:bundle after editing
    | this list. The app's own 'required' list is merged with these rather than
    | replacing them.
    |
    */

    'bundled' => [
        'lucide:squirrel', // icon library page, in the admin navigation

        // A small, broadly useful starting point. Anything else is added from
        // the icon library and lives on the configured disk.
        'lucide:arrow-right', 'lucide:chevron-down', 'lucide:external-link',
        'lucide:check', 'lucide:x', 'lucide:plus', 'lucide:minus',
        'lucide:search', 'lucide:menu', 'lucide:settings',
        'lucide:info', 'lucide:star', 'lucide:mail', 'lucide:user',

        // Theme toggle: rendered from a template, so it must exist everywhere
        // without depending on storage being populated.
        'lucide:sun', 'lucide:moon',
    ],

];
