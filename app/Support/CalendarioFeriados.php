<?php

namespace App\Support;

use Carbon\Carbon;

class CalendarioFeriados
{
    /** @return array<string, string> */
    public static function year(int $year): array
    {
        $fixed = ['01-01' => 'Confraternização Universal', '04-21' => 'Tiradentes', '05-01' => 'Dia do Trabalho', '09-07' => 'Independência do Brasil', '10-12' => 'Nossa Senhora Aparecida', '11-02' => 'Finados', '11-15' => 'Proclamação da República', '11-20' => 'Dia Nacional de Zumbi e da Consciência Negra', '12-25' => 'Natal'];
        $holidays = [];
        foreach ($fixed as $date => $name) {
            $holidays[$year.'-'.$date] = $name;
        }
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = ($h + $l - 7 * $m + 114) % 31 + 1;
        $holidays[Carbon::create($year, $month, $day)->subDays(2)->toDateString()] = 'Sexta-feira Santa (Paixão de Cristo)';
        ksort($holidays);

        return $holidays;
    }

    public static function on(Carbon $date): ?string
    {
        return self::year($date->year)[$date->toDateString()] ?? null;
    }
}
