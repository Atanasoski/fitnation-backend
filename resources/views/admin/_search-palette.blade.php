{{--
    Global search (⌘K / Ctrl-K): users by name or email with their chips,
    partners by name, admin pages by title. Results come from admin.search;
    ↑↓ move, Enter opens, Esc closes.
--}}
<div data-global-search class="hidden xl:block"
    x-data="{
        open: false,
        q: '',
        results: [],
        index: 0,
        loading: false,
        timer: null,
        controller: null,
        endpoint: @js(route('admin.search')),
        shortcut: /Mac|iPhone|iPad/.test(navigator.platform) ? '⌘K' : 'Ctrl K',
        show() {
            this.open = true;
            this.$nextTick(() => this.$refs.input.focus());
        },
        hide() {
            this.open = false;
        },
        search() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.fetch(), 150);
        },
        async fetch() {
            const term = this.q.trim();
            this.controller?.abort();
            if (term === '') {
                this.results = [];
                this.index = 0;
                return;
            }
            this.controller = new AbortController();
            this.loading = true;
            try {
                const response = await fetch(this.endpoint + '?q=' + encodeURIComponent(term), {
                    headers: { Accept: 'application/json' },
                    signal: this.controller.signal,
                });
                const data = await response.json();
                this.results = [
                    ...data.users.map(u => ({ key: 'user-' + u.id, type: 'User', label: u.name, sub: [u.email, u.partner].filter(Boolean).join(' · '), url: u.url, chips: u.chips })),
                    ...data.partners.map(p => ({ key: 'partner-' + p.id, type: 'Partner', label: p.name, sub: '', url: p.url, chips: '' })),
                    ...data.pages.map(p => ({ key: 'page-' + p.url, type: 'Page', label: p.title, sub: p.section ?? '', url: p.url, chips: '' })),
                ];
                this.index = 0;
            } catch (e) {
                if (e.name !== 'AbortError') this.results = [];
            } finally {
                this.loading = false;
            }
        },
        move(step) {
            if (!this.results.length) return;
            this.index = (this.index + step + this.results.length) % this.results.length;
            this.$nextTick(() => this.$refs.list.querySelector('[data-active]')?.scrollIntoView({ block: 'nearest' }));
        },
        choose(result) {
            if (result) window.location.href = result.url;
        },
    }"
    @keydown.window.prevent.meta.k="show()"
    @keydown.window.prevent.ctrl.k="show()"
    @keydown.window.escape="hide()">

    <button type="button" @click="show()"
        class="flex h-11 w-[430px] items-center justify-between rounded-lg border border-gray-200 bg-transparent px-4 text-sm text-gray-400 shadow-theme-xs hover:border-gray-300 dark:border-gray-800 dark:bg-white/3 dark:text-white/30">
        <span class="flex items-center gap-3">
            <svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" aria-hidden="true">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M3.04175 9.37363C3.04175 5.87693 5.87711 3.04199 9.37508 3.04199C12.8731 3.04199 15.7084 5.87693 15.7084 9.37363C15.7084 12.8703 12.8731 15.7053 9.37508 15.7053C5.87711 15.7053 3.04175 12.8703 3.04175 9.37363ZM9.37508 1.54199C5.04902 1.54199 1.54175 5.04817 1.54175 9.37363C1.54175 13.6991 5.04902 17.2053 9.37508 17.2053C11.2674 17.2053 13.003 16.5344 14.357 15.4176L17.177 18.238C17.4699 18.5309 17.9448 18.5309 18.2377 18.238C18.5306 17.9451 18.5306 17.4703 18.2377 17.1774L15.418 14.3573C16.5365 13.0033 17.2084 11.2669 17.2084 9.37363C17.2084 5.04817 13.7011 1.54199 9.37508 1.54199Z" />
            </svg>
            Search users, partners, pages…
        </span>
        <kbd class="rounded-lg border border-gray-200 bg-gray-50 px-[7px] py-[4.5px] text-xs text-gray-500 dark:border-gray-800 dark:bg-white/3 dark:text-gray-400" x-text="shortcut">⌘K</kbd>
    </button>

    <div x-show="open" x-cloak x-transition.opacity
        class="fixed inset-0 z-[100000] flex items-start justify-center bg-gray-900/40 p-4 pt-[12vh]"
        @click.self="hide()" role="dialog" aria-modal="true" aria-label="Search">
        <div class="w-full max-w-xl overflow-hidden rounded-2xl bg-white shadow-theme-xl dark:bg-gray-900">
            <input x-ref="input" x-model="q" @input="search()" type="text" autocomplete="off" spellcheck="false"
                @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)" @keydown.enter.prevent="choose(results[index])"
                placeholder="Search users by name or email, partners, pages…" aria-label="Search users, partners, pages"
                class="w-full border-0 border-b border-gray-200 px-4 py-3.5 text-base text-gray-800 outline-none placeholder:text-gray-400 focus:ring-0 dark:border-gray-800 dark:bg-gray-900 dark:text-white/90" />
            <ul x-ref="list" class="max-h-[50vh] overflow-y-auto py-1" role="listbox">
                <template x-for="(result, i) in results" :key="result.key">
                    <li role="option" :aria-selected="i === index">
                        <a :href="result.url" @mouseenter="index = i" :data-active="i === index ? '' : null"
                            class="flex w-full items-center gap-3 px-4 py-2 text-left"
                            :class="i === index ? 'bg-brand-50 dark:bg-brand-500/10' : ''">
                            <span class="w-14 shrink-0 text-theme-xs uppercase text-gray-400" x-text="result.type"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-gray-900 dark:text-white" x-text="result.label"></span>
                                <span x-show="result.sub" class="block truncate text-theme-xs text-gray-500 dark:text-gray-400" x-text="result.sub"></span>
                            </span>
                            <span x-show="result.chips" class="flex shrink-0 gap-1" x-html="result.chips"></span>
                        </a>
                    </li>
                </template>
                <li x-show="q.trim() && !loading && !results.length" class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">No matches.</li>
                <li x-show="!q.trim()" class="px-4 py-3 text-sm text-gray-400">Type a name or email. ↑↓ to move, Enter to open, Esc to close.</li>
            </ul>
        </div>
    </div>
</div>
