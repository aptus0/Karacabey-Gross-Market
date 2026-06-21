<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string $driver
 * @property ?string $host
 * @property ?int $port
 * @property string $database
 * @property ?string $username
 * @property ?string $password
 * @property ?array $extra
 * @property ?\Illuminate\Support\Carbon $last_tested_at
 * @property ?string $last_test_status
 * @property ?string $last_test_message
 */
class DataConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'driver',
        'host',
        'port',
        'database',
        'username',
        'password',
        'extra',
        'last_tested_at',
        'last_test_status',
        'last_test_message',
    ];

    protected function casts(): array
    {
        return [
            'extra' => 'array',
            'password' => 'encrypted',
            'last_tested_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Sadece desteklenen driver'lar — DataSourceBrowser bu listeyi kullanır. */
    public const SUPPORTED_DRIVERS = ['mysql', 'pgsql', 'sqlsrv', 'dblib', 'sqlite'];

    public static function defaultPort(string $driver): ?int
    {
        return match ($driver) {
            'mysql' => 3306,
            'pgsql' => 5432,
            'sqlsrv', 'dblib' => 1433,
            default => null,
        };
    }

    public function driverLabel(): string
    {
        return match ($this->driver) {
            'mysql' => 'MySQL / MariaDB',
            'pgsql' => 'PostgreSQL',
            'sqlsrv' => 'SQL Server',
            'dblib' => 'SQL Server (FreeTDS)',
            'sqlite' => 'SQLite',
            default => strtoupper($this->driver),
        };
    }
}
