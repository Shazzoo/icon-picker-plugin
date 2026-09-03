@php
    $statePath = $getStatePath();
    $preview = $getPreview();
    $libraryUrl = $getLibraryUrl();
    $hasIcons = $getInstalledCount() > 0;
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        class="sip"
        x-data="iconPicker({
            state: $wire.$entangle('{{ $statePath }}'),
            sets: @js($getSets()),
            limit: @js($getResultLimit()),
        })"
    >
        <button type="button" class="sip-trigger" x-ref="trigger" x-on:click="toggle()">
            {{-- Server-rendered so the current icon shows immediately. --}}
            <span class="sip-preview" x-ref="preview">
                @if ($preview)
                    <svg viewBox="{{ $preview['viewBox'] }}"
                        @if ($preview['filled']) fill="currentColor"
                        @else fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" @endif
                    >{!! $preview['body'] !!}</svg>
                @endif
            </span>

            <span class="sip-label" :data-empty="! state" x-text="state || 'Select an icon…'">
                {{ $preview['key'] ?? 'Select an icon…' }}
            </span>

            <span class="sip-clear" x-show="state" x-on:click.stop="clear()" role="button" tabindex="0"
                x-on:keydown.enter.stop="clear()" title="Clear">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </span>

            <span class="sip-caret">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
            </span>
        </button>

        <template x-teleport="body">
            <div x-show="isOpen" x-cloak class="sip-panel" :style="panelStyle()"
                x-on:click.outside="close()" x-on:keydown.escape.window="close()">
                @if ($hasIcons)
                    <div class="sip-head">
                        <input x-ref="search" x-model="query" type="search" placeholder="Search icons…">
                        <select x-model="set" x-show="availableSets.length > 1">
                            <option value="">All sets</option>
                            <template x-for="s in availableSets" :key="s">
                                <option :value="s" x-text="s"></option>
                            </template>
                        </select>
                    </div>

                    <div class="sip-grid" x-show="results.length">
                        <template x-for="icon in results" :key="icon.key">
                            <button type="button" class="sip-item" :title="icon.key"
                                :data-selected="icon.key === state" x-on:click="choose(icon.key)" x-html="svgFor(icon)">
                            </button>
                        </template>
                    </div>

                    <div class="sip-empty" x-show="!results.length">
                        <p>No installed icon matches that search.</p>
                        @if ($libraryUrl)
                            <a class="sip-btn" href="{{ $libraryUrl }}" target="_blank">Add more icons</a>
                        @endif
                    </div>

                    <div class="sip-foot">
                        <span><span x-text="matchCount"></span> of {{ $getInstalledCount() }} installed</span>
                        @if ($libraryUrl)
                            <a href="{{ $libraryUrl }}" target="_blank">Add more icons</a>
                        @endif
                    </div>
                @else
                    <div class="sip-empty">
                        <p>No icons are installed in this project yet.</p>
                        @if ($libraryUrl)
                            <a class="sip-btn" href="{{ $libraryUrl }}" target="_blank">Add icons</a>
                        @endif
                    </div>
                @endif
            </div>
        </template>
    </div>
</x-dynamic-component>
