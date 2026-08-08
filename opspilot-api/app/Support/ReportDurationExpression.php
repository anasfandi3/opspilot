<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReportDurationExpression
{
    public function hours(string $startedAt, string $endedAt): string
    {
        return match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => "TIMESTAMPDIFF(SECOND, {$startedAt}, {$endedAt}) / 3600.0",
            'sqlite' => "(julianday({$endedAt}) - julianday({$startedAt})) * 24.0",
            default => throw new RuntimeException('The configured database driver does not support reporting duration aggregates.'),
        };
    }
}
