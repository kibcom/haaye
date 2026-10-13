<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Ethiopian (Ge'ez) calendar helpers.
 * Conversion uses the standard JDN offset shared by common Ethiopic libraries.
 */
final class EthiopianCalendar
{
    public const MONTHS = [
        1 => 'Meskerem',
        2 => 'Tikimt',
        3 => 'Hidar',
        4 => 'Tahsas',
        5 => 'Tir',
        6 => 'Yekatit',
        7 => 'Megabit',
        8 => 'Miazia',
        9 => 'Ginbot',
        10 => 'Sene',
        11 => 'Hamle',
        12 => 'Nehase',
        13 => 'Pagumen',
    ];

    /** @return array{year:int,month:int,day:int} */
    public static function fromGregorian(int $year, int $month, int $day): array
    {
        return self::jdnToEthiopian(self::gregorianToJdn($year, $month, $day));
    }

    /** @return array{year:int,month:int,day:int} */
    public static function fromDate(\DateTimeInterface $date): array
    {
        return self::fromGregorian(
            (int) $date->format('Y'),
            (int) $date->format('n'),
            (int) $date->format('j')
        );
    }

    public static function toGregorianDate(int $year, int $month, int $day): \DateTimeImmutable
    {
        $jdn = self::ethiopianToJdn($year, $month, $day);
        [$gy, $gm, $gd] = self::jdnToGregorian($jdn);

        return new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $gy, $gm, $gd));
    }

    public static function monthLabel(int $year, int $month): string
    {
        $name = self::MONTHS[$month] ?? ('Month ' . $month);

        return $name . ' ' . $year;
    }

    public static function daysInMonth(int $year, int $month): int
    {
        if ($month >= 1 && $month <= 12) {
            return 30;
        }
        if ($month === 13) {
            return self::isLeapYear($year) ? 6 : 5;
        }

        return 0;
    }

    public static function isLeapYear(int $year): bool
    {
        return $year % 4 === 3;
    }

    /** @return array{year:int,month:int} */
    public static function shiftMonth(int $year, int $month, int $delta): array
    {
        $index = ($year * 13) + ($month - 1) + $delta;
        $year = intdiv($index, 13);
        $month = ($index % 13) + 1;
        if ($month <= 0) {
            $year -= 1;
            $month += 13;
        }

        return ['year' => $year, 'month' => $month];
    }

    private static function gregorianToJdn(int $year, int $month, int $day): int
    {
        $a = intdiv(14 - $month, 12);
        $y = $year + 4800 - $a;
        $m = $month + 12 * $a - 3;

        return $day
            + intdiv(153 * $m + 2, 5)
            + 365 * $y
            + intdiv($y, 4)
            - intdiv($y, 100)
            + intdiv($y, 400)
            - 32045;
    }

    /** @return array{0:int,1:int,2:int} year, month, day */
    private static function jdnToGregorian(int $jdn): array
    {
        $a = $jdn + 32044;
        $b = intdiv(4 * $a + 3, 146097);
        $c = $a - intdiv(146097 * $b, 4);
        $d = intdiv(4 * $c + 3, 1461);
        $e = $c - intdiv(1461 * $d, 4);
        $m = intdiv(5 * $e + 2, 153);
        $day = $e - intdiv(153 * $m + 2, 5) + 1;
        $month = $m + 3 - 12 * intdiv($m, 10);
        $year = 100 * $b + $d - 4800 + intdiv($m, 10);

        return [$year, $month, $day];
    }

    private static function ethiopianToJdn(int $year, int $month, int $day): int
    {
        return (1723856 + 365)
            + 365 * ($year - 1)
            + intdiv($year, 4)
            + 30 * $month
            + $day
            - 31;
    }

    /** @return array{year:int,month:int,day:int} */
    private static function jdnToEthiopian(int $jdn): array
    {
        $r = ($jdn - 1723856) % 1461;
        $n = ($r % 365) + 365 * intdiv($r, 1460);
        $year = 4 * intdiv($jdn - 1723856, 1461) + intdiv($r, 365) - intdiv($r, 1460);
        $month = intdiv($n, 30) + 1;
        $day = ($n % 30) + 1;

        return [
            'year' => $year,
            'month' => $month,
            'day' => $day,
        ];
    }
}
