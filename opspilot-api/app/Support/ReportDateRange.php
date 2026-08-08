<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class ReportDateRange
{
    private function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $endExclusive,
    ) {}

    /** @param array{from?: string, to?: string} $filters */
    public static function fromFilters(array $filters): self
    {
        $to = isset($filters['to'])
            ? CarbonImmutable::createFromFormat('!Y-m-d', $filters['to'], 'UTC')
            : CarbonImmutable::now('UTC')->startOfDay();
        $from = isset($filters['from'])
            ? CarbonImmutable::createFromFormat('!Y-m-d', $filters['from'], 'UTC')
            : $to->subDays(29);

        return new self($from, $to->addDay());
    }

    /** @return array{from: string, to: string, timezone: string} */
    public function period(): array
    {
        return [
            'from' => $this->start->toDateString(),
            'to' => $this->endExclusive->subDay()->toDateString(),
            'timezone' => 'UTC',
        ];
    }

    /** @return list<string> */
    public function dates(): array
    {
        $dates = [];

        for ($date = $this->start; $date->lessThan($this->endExclusive); $date = $date->addDay()) {
            $dates[] = $date->toDateString();
        }

        return $dates;
    }
}
