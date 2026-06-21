<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class OpsMonitorController extends Controller
{
    public function __invoke(Request $request): View
    {
        $baseUrl = rtrim((string) env('GO_API_INTERNAL_URL', env('API_INTERNAL_URL', 'http://go-api:8080')), '/');
        $token = (string) env('KGM_INTERNAL_API_TOKEN', '');
        $autoRefresh = max(0, min(600, (int) $request->integer('refresh', 30)));
        $error = null;

        $summary = null;
        $paymentRisk = null;
        $mobile = null;
        $queues = null;
        $devices = null;
        $events = null;

        if ($token === '') {
            $error = 'KGM_INTERNAL_API_TOKEN tanımlı değil. Go internal endpointleri güvenlik için token ister.';
        } else {
            $summary = $this->fetchInternal($baseUrl, $token, '/internal/v1/ops/summary', $error);
            $paymentRisk = $this->fetchInternal($baseUrl, $token, '/internal/v1/ops/payment-risk', $error);
            $mobile = $this->fetchInternal($baseUrl, $token, '/internal/v1/ops/mobile', $error);
            $queues = $this->fetchInternal($baseUrl, $token, '/internal/v1/ops/queues', $error);
            $devices = $this->fetchInternal($baseUrl, $token, '/internal/v1/ops/mobile/devices', $error);
            $events = $this->fetchInternal($baseUrl, $token, '/internal/v1/ops/mobile/events', $error);
        }

        return view('admin.ops-monitor.index', [
            'summary' => $summary,
            'paymentRisk' => $paymentRisk,
            'mobile' => $mobile,
            'queues' => $queues,
            'devices' => $devices,
            'events' => $events,
            'error' => $error,
            'baseUrl' => $baseUrl,
            'runtime' => Arr::get($summary, 'runtime', []),
            'autoRefresh' => $autoRefresh,
            'generatedAt' => Arr::get($summary, 'generated_at'),
        ]);
    }

    private function fetchInternal(string $baseUrl, string $token, string $path, ?string &$error): ?array
    {
        try {
            $response = Http::timeout(5)
                ->retry(1, 150)
                ->withToken($token)
                ->acceptJson()
                ->get($baseUrl . $path);

            if ($response->successful()) {
                return $response->json('data');
            }

            $error = trim(($error ? $error . ' | ' : '') . $path . ' HTTP ' . $response->status());
        } catch (\Throwable $exception) {
            $error = trim(($error ? $error . ' | ' : '') . $path . ': ' . $exception->getMessage());
        }

        return null;
    }
}
