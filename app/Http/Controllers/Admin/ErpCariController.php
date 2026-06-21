<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ErkurAnalyticsService;
use App\Services\Erp12\Erp12LiveAnalyticsService;
use App\Support\TenantResolver;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ErpCariController extends Controller
{
    public function __construct(
        private readonly ErkurAnalyticsService $erkur,
        private readonly Erp12LiveAnalyticsService $liveErp,
    ) {}

    public function index(Request $request, TenantResolver $tenants): View
    {
        $q    = $request->get('q');
        $tur  = $request->get('tur');
        $tenant = $tenants->resolve($request);
        $data = $this->liveErp->getCariListesi(['q' => $q, 'tur' => $tur], $tenant->id)
            ?? $this->erkur->getCariListesi(['q' => $q, 'tur' => $tur]);

        // Fallback if both services return null
        if ($data === null) {
            $data = [
                'cariler' => [],
                'ozet' => ['toplam' => 0, 'aktif' => 0, 'alici' => 0, 'satici' => 0],
            ];
        }

        return view('admin.erp.cari', [
            'cariler' => $data['cariler'],
            'ozet'    => $data['ozet'],
            'q'       => $q,
            'tur'     => $tur,
        ]);
    }

    public function show(int $id, Request $request): View
    {
        $data = $this->liveErp->getCariDetay($id) ?? $this->erkur->getCariDetay($id);

        return view('admin.erp.cari-show', [
            'cari'    => $data['cari'],
            'adresler'=> $data['adresler'],
            'fisler'  => $data['fisler'],
            'ozet'    => $data['ozet'],
            'id'      => $id,
        ]);
    }
}
