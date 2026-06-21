<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuthLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AuthLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'max:32'],
            'event' => ['nullable', 'string', 'max:48'],
        ]);

        $query = AdminAuthLog::query()->latest();

        if (filled($filters['q'] ?? null)) {
            $term = trim((string) $filters['q']);

            $query->where(function (Builder $query) use ($term): void {
                $query->where('ip_address', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('path', 'like', "%{$term}%")
                    ->orWhere('user_agent', 'like', "%{$term}%");
            });
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        if (filled($filters['event'] ?? null)) {
            $query->where('event_type', $filters['event']);
        }

        $logs = $query->paginate(30)->withQueryString();
        $since = now()->subDay();

        $stats = [
            'total_24h' => AdminAuthLog::query()->where('created_at', '>=', $since)->count(),
            'blocked_24h' => AdminAuthLog::query()
                ->where('created_at', '>=', $since)
                ->where(function (Builder $query): void {
                    $query->where('status', 'blocked')
                        ->orWhere('guard_action', 'block');
                })
                ->count(),
            'decoy_24h' => AdminAuthLog::query()
                ->where('created_at', '>=', $since)
                ->where('event_type', 'like', 'decoy_%')
                ->count(),
            'unique_ips_24h' => AdminAuthLog::query()
                ->where('created_at', '>=', $since)
                ->whereNotNull('ip_address')
                ->distinct('ip_address')
                ->count('ip_address'),
        ];

        $events = AdminAuthLog::query()
            ->select('event_type')
            ->distinct()
            ->orderBy('event_type')
            ->pluck('event_type');

        $statuses = AdminAuthLog::query()
            ->select('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status');

        $activeBlocks = AdminAuthLog::query()
            ->select([
                'ip_address',
                DB::raw('MAX(blocked_until) as blocked_until'),
                DB::raw('MAX(risk_score) as risk_score'),
                DB::raw('COUNT(*) as events_count'),
            ])
            ->whereNotNull('ip_address')
            ->activeBlock()
            ->groupBy('ip_address')
            ->orderByDesc('blocked_until')
            ->limit(8)
            ->get();

        return view('admin.auth-logs.index', [
            'logs' => $logs,
            'stats' => $stats,
            'events' => $events,
            'statuses' => $statuses,
            'activeBlocks' => $activeBlocks,
            'filters' => $filters,
        ]);
    }
}
