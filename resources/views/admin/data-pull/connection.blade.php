<x-layouts.admin header="{{ $connection->exists ? 'Bağlantı Düzenle' : 'Yeni Bağlantı' }}">
    <div class="flex flex-col gap-6 max-w-3xl mx-auto w-full" x-data="{ driver: '{{ old('driver', $connection->driver ?? 'mysql') }}' }">
        <div>
            <a href="{{ route('admin.data-pull.index') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700">
                <x-lucide-arrow-left class="h-4 w-4" /> Bağlantılar
            </a>
            <h2 class="text-2xl font-bold tracking-tight mt-2">{{ $connection->exists ? 'Bağlantıyı Düzenle' : 'Yeni Veri Bağlantısı' }}</h2>
            <p class="text-sm text-muted-foreground">Dış veritabanına read-only erişim için bilgileri girin.</p>
        </div>

        @if($errors->any())
            <div class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                <ul class="list-disc pl-5">
                    @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ $connection->exists ? route('admin.data-pull.update', $connection) : route('admin.data-pull.store') }}">
            @csrf
            @if($connection->exists)@method('PUT')@endif

            <x-ui.card>
                <div class="p-6 grid gap-6">
                    <div class="space-y-2">
                        <x-ui.label for="name">Bağlantı Adı *</x-ui.label>
                        <x-ui.input id="name" name="name" value="{{ old('name', $connection->name) }}" placeholder="Örn: Erkur SQL Sunucu" required />
                        <p class="text-[0.8rem] text-muted-foreground">Listede ve menülerde görünecek isim.</p>
                    </div>

                    <div class="space-y-2">
                        <x-ui.label for="driver">Sürücü *</x-ui.label>
                        <select id="driver" name="driver" x-model="driver" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm" required>
                            @foreach($availableDrivers as $d)
                                <option value="{{ $d }}">{{ ['mysql'=>'MySQL / MariaDB','pgsql'=>'PostgreSQL','sqlsrv'=>'SQL Server','dblib'=>'SQL Server (FreeTDS)','sqlite'=>'SQLite'][$d] ?? strtoupper($d) }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- SQLite hariç tüm sürücüler için host/port/user/pass --}}
                    <div x-show="driver !== 'sqlite'" x-cloak class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <x-ui.label for="host">Sunucu (Host) *</x-ui.label>
                            <x-ui.input id="host" name="host" value="{{ old('host', $connection->host) }}" placeholder="192.168.1.100 veya db.example.com" />
                        </div>
                        <div class="space-y-2">
                            <x-ui.label for="port">Port</x-ui.label>
                            <input id="port" name="port" value="{{ old('port', $connection->port) }}" type="number" x-bind:placeholder="driver === 'mysql' ? '3306' : (driver === 'pgsql' ? '5432' : ((driver === 'sqlsrv' || driver === 'dblib') ? '1433' : ''))" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" />
                        </div>
                    </div>

                    <div class="space-y-2">
                        <x-ui.label for="database">Veritabanı / Dosya Yolu *</x-ui.label>
                        <input id="database" name="database" value="{{ old('database', $connection->database) }}" x-bind:placeholder="driver === 'sqlite' ? '/path/to/file.sqlite' : 'database_name'" required class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" />
                    </div>

                    <div x-show="driver !== 'sqlite'" x-cloak class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <x-ui.label for="username">Kullanıcı Adı</x-ui.label>
                            <x-ui.input id="username" name="username" value="{{ old('username', $connection->username) }}" autocomplete="off" />
                        </div>
                        <div class="space-y-2">
                            <x-ui.label for="password">Şifre {{ $connection->exists ? '(değiştirmek için doldurun)' : '' }}</x-ui.label>
                            <x-ui.input id="password" name="password" type="password" autocomplete="new-password" placeholder="{{ $connection->exists && $connection->password ? '••••••• (mevcut)' : '' }}" />
                        </div>
                    </div>

                    {{-- Postgres için şema --}}
                    <div x-show="driver === 'pgsql'" x-cloak class="space-y-2">
                        <x-ui.label for="extra_schema">Şema (Postgres)</x-ui.label>
                        <x-ui.input id="extra_schema" name="extra[schema]" value="{{ old('extra.schema', $connection->extra['schema'] ?? 'public') }}" placeholder="public" />
                    </div>

                    {{-- SQL Server için SSL/TLS opsiyonları --}}
                    <div x-show="driver === 'sqlsrv' || driver === 'dblib'" x-cloak class="space-y-3">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="hidden" name="extra[trust_server_certificate]" value="0">
                            <input type="checkbox" name="extra[trust_server_certificate]" value="1" {{ ($connection->extra['trust_server_certificate'] ?? 1) ? 'checked' : '' }} class="h-4 w-4 rounded">
                            <span>Sunucu sertifikasına güven (self-signed cert için)</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="hidden" name="extra[encrypt]" value="0">
                            <input type="checkbox" name="extra[encrypt]" value="1" {{ ($connection->extra['encrypt'] ?? 0) ? 'checked' : '' }} class="h-4 w-4 rounded">
                            <span>Bağlantıyı şifrele (Encrypt=1)</span>
                        </label>
                    </div>

                    <div class="rounded-md border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                        <p class="font-semibold mb-1 flex items-center gap-2"><x-lucide-lock class="h-3.5 w-3.5" /> Güvenlik</p>
                        <p>Şifre AES ile şifrelenerek saklanır. Sadece okuma sorguları (SELECT) çalıştırılır; DDL ve INSERT/UPDATE/DELETE izni yoktur.</p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <x-ui.label for="allowed_client_ips">İzinli dış admin IP/CIDR</x-ui.label>
                            <textarea id="allowed_client_ips" name="extra[product_sync][allowed_client_ips]" rows="3" placeholder="Örn: 195.87.234.135 veya 195.87.234.0/24" class="w-full rounded-md border border-input bg-background px-3 py-2 text-xs">{{ old('extra.product_sync.allowed_client_ips', data_get($connection->extra ?? [], 'product_sync.allowed_client_ips')) }}</textarea>
                            <p class="text-[0.75rem] text-muted-foreground">Yerel ağ otomatik izinlidir. Dışarıdan kurulum yapıyorsanız mevcut IP boşken otomatik eklenir.</p>
                        </div>

                        <div class="space-y-2">
                            <x-ui.label for="allowed_source_ips">İzinli public kaynak IP/CIDR</x-ui.label>
                            <textarea id="allowed_source_ips" name="extra[product_sync][allowed_source_ips]" rows="3" placeholder="DB public IP ise yazın. Yerel ağ kaynakları otomatik izinlidir." class="w-full rounded-md border border-input bg-background px-3 py-2 text-xs">{{ old('extra.product_sync.allowed_source_ips', data_get($connection->extra ?? [], 'product_sync.allowed_source_ips')) }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between border-t px-6 py-4 bg-slate-50">
                    <a href="{{ route('admin.data-pull.index') }}" class="text-sm text-slate-600 hover:text-slate-800">İptal</a>
                    <x-ui.button type="submit">
                        <x-lucide-save class="mr-2 h-4 w-4" /> {{ $connection->exists ? 'Güncelle' : 'Kaydet ve Test Et' }}
                    </x-ui.button>
                </div>
            </x-ui.card>
        </form>
    </div>
</x-layouts.admin>
