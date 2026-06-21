<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Erp12\Erp12LiveAnalyticsService;
use App\Support\TenantResolver;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ErpPosController extends Controller
{
    public function __construct(private readonly Erp12LiveAnalyticsService $liveErp)
    {
    }

    public function index(Request $request, TenantResolver $tenants): View
    {
        $tenant = $tenants->resolve($request);
        $filters = [
            'tarih_baslangic' => $request->get('baslangic'),
            'tarih_bitis' => $request->get('bitis'),
            'terminal' => $request->get('terminal'),
        ];

        $data = $this->liveErp->getPosRaporlari($filters, $tenant->id) ?? [
            'raporlar' => [],
            'ozet' => ['pos_fis' => 0, 'tekil_fis' => 0, 'terminal' => 0, 'z_sayisi' => 0, 'son_kapanis' => ''],
        ];

        return view('admin.erp.pos', [
            'raporlar' => $data['raporlar'],
            'ozet' => $data['ozet'],
            'filters' => $filters,
        ]);
    }
}
