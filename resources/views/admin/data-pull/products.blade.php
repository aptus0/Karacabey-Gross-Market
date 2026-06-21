<x-layouts.admin header="Ürün Veri Çekme">
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6" x-data="productDataPull()">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight text-slate-950">Ürün Veri Çekme</h2>
                <p class="text-sm text-slate-500">Kayıtlı veri bağlantılarından ürün tablosunu okuyun, kolonları eşleştirin ve kataloğa aktarın.</p>
            </div>
            <a href="{{ route('admin.data-pull.index') }}" class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">
                <x-lucide-database class="h-4 w-4" /> Bağlantılar
            </a>
        </div>

        @if($connections->isEmpty())
            <x-ui.card>
                <div class="p-10 text-center">
                    <x-lucide-database class="mx-auto mb-3 h-12 w-12 text-slate-300" />
                    <h3 class="font-bold text-slate-800">Önce veri bağlantısı ekleyin</h3>
                    <p class="mt-1 text-sm text-slate-500">SQL Server, MySQL, PostgreSQL veya SQLite bağlantısı oluşturduktan sonra ürünleri buradan çekebilirsiniz.</p>
                    <a href="{{ route('admin.data-pull.create') }}" class="mt-4 inline-flex items-center gap-2 rounded-md bg-orange-500 px-4 py-2 text-sm font-bold text-white hover:bg-orange-600">
                        <x-lucide-plus class="h-4 w-4" /> Yeni Bağlantı
                    </a>
                </div>
            </x-ui.card>
        @else
            <div class="grid gap-6 lg:grid-cols-[360px_minmax(0,1fr)]">
                <x-ui.card class="self-start">
                    <div class="border-b px-6 py-4">
                        <h3 class="text-sm font-bold text-slate-900">Kaynak</h3>
                    </div>
                    <div class="space-y-4 p-6">
                        <div class="space-y-1.5">
                            <x-ui.label for="connection_id">Bağlantı</x-ui.label>
                            <select id="connection_id" x-model="connectionId" @change="loadTables()" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm">
                                <option value="">Seçin</option>
                                @foreach($connections as $connection)
                                    <option value="{{ $connection->id }}">{{ $connection->name }} · {{ $connection->driverLabel() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="space-y-1.5">
                            <x-ui.label for="source_table">Tablo</x-ui.label>
                            <select id="source_table" x-model="tableKey" @change="inspectTable()" :disabled="tables.length === 0" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm disabled:bg-slate-50">
                                <option value="">Tablo seçin</option>
                                <template x-for="table in tables" :key="tableKeyFor(table)">
                                    <option :value="tableKeyFor(table)" x-text="table.schema ? table.schema + '.' + table.name : table.name"></option>
                                </template>
                            </select>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                            <label class="flex items-start gap-2 rounded-md border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-800">
                                <input type="checkbox" x-model="autoSyncEnabled" class="mt-0.5 rounded border-emerald-300">
                                Her 5 dakikada arka planda ürün/fiyat/stok senkronu yap
                            </label>
                            <label class="flex items-start gap-2 rounded-md border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600">
                                <input type="checkbox" x-model="priceIsCents" class="mt-0.5 rounded border-slate-300">
                                Fiyat kolonu kuruş olarak geliyor
                            </label>
                            <label class="flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                                <input type="checkbox" x-model="deactivateMissing" class="mt-0.5 rounded border-amber-300">
                                Kaynakta olmayan external_ref ürünleri pasif et
                            </label>
                        </div>

                        <div class="space-y-1.5">
                            <x-ui.label for="limit">Limit</x-ui.label>
                            <input id="limit" type="number" min="1" max="50000" x-model.number="limit" class="h-10 w-full rounded-md border border-slate-200 px-3 text-sm">
                        </div>

                        <div class="space-y-1.5">
                            <x-ui.label for="allowed_client_ips">İzinli dış admin IP/CIDR</x-ui.label>
                            <textarea id="allowed_client_ips" rows="2" x-model="allowedClientIps" placeholder="Örn: 195.87.234.135 veya 195.87.234.0/24" class="w-full rounded-md border border-slate-200 px-3 py-2 text-xs"></textarea>
                            <p class="text-[11px] text-slate-400">Yerel ağ IP'leri otomatik izinlidir. Dışarıdan çalışan admin için burayı kullan.</p>
                        </div>

                        <div class="space-y-1.5">
                            <x-ui.label for="allowed_source_ips">İzinli public kaynak IP/CIDR</x-ui.label>
                            <textarea id="allowed_source_ips" rows="2" x-model="allowedSourceIps" placeholder="DB sunucusu public IP ise yazın. Yerel ağ kaynakları otomatik izinlidir." class="w-full rounded-md border border-slate-200 px-3 py-2 text-xs"></textarea>
                        </div>

                        <div x-show="error" x-cloak class="rounded-md border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700" x-text="error"></div>
                        <div x-show="result" x-cloak class="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-700">
                            <div class="font-bold" x-text="result?.message"></div>
                            <div class="mt-1" x-show="result?.stats">
                                İşlenen: <span x-text="result?.stats?.processed"></span>,
                                yeni: <span x-text="result?.stats?.created"></span>,
                                güncel: <span x-text="result?.stats?.updated"></span>,
                                pasif: <span x-text="result?.stats?.deactivated"></span>
                            </div>
                        </div>
                    </div>
                </x-ui.card>

                <div class="flex min-w-0 flex-col gap-6">
                    <x-ui.card>
                        <div class="border-b px-6 py-4">
                            <h3 class="text-sm font-bold text-slate-900">Kolon Eşleştirme</h3>
                            <p class="mt-1 text-xs text-slate-500">Ürün adı zorunlu. Güncelleme için external_ref, barkod veya SKU alanlarından en az biri dolu olmalı.</p>
                        </div>
                        <div class="grid gap-4 p-6 sm:grid-cols-2 lg:grid-cols-3">
                            <template x-for="field in fields" :key="field.key">
                                <div class="space-y-1.5">
                                    <label class="text-xs font-bold text-slate-600" :for="'map_' + field.key">
                                        <span x-text="field.label"></span>
                                        <span x-show="field.required" class="text-rose-500">*</span>
                                    </label>
                                    <select :id="'map_' + field.key" x-model="mapping[field.key]" class="h-10 w-full rounded-md border border-slate-200 bg-white px-3 text-sm">
                                        <option value="">Boş</option>
                                        <template x-for="column in columns" :key="field.key + '_' + column.name">
                                            <option :value="column.name" x-text="column.name"></option>
                                        </template>
                                    </select>
                                </div>
                            </template>
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-3 border-t px-6 py-4">
                            <p class="text-xs text-slate-500" x-show="columns.length > 0">
                                <span x-text="columns.length"></span> kolon bulundu.
                            </p>
                            <button type="button" @click="importProducts()" :disabled="!canImport || importing" class="inline-flex h-10 items-center gap-2 rounded-md bg-orange-500 px-4 text-sm font-bold text-white hover:bg-orange-600 disabled:cursor-not-allowed disabled:opacity-50">
                                <x-lucide-download class="h-4 w-4" />
                                <span x-show="!importing">Ürünleri Aktar</span>
                                <span x-show="importing">Aktarılıyor...</span>
                            </button>
                            <button type="button" @click="saveSettings()" :disabled="!canImport || savingSettings" class="inline-flex h-10 items-center gap-2 rounded-md border border-slate-200 bg-white px-4 text-sm font-bold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50">
                                <x-lucide-save class="h-4 w-4" />
                                <span x-show="!savingSettings">Otomatik Ayarı Kaydet</span>
                                <span x-show="savingSettings">Kaydediliyor...</span>
                            </button>
                        </div>
                    </x-ui.card>

                    <x-ui.card>
                        <div class="border-b px-6 py-4">
                            <h3 class="text-sm font-bold text-slate-900">Önizleme</h3>
                        </div>
                        <div class="p-6">
                            <div x-show="loading" x-cloak class="flex items-center justify-center py-12 text-sm text-slate-500">
                                <x-lucide-loader-2 class="mr-2 h-5 w-5 animate-spin" /> Yükleniyor...
                            </div>
                            <div x-show="!loading && rows.length === 0" class="py-12 text-center text-sm text-slate-400">
                                Bağlantı ve tablo seçince ilk satırlar burada görünür.
                            </div>
                            <div x-show="!loading && rows.length > 0" x-cloak class="overflow-x-auto">
                                <table class="w-full text-xs">
                                    <thead class="bg-slate-50 text-left font-bold text-slate-600">
                                        <tr>
                                            <template x-for="column in columns" :key="'h_' + column.name">
                                                <th class="whitespace-nowrap px-2 py-2">
                                                    <div class="font-mono" x-text="column.name"></div>
                                                    <div class="text-[10px] font-normal text-slate-400" x-text="column.type"></div>
                                                </th>
                                            </template>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y">
                                        <template x-for="(row, idx) in rows" :key="idx">
                                            <tr class="hover:bg-slate-50">
                                                <template x-for="column in columns" :key="'c_' + idx + '_' + column.name">
                                                    <td class="max-w-[240px] truncate px-2 py-1.5 font-mono text-slate-700" x-text="formatCell(row[column.name])"></td>
                                                </template>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </x-ui.card>
                </div>
            </div>
        @endif
    </div>

    @push('scripts')
        <script nonce="{{ request()->attributes->get('csp_nonce') }}">
            function productDataPull() {
                return {
                    connectionSettings: @json($connections->mapWithKeys(fn ($connection) => [$connection->id => data_get($connection->extra ?? [], 'product_sync', [])])),
                    connectionId: '',
                    tableKey: '',
                    tables: [],
                    columns: [],
                    rows: [],
                    mapping: {},
                    loading: false,
                    importing: false,
                    savingSettings: false,
                    error: null,
                    result: null,
                    limit: 5000,
                    priceIsCents: false,
                    deactivateMissing: false,
                    autoSyncEnabled: false,
                    allowedClientIps: '',
                    allowedSourceIps: '',
                    fields: [
                        { key: 'external_ref', label: 'External Ref' },
                        { key: 'name', label: 'Ürün Adı', required: true },
                        { key: 'sku', label: 'SKU' },
                        { key: 'barcode', label: 'Barkod' },
                        { key: 'brand', label: 'Marka' },
                        { key: 'description', label: 'Açıklama' },
                        { key: 'price', label: 'Fiyat' },
                        { key: 'compare_at_price', label: 'Eski Fiyat' },
                        { key: 'stock', label: 'Stok' },
                        { key: 'image_url', label: 'Görsel URL' },
                        { key: 'category', label: 'Kategori' },
                        { key: 'active', label: 'Aktif' },
                    ],
                    get selectedTable() {
                        if (!this.tableKey) return null;
                        const [schema, name] = this.tableKey.split('::');
                        return { schema: schema || null, name };
                    },
                    get canImport() {
                        return Boolean(this.connectionId && this.selectedTable && this.mapping.name);
                    },
                    tableKeyFor(table) {
                        return (table.schema || '') + '::' + table.name;
                    },
                    async loadTables() {
                        this.tableKey = '';
                        this.tables = [];
                        this.columns = [];
                        this.rows = [];
                        this.mapping = {};
                        this.result = null;
                        this.applySavedSettings();
                        if (!this.connectionId) return;
                        await this.inspect();
                    },
                    applySavedSettings() {
                        const settings = this.connectionSettings[this.connectionId] || {};
                        this.autoSyncEnabled = Boolean(settings.enabled);
                        this.limit = settings.limit || 5000;
                        this.priceIsCents = Boolean(settings.price_is_cents);
                        this.deactivateMissing = Boolean(settings.deactivate_missing);
                        this.allowedClientIps = settings.allowed_client_ips || '';
                        this.allowedSourceIps = settings.allowed_source_ips || '';
                        this.mapping = { ...(settings.mapping || {}) };
                        if (settings.table) {
                            this.tableKey = (settings.schema || '') + '::' + settings.table;
                        }
                    },
                    async inspectTable() {
                        this.columns = [];
                        this.rows = [];
                        const saved = this.connectionSettings[this.connectionId]?.mapping || {};
                        this.mapping = { ...saved };
                        this.result = null;
                        if (!this.selectedTable) return;
                        await this.inspect();
                    },
                    async inspect() {
                        this.loading = true;
                        this.error = null;
                        try {
                            const body = { connection_id: this.connectionId };
                            if (this.selectedTable) {
                                body.table = this.selectedTable.name;
                                body.schema = this.selectedTable.schema;
                            }
                            const res = await fetch('{{ route('admin.data-pull.products.inspect') }}', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                                body: JSON.stringify(body),
                            });
                            const data = await res.json();
                            if (!res.ok || !data.success) throw new Error(data.message || 'Veri okunamadı.');
                            this.tables = data.tables || [];
                            this.columns = data.columns || [];
                            this.rows = data.rows || [];
                            this.mapping = { ...this.mapping, ...(data.suggested_mapping || {}) };
                        } catch (e) {
                            this.error = e.message;
                        }
                        this.loading = false;
                    },
                    async importProducts() {
                        if (!this.canImport) return;
                        this.importing = true;
                        this.error = null;
                        this.result = null;
                        try {
                            const res = await fetch('{{ route('admin.data-pull.products.import') }}', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                                body: JSON.stringify({
                                    connection_id: this.connectionId,
                                    table: this.selectedTable.name,
                                    schema: this.selectedTable.schema,
                                    mapping: this.mapping,
                                    limit: this.limit,
                                    price_is_cents: this.priceIsCents,
                                    deactivate_missing: this.deactivateMissing,
                                }),
                            });
                            const data = await res.json();
                            if (!res.ok || !data.success) throw new Error(data.message || 'Aktarım yapılamadı.');
                            this.result = data;
                        } catch (e) {
                            this.error = e.message;
                        }
                        this.importing = false;
                    },
                    async saveSettings() {
                        if (!this.canImport) return;
                        this.savingSettings = true;
                        this.error = null;
                        this.result = null;
                        try {
                            const res = await fetch('{{ route('admin.data-pull.products.settings') }}', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                                body: JSON.stringify({
                                    connection_id: this.connectionId,
                                    enabled: this.autoSyncEnabled,
                                    table: this.selectedTable.name,
                                    schema: this.selectedTable.schema,
                                    mapping: this.mapping,
                                    limit: this.limit,
                                    price_is_cents: this.priceIsCents,
                                    deactivate_missing: this.deactivateMissing,
                                    allowed_client_ips: this.allowedClientIps,
                                    allowed_source_ips: this.allowedSourceIps,
                                }),
                            });
                            const data = await res.json();
                            if (!res.ok || !data.success) throw new Error(data.message || 'Ayar kaydedilemedi.');
                            this.connectionSettings[this.connectionId] = data.settings || {};
                            this.result = data;
                        } catch (e) {
                            this.error = e.message;
                        }
                        this.savingSettings = false;
                    },
                    formatCell(value) {
                        if (value === null || value === undefined) return '∅';
                        if (typeof value === 'object') return JSON.stringify(value);
                        const text = String(value);
                        return text.length > 160 ? text.slice(0, 160) + '...' : text;
                    },
                };
            }
        </script>
    @endpush
</x-layouts.admin>
