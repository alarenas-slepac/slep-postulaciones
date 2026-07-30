<?php

namespace App\Support\Cometidos;

use Carbon\Carbon;

class BusinessDaysCalculator
{
    public function between($from, $to): int
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();
        if ($end->lessThan($start)) {
            return 0;
        }

        $count = 0;
        $cursor = $start->copy();
        while ($cursor->lessThan($end)) {
            if (! $cursor->isWeekend()) {
                $count++;
            }
            $cursor->addDay();
        }
        return $count;
    }
}
