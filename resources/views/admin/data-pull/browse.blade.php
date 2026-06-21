<x-layouts.admin header="Tablo Tarayıcı — {{ $connection->name }}">
    <div class="flex flex-col gap-6 max-w-7xl mx-auto w-full" x-data="dataPullBrowser()">
        <div class="flex items-center justify-between">
            <div>
                <a href="{{ route('admin.data-pull.index') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700">
                    <x-lucide-arrow-left class="h-4 w-4" /> Bağlantılar
                </a>
                <h2 class="text-2xl font-bold tracking-tight mt-2">{{ $connection->name }}</h2>
                <p class="text-sm text-muted-foreground">
                    {{ $connection->driverLabel() }} · <span class="font-mono text-xs">{{ $connection->host }}{{ $connection->port ? ':'.$connection->port : '' }}/{{ $connection->database }}</span>
                </p>
            </div>
        </div>

        @if($error)
            <div class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                <p class="font-semibold mb-1">Bağlantı hatası:</p>
                <pre class="whitespace-pre-wrap text-xs">{{ $error }}</pre>
            </div>
        @endif

        <div class="grid grid-cols-12 gap-6">
            {{-- Tablo Listesi --}}
            <aside class="col-span-12 md:col-span-3">
                <x-ui.card>
                    <div class="border-b px-4 py-3 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-slate-800">Tablolar</h3>
                        <span class="text-xs text-slate-400">{{ count($tables) }}</span>
                    </div>
                    @if(empty($tables))
                        <div class="p-4 text-xs text-slate-500">Tablo bulunamadı.</div>
                    @else
                        <div class="p-2 border-b">
                            <input type="text" x-model="filter" placeholder="Tablo ara..." class="w-full rounded-md border border-slate-200 px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-orange-200" />
                        </div>
                        <div class="max-h-[600px] overflow-y-auto p-2">
                            @foreach($tables as $t)
                                <button
                                    type="button"
                                    @click="select('{{ $t['name'] }}', {{ $t['schema'] ? "'".$t['schema']."'" : 'null' }})"
                                    x-show="filter === '' || '{{ strtolower($t['name']) }}'.includes(filter.toLowerCase())"
                                    ::class="active === '{{ ($t['schema'] ?? '').'::'.$t['name'] }}' ? 'bg-orange-50 text-orange-700' : 'hover:bg-slate-50 text-slate-700'"
                                    class="block w-full text-left px-2 py-1.5 rounded text-xs font-mono truncate">
                                    @if($t['type'] === 'view')
                                        <x-lucide-eye class="inline h-3 w-3 text-violet-400" />
                                    @else
                                        <x-lucide-table-2 class="inline h-3 w-3 text-slate-400" />
                                    @endif
                                    @if($t['schema'])<span class="text-slate-400">{{ $t['schema'] }}.</span>@endif{{ $t['name'] }}
                                </button>
                            @endforeach
                        </div>
                    @endif
                </x-ui.card>
            </aside>

            {{-- Önizleme --}}
            <main class="col-span-12 md:col-span-9">
                <x-ui.card>
                    <div class="border-b px-4 py-3 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-800" x-text="selectedTable ? (selectedSchema ? selectedSchema + '.' + selectedTable : selectedTable) : 'Bir tablo seçin'">Bir tablo seçin</h3>
                            <p class="text-xs text-slate-500" x-show="preview && preview.total !== null && preview.total !== undefined" x-cloak>
                                Yaklaşık <span x-text="preview.total"></span> satır · İlk <span x-text="limit"></span> gösteriliyor
                            </p>
                        </div>
                        <div class="flex items-center gap-2" x-show="selectedTable" x-cloak>
                            <select x-model="limit" @change="reloadPreview" class="rounded-md border border-slate-200 px-2 py-1.5 text-xs">
                                <option value="50">50 satır</option>
                                <option value="100">100 satır</option>
                                <option value="500">500 satır</option>
                                <option value="1000">1000 satır</option>
                            </select>
                            <a :href="exportUrl" class="inline-flex items-center gap-1 rounded-md bg-emerald-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-600">
                                <x-lucide-download class="h-3.5 w-3.5" /> CSV İndir
                            </a>
                        </div>
                    </div>

                    <div class="p-4">
                        <template x-if="loading">
                            <div class="flex items-center justify-center py-12 text-sm text-slate-400">
                                <x-lucide-loader-2 class="h-5 w-5 animate-spin mr-2" /> Yükleniyor...
                            </div>
                        </template>

                        <template x-if="!loading && !preview && !error">
                            <div class="text-center py-12 text-sm text-slate-400">
                                <x-lucide-mouse-pointer-click class="h-8 w-8 mx-auto text-slate-300 mb-2" />
                                Soldaki listeden bir tablo seçin.
                            </div>
                        </template>

                        <template x-if="error">
                            <div class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                                <pre class="whitespace-pre-wrap text-xs" x-text="error"></pre>
                            </div>
                        </template>

                        <template x-if="!loading && preview">
                            <div class="overflow-x-auto">
                                <table class="w-full text-xs">
                                    <thead class="bg-slate-50 text-left font-semibold text-slate-600 sticky top-0">
                                        <tr>
                                            <template x-for="col in preview.columns" :key="col.name">
                                                <th class="px-2 py-2 whitespace-nowrap">
                                                    <div x-text="col.name" class="font-mono"></div>
                                                    <div x-text="col.type" class="text-[10px] font-normal text-slate-400"></div>
                                                </th>
                                            </template>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y">
                                        <template x-for="(row, idx) in preview.rows" :key="idx">
                                            <tr class="hover:bg-slate-50">
                                                <template x-for="col in preview.columns" :key="col.name">
                                                    <td class="px-2 py-1.5 font-mono align-top text-slate-700" x-text="formatCell(row[col.name])"></td>
                                                </template>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                                <p class="mt-3 text-xs text-slate-400" x-show="preview.rows.length === 0">Tabloda satır yok.</p>
                            </div>
                        </template>
                    </div>
                </x-ui.card>
            </main>
        </div>
    </div>

    @push('scripts')
    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
    function dataPullBrowser() {
        return {
            filter: '',
            active: null,
            selectedTable: null,
            selectedSchema: null,
            limit: 100,
            loading: false,
            preview: null,
            error: null,

            get exportUrl() {
                if (!this.selectedTable) return '#';
                const params = new URLSearchParams({ table: this.selectedTable });
                if (this.selectedSchema) params.append('schema', this.selectedSchema);
                return '{{ route('admin.data-pull.export', $connection) }}?' + params.toString();
            },

            async select(table, schema) {
                this.selectedTable = table;
                this.selectedSchema = schema;
                this.active = (schema || '') + '::' + table;
                await this.reloadPreview();
            },

            async reloadPreview() {
                if (!this.selectedTable) return;
                this.loading = true;
                this.preview = null;
                this.error = null;
                try {
                    const res = await fetch('{{ route('admin.data-pull.preview', $connection) }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                        body: JSON.stringify({ table: this.selectedTable, schema: this.selectedSchema, limit: parseInt(this.limit) }),
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.preview = data.data;
                    } else {
                        this.error = data.message || 'Bilinmeyen hata';
                    }
                } catch (e) {
                    this.error = e.message;
                }
                this.loading = false;
            },

            formatCell(value) {
                if (value === null || value === undefined) return '∅';
                if (typeof value === 'object') return JSON.stringify(value);
                const s = String(value);
                return s.length > 200 ? s.substring(0, 200) + '…' : s;
            },
        };
    }
    </script>
    @endpush
</x-layouts.admin>
