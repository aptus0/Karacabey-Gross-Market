<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ErkurAnalyticsService;
use App\Services\Erp12\Erp12LiveAnalyticsService;
use App\Support\TenantResolver;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ErpSayimController extends Controller
{
    public function __construct(
        private readonly ErkurAnalyticsService $erkur,
        private readonly Erp12LiveAnalyticsService $liveErp,
    ) {}

    public function index(Request $request, TenantResolver $tenants): View
    {
        $tenant = $tenants->resolve($request);
        $data = $this->liveErp->getSayimListesi($tenant->id) ?? $this->erkur->getSayimListesi();

        return view('admin.erp.sayim', [
            'sayimlar' => $data['sayimlar'],
            'ozet'     => $data['ozet'],
        ]);
    }
}
