<?php

namespace Shazzoo\IconPicker;

class Plugin
{
    public static function key(): string
    {
        return 'shazzoo/icon-picker-plugin';
    }

    public static function provider(): string
    {
        return IconPickerServiceProvider::class;
    }
}
