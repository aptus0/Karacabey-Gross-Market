<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ErkurAnalyticsService;
use App\Services\Erp12\Erp12LiveAnalyticsService;
use App\Services\ErpFaturaSyncService;
use App\Support\TenantResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ErpFaturaController extends Controller
{
    public function __construct(
        private readonly ErkurAnalyticsService $erkur,
        private readonly ErpFaturaSyncService  $syncSvc,
        private readonly Erp12LiveAnalyticsService $liveErp,
    ) {}

    public function index(Request $request, TenantResolver $tenants): View
    {
        $filters = [
            'tarih_baslangic' => $request->get('baslangic'),
            'tarih_bitis'     => $request->get('bitis'),
            'tip'             => $request->get('tip'),
            'durum'           => $request->get('durum'),
        ];

        $tenant = $tenants->resolve($request);
        $data = $this->liveErp->getFaturalar($filters, $tenant->id) ?? $this->erkur->getFaturalar($filters);

        return view('admin.erp.fatura', [
            'faturalar' => $data['faturalar'],
            'ozet'      => $data['ozet'],
            'filters'   => $filters,
        ]);
    }

    public function show(int $id, Request $request, TenantResolver $tenants): View
    {
        $tenant = $tenants->resolve($request);
        $data = $this->liveErp->getFaturalar([], $tenant->id) ?? $this->erkur->getFaturalar([]);
        $fatura = $this->liveErp->getFatura($id, $tenant->id)
            ?? collect($data['faturalar'])->firstWhere('id', (string) $id);
        $satirlar = $this->liveErp->getFaturaDetay($id, $tenant->id) ?? $this->syncSvc->getFaturaDetay($id);

        return view('admin.erp.fatura-show', [
            'fatura'   => $fatura,
            'satirlar' => $satirlar,
            'fisId'    => $id,
        ]);
    }

    public function sync(int $id, TenantResolver $tenants, Request $request): RedirectResponse
    {
        $tenant = $tenants->resolve($request);
        $result = $this->syncSvc->syncFromCsv($id, $tenant->id);

        $msg = "Senkronizasyon tamamlandı: {$result['new']} yeni, {$result['updated']} güncellenen, {$result['stock_updated']} stok düzeltilen ürün.";
        if (! empty($result['errors'])) {
            $msg .= ' Hata: ' . implode(', ', $result['errors']);
        }

        return redirect()->route('admin.erp.fatura.show', $id)->with('status', $msg);
    }
}
