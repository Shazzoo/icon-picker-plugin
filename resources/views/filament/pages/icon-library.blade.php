<x-filament-panels::page>
    @if ($this->catalogueMissing)
        <div class="sip-lib-note">
            <p><strong>The icon catalogue has not been built.</strong></p>
            <p>Run <code>php artisan icons:build</code> to generate it. Icons already installed in this
                project keep working — the catalogue is only needed to add new ones.</p>
        </div>
    @else
        <div class="sip-lib">
            <div class="sip-lib-bar">
                <input type="search" wire:model.live.debounce.300ms="query"
                    placeholder="Search {{ number_format($this->catalogueCount) }} icons…">

                {{-- "Added/not added" reads ambiguously next to the Add badge, so
                     these say where the icon is rather than what was done to it. --}}
                <div class="sip-lib-filter" role="group" aria-label="Filter icons">
                    @foreach ([
                        'all' => 'All icons',
                        'in-project' => 'In this project',
                        'available' => 'Available to add',
                    ] as $value => $label)
                        <button type="button" wire:click="$set('filter', '{{ $value }}')"
                            aria-pressed="{{ $filter === $value ? 'true' : 'false' }}">{{ $label }}</button>
                    @endforeach
                </div>

                <span class="sip-lib-count">
                    {{ number_format(count($this->results)) }} of {{ number_format($this->matchCount) }}
                    &middot; {{ count($installed) }} installed
                </span>
            </div>

            <div class="sip-lib-grid" wire:loading.class="sip-lib-busy">
                @foreach ($this->results as $icon)
                    @php $isInstalled = in_array($icon['key'], $installed, true); @endphp

                    {{-- The whole card is the control; the badge is the hover affordance. --}}
                    <button
                        type="button"
                        class="sip-lib-item"
                        data-installed="{{ $isInstalled ? 'true' : 'false' }}"
                        wire:key="{{ $icon['key'] }}"
                        wire:click="{{ $isInstalled ? 'uninstall' : 'install' }}('{{ $icon['key'] }}')"
                        wire:loading.attr="disabled"
                        title="{{ $isInstalled ? 'Remove '.$icon['key'] : 'Add '.$icon['key'] }}"
                        aria-label="{{ $isInstalled ? 'Remove '.$icon['key'] : 'Add '.$icon['key'] }}"
                    >
                        <span class="sip-lib-badge" aria-hidden="true">
                            @if ($isInstalled)
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                    stroke-linecap="round"><path d="M5 12h14"/></svg>
                            @else
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                    stroke-linecap="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                            @endif
                        </span>

                        <span class="sip-lib-art">
                            <svg viewBox="{{ $icon['viewBox'] }}" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">{!! $icon['body'] !!}</svg>
                        </span>

                        <span class="sip-lib-name">{{ $icon['name'] }}</span>
                    </button>
                @endforeach

                {{-- Inside the scroll container and spanning the full row, so it
                     comes into view only once you reach the end of the grid. --}}
                @if ($this->hasMore)
                    <div class="sip-lib-foot">
                        <button type="button" class="sip-lib-load" wire:click="loadMore"
                            wire:loading.attr="disabled" wire:target="loadMore">
                            <span wire:loading.remove wire:target="loadMore">Load more</span>
                            <span wire:loading wire:target="loadMore">Loading…</span>
                        </button>

                        <span class="sip-lib-progress">
                            {{ number_format(count($this->results)) }} of {{ number_format($this->matchCount) }}
                        </span>
                    </div>
                @endif
            </div>

            @if (! count($this->results))
                <p class="sip-lib-more">Nothing matches that search.</p>
            @endif
        </div>
    @endif

    @if ($confirming)
        <div class="sip-confirm-backdrop" wire:click.self="cancelRemove" x-on:keydown.escape.window="$wire.cancelRemove()">
            <div class="sip-confirm" role="alertdialog" aria-modal="true" aria-labelledby="sip-confirm-title">
                <h2 id="sip-confirm-title">Remove {{ $confirming }}?</h2>

                <p>This icon is still in use. Removing it leaves an empty space wherever it appears,
                    until another icon is chosen.</p>

                <ul>
                    @foreach ($confirmingUsage as $where)
                        <li>{{ $where }}</li>
                    @endforeach
                </ul>

                <div class="sip-confirm-actions">
                    <button type="button" class="sip-confirm-cancel" wire:click="cancelRemove">Cancel</button>
                    <button type="button" class="sip-confirm-go" wire:click="confirmRemove">Remove anyway</button>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
