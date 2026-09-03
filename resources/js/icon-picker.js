// Installed icons are inlined once per page by the service provider.
window.__iconPickerInstalled = window.__iconPickerInstalled || [];

function iconPicker({ state, sets, limit }) {
    return {
        state,
        isOpen: false,
        query: '',
        set: '',
        icons: [],
        limit,
        coords: { top: 0, left: 0, width: 0 },

        init() {
            this.icons = window.__iconPickerInstalled;
            this.renderPreview();
            this.$watch('state', () => this.renderPreview());
        },

        get filtered() {
            const q = this.query.trim().toLowerCase();
            return this.icons.filter((i) => {
                if (this.set && i.set !== this.set) return false;
                if (sets.length && !sets.includes(i.set)) return false;
                return !q || i.key.toLowerCase().includes(q);
            });
        },
        get matchCount() { return this.filtered.length; },
        get results() { return this.filtered.slice(0, this.limit); },
        get truncated() { return this.matchCount > this.limit; },
        get availableSets() {
            const all = [...new Set(this.icons.map((i) => i.set))];
            return sets.length ? all.filter((s) => sets.includes(s)) : all;
        },

        toggle() { this.isOpen ? this.close() : this.open(); },

        open() {
            this.position();
            this.isOpen = true;
            this._track = () => this.position();
            window.addEventListener('scroll', this._track, true);
            window.addEventListener('resize', this._track);
            this.$nextTick(() => this.$refs.search?.focus());
        },

        close() {
            this.isOpen = false;
            if (this._track) {
                window.removeEventListener('scroll', this._track, true);
                window.removeEventListener('resize', this._track);
                this._track = null;
            }
        },

        /** Flip above the field when there is not room below it. */
        position() {
            const r = this.$refs.trigger.getBoundingClientRect();
            const panelH = 336;
            const room = window.innerHeight - r.bottom;
            this.coords = {
                top: room < panelH && r.top > room ? Math.max(8, r.top - panelH - 6) : r.bottom + 6,
                left: r.left,
                width: r.width,
            };
        },

        panelStyle() {
            return `top:${this.coords.top}px;left:${this.coords.left}px;width:${this.coords.width}px`;
        },

        choose(key) { this.state = key; this.close(); },
        clear() { this.state = null; },


        svgFor(icon) {
            const paint = icon.set === 'heroicon-s'
                ? 'fill="currentColor"'
                : 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';
            return `<svg viewBox="${icon.viewBox}" ${paint}>${icon.body}</svg>`;
        },

        /** Replaces the server-rendered preview once the manifest is in. */
        renderPreview() {
            const el = this.$refs.preview;
            if (!el) return;
            if (!this.state) { el.innerHTML = ''; return; }
            const icon = this.icons.find((i) => i.key === this.state);
            if (icon) el.innerHTML = this.svgFor(icon);
        },
    };
}
