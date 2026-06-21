<x-layouts.admin header="Veri Çekme — Bağlantılar">
    <div class="flex flex-col gap-6 max-w-6xl mx-auto w-full">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight">Veri Bağlantıları</h2>
                <p class="text-sm text-muted-foreground">Dış veritabanlarına read-only bağlantı kurun, tablo tarayın, önizleyin ve CSV olarak dışa aktarın.</p>
            </div>
            <a href="{{ route('admin.data-pull.create') }}" class="inline-flex items-center gap-2 rounded-md bg-orange-500 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-orange-600">
                <x-lucide-plus class="h-4 w-4" /> Yeni Bağlantı
            </a>
        </div>

        @if(session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        @if(empty($availableDrivers))
            <div class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                Sistemde hiçbir PDO sürücüsü kurulu değil. (PHP'de pdo_mysql, pdo_pgsql, pdo_sqlsrv veya pdo_sqlite gerekiyor.)
            </div>
        @else
            <div class="rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600">
                <span class="font-medium">Mevcut sürücüler:</span>
                @foreach($availableDrivers as $d)
                    <span class="ml-1 inline-block rounded-full bg-slate-200 px-2 py-0.5 font-mono">{{ $d }}</span>
                @endforeach
            </div>
        @endif

        @if($connections->isEmpty())
            <x-ui.card>
                <div class="p-12 text-center">
                    <x-lucide-database class="mx-auto h-12 w-12 text-slate-300 mb-3" />
                    <h3 class="font-semibold text-slate-700 mb-1">Henüz bir bağlantı yok</h3>
                    <p class="text-sm text-slate-500 mb-4">İlk veri kaynağınızı ekleyerek başlayın.</p>
                    <a href="{{ route('admin.data-pull.create') }}" class="inline-flex items-center gap-2 rounded-md bg-orange-500 px-4 py-2 text-sm font-medium text-white">
                        <x-lucide-plus class="h-4 w-4" /> Yeni Bağlantı Ekle
                    </a>
                </div>
            </x-ui.card>
        @else
            <x-ui.card>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Ad</th>
                                <th class="px-4 py-3">Sürücü</th>
                                <th class="px-4 py-3">Host / DB</th>
                                <th class="px-4 py-3">Son Test</th>
                                <th class="px-4 py-3 text-right">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach($connections as $conn)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 font-medium text-slate-800">{{ $conn->name }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">{{ $conn->driverLabel() }}</span>
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    <div class="font-mono text-xs">{{ $conn->host ?? '—' }}{{ $conn->port ? ':'.$conn->port : '' }}</div>
                                    <div class="font-mono text-xs text-slate-400">{{ $conn->database }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    @if($conn->last_test_status === 'success')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700"><x-lucide-check-circle-2 class="h-3 w-3" /> Başarılı</span>
                                        <div class="text-[10px] text-slate-400 mt-1">{{ $conn->last_tested_at?->diffForHumans() }}</div>
                                    @elseif($conn->last_test_status === 'fail')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700" title="{{ $conn->last_test_message }}"><x-lucide-x-circle class="h-3 w-3" /> Hata</span>
                                        <div class="text-[10px] text-slate-400 mt-1">{{ $conn->last_tested_at?->diffForHumans() }}</div>
                                    @else
                                        <span class="text-xs text-slate-400">Test edilmedi</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-1">
                                        <form action="{{ route('admin.data-pull.test', $conn) }}" method="POST" class="inline-flex">
                                            @csrf
                                            <button class="inline-flex items-center gap-1 rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100" title="Bağlantıyı Test Et">
                                                <x-lucide-zap class="h-3.5 w-3.5" /> Test
                                            </button>
                                        </form>
                                        <a href="{{ route('admin.data-pull.browse', $conn) }}" class="inline-flex items-center gap-1 rounded-md bg-blue-50 px-2.5 py-1.5 text-xs font-medium text-blue-700 hover:bg-blue-100">
                                            <x-lucide-table-2 class="h-3.5 w-3.5" /> Tablolar
                                        </a>
                                        <a href="{{ route('admin.data-pull.edit', $conn) }}" class="inline-flex items-center gap-1 rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100">
                                            <x-lucide-pencil class="h-3.5 w-3.5" />
                                        </a>
                                        <form action="{{ route('admin.data-pull.destroy', $conn) }}" method="POST" class="inline-flex" data-confirm-submit="Bağlantı silinsin mi? Bu işlem geri alınamaz.">
                                            @csrf
                                            @method('DELETE')
                                            <button class="inline-flex items-center gap-1 rounded-md border border-rose-200 px-2.5 py-1.5 text-xs font-medium text-rose-600 hover:bg-rose-50">
                                                <x-lucide-trash-2 class="h-3.5 w-3.5" />
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-ui.card>
        @endif
    </div>
</x-layouts.admin>
