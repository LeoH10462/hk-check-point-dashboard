<?php
namespace App;

final class QueueModel
{
    public static function run(int $base, int $increase, int $ramp, int $lanes, int $initial): array
    {
        $queue = $initial;
        $capacity = $lanes * 4;
        $series = [];
        for ($minute = 1; $minute <= 60; $minute++) {
            $arrivals = $base + (int) round($increase * min(1, $minute / $ramp));
            $served = min($queue + $arrivals, $capacity);
            $queue += $arrivals - $served;
            $series[] = ['minute'=>$minute, 'arrivals'=>$arrivals, 'served'=>$served,
                'queue'=>$queue, 'wait'=>round($queue / $capacity, 1)];
        }
        return $series;
    }
}
