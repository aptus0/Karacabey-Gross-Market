<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataConnection;
use App\Services\DataIntegration\DataSourceBrowser;
use App\Services\DataIntegration\NetworkAccessGuard;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataPullController extends Controller
{
    public function __construct(
        private readonly DataSourceBrowser $browser,
        private readonly NetworkAccessGuard $network,
    ) {
    }

    public function browse(Request $request, DataConnection $connection): View
    {
        $tables = [];
        $error = null;

        try {
            $this->network->assertRequestAllowed($request, $connection);
            $this->network->assertSourceAllowed($connection);
            $tables = $this->browser->listTables($connection);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return view('admin.data-pull.browse', [
            'connection' => $connection,
            'tables' => $tables,
            'error' => $error,
        ]);
    }

    public function preview(Request $request, DataConnection $connection)
    {
        $validated = $request->validate([
            'table' => ['required', 'string', 'max:120'],
            'schema' => ['nullable', 'string', 'max:120'],
            'limit' => ['nullable', 'integer', 'between:1,1000'],
        ]);

        try {
            $this->network->assertRequestAllowed($request, $connection);
            $this->network->assertSourceAllowed($connection);
            $preview = $this->browser->previewTable(
                $connection,
                $validated['table'],
                $validated['limit'] ?? 100,
                $validated['schema'] ?? null,
            );

            return response()->json([
                'success' => true,
                'data' => $preview,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function export(Request $request, DataConnection $connection): StreamedResponse
    {
        $validated = $request->validate([
            'table' => ['required', 'string', 'max:120'],
            'schema' => ['nullable', 'string', 'max:120'],
        ]);

        $tableName = $validated['table'];
        $schema = $validated['schema'] ?? null;
        $filename = sprintf('%s_%s_%s.csv', $connection->name, $schema ? "{$schema}." : '', $tableName);
        $filename = preg_replace('/[^A-Za-z0-9_.-]+/', '_', $filename).'.csv';

        $this->network->assertRequestAllowed($request, $connection);
        $this->network->assertSourceAllowed($connection);

        return response()->stream(function () use ($connection, $tableName, $schema) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM (Excel için)
            fwrite($out, "\xEF\xBB\xBF");

            $headerWritten = false;
            try {
                foreach ($this->browser->streamTable($connection, $tableName, $schema) as $row) {
                    if (! $headerWritten) {
                        fputcsv($out, array_keys($row), ',', '"', '\\');
                        $headerWritten = true;
                    }
                    fputcsv($out, array_map(fn ($v) => is_scalar($v) || $v === null ? $v : json_encode($v), $row), ',', '"', '\\');
                }
            } catch (\Throwable $e) {
                fwrite($out, "\n# Export hatası: ".$e->getMessage().PHP_EOL);
            }

            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
