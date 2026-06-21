<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataConnection;
use App\Services\DataIntegration\DataSourceBrowser;
use App\Services\DataIntegration\NetworkAccessGuard;
use App\Support\TenantResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DataConnectionController extends Controller
{
    public function __construct(
        private readonly DataSourceBrowser $browser,
        private readonly NetworkAccessGuard $network,
    ) {
    }

    public function index(Request $request, TenantResolver $tenants): View
    {
        $tenant = $tenants->resolve($request);

        return view('admin.data-pull.index', [
            'connections' => DataConnection::query()
                ->where('tenant_id', $tenant->id)
                ->orderByDesc('updated_at')
                ->get(),
            'availableDrivers' => $this->browser->availableDrivers(),
        ]);
    }

    public function create(): View
    {
        return view('admin.data-pull.connection', [
            'connection' => new DataConnection(),
            'availableDrivers' => $this->browser->availableDrivers(),
        ]);
    }

    public function store(Request $request, TenantResolver $tenants): RedirectResponse
    {
        $tenant = $tenants->resolve($request);
        $data = $this->validated($request);
        $data['tenant_id'] = $tenant->id;

        $connection = DataConnection::create($data);

        return redirect()
            ->route('admin.data-pull.index')
            ->with('status', "Bağlantı oluşturuldu: {$connection->name}");
    }

    public function edit(DataConnection $connection): View
    {
        return view('admin.data-pull.connection', [
            'connection' => $connection,
            'availableDrivers' => $this->browser->availableDrivers(),
        ]);
    }

    public function update(Request $request, DataConnection $connection): RedirectResponse
    {
        $data = $this->validated($request);

        if (isset($data['extra'])) {
            $data['extra'] = array_replace_recursive($connection->extra ?? [], $data['extra']);
        }

        // Şifre boş bırakılırsa mevcut korunsun
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $connection->update($data);

        return redirect()
            ->route('admin.data-pull.index')
            ->with('status', "Bağlantı güncellendi: {$connection->name}");
    }

    public function destroy(DataConnection $connection): RedirectResponse
    {
        $name = $connection->name;
        $connection->delete();

        return redirect()
            ->route('admin.data-pull.index')
            ->with('status', "Bağlantı silindi: {$name}");
    }

    public function test(Request $request, DataConnection $connection): RedirectResponse
    {
        try {
            $this->network->assertRequestAllowed($request, $connection);
            $this->network->assertSourceAllowed($connection);
            $result = $this->browser->testConnection($connection);
        } catch (\Throwable $e) {
            $result = [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }

        $connection->update([
            'last_tested_at' => now(),
            'last_test_status' => $result['success'] ? 'success' : 'fail',
            'last_test_message' => $result['message'],
        ]);

        return redirect()
            ->route('admin.data-pull.index')
            ->with('status', $result['success']
                ? "✓ {$connection->name}: Bağlantı başarılı."
                : "✗ {$connection->name}: ".$result['message']);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'driver' => ['required', 'string', 'in:'.implode(',', DataConnection::SUPPORTED_DRIVERS)],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'database' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:120'],
            'password' => ['nullable', 'string', 'max:255'],
            'extra' => ['nullable', 'array'],
            'extra.schema' => ['nullable', 'string', 'max:120'],
            'extra.trust_server_certificate' => ['nullable', 'boolean'],
            'extra.encrypt' => ['nullable', 'boolean'],
            'extra.product_sync.allowed_client_ips' => ['nullable', 'string', 'max:1000'],
            'extra.product_sync.allowed_source_ips' => ['nullable', 'string', 'max:1000'],
        ]);

        $clientIp = (string) $request->ip();
        if (
            filter_var($clientIp, FILTER_VALIDATE_IP)
            && ! $this->isPrivateIp($clientIp)
            && blank(data_get($data, 'extra.product_sync.allowed_client_ips'))
        ) {
            data_set($data, 'extra.product_sync.allowed_client_ips', $clientIp);
        }

        return $data;
    }

    private function isPrivateIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
