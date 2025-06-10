<?php

namespace App\Helpers;

class JalaliHelper
{
    /**
     * تبدیل تاریخ میلادی به شمسی
     */
    public static function toJalali($gregorianDate): string
    {
        if (empty($gregorianDate)) {
            return '';
        }

        $timestamp = is_numeric($gregorianDate) ? $gregorianDate : strtotime($gregorianDate);

        if (!$timestamp) {
            return '';
        }

        return self::gregorianToJalali($timestamp);
    }

    /**
     * تبدیل timestamp به تاریخ شمسی
     */
    private static function gregorianToJalali($timestamp): string
    {
        $g_y = date('Y', $timestamp);
        $g_m = date('n', $timestamp);
        $g_d = date('j', $timestamp);
        $h = date('H', $timestamp);
        $i = date('i', $timestamp);
        $s = date('s', $timestamp);

        $j_date = self::convertToJalali($g_y, $g_m, $g_d);

        return sprintf(
            '%04d/%02d/%02d %02d:%02d:%02d',
            $j_date[0], $j_date[1], $j_date[2], $h, $i, $s
        );
    }

    /**
     * الگوریتم تبدیل تاریخ میلادی به شمسی
     */
    private static function convertToJalali($gy, $gm, $gd): array
    {
        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];

        if ($gy <= 1600) {
            $jy = 0;
            $gy -= 621;
        } else {
            $jy = 979;
            $gy -= 1600;
        }

        if ($gm > 2) {
            $gy2 = $gy + 1;
        } else {
            $gy2 = $gy;
        }

        $days = (365 * $gy) + ((int)($gy2 / 4)) - ((int)($gy2 / 100)) + ((int)($gy2 / 400)) - 80 + $gd + $g_d_m[$gm - 1];

        $jy += 33 * ((int)($days / 12053));
        $days %= 12053;

        $jy += 4 * ((int)($days / 1461));
        $days %= 1461;

        if ($days > 365) {
            $jy += (int)(($days - 1) / 365);
            $days = ($days - 1) % 365;
        }

        if ($days < 186) {
            $jm = 1 + (int)($days / 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + (int)(($days - 186) / 30);
            $jd = 1 + (($days - 186) % 30);
        }

        return [$jy, $jm, $jd];
    }

    /**
     * نمایش تاریخ شمسی با فرمت کوتاه
     */
    public static function toJalaliShort($gregorianDate): string
    {
        if (empty($gregorianDate)) {
            return '';
        }

        $timestamp = is_numeric($gregorianDate) ? $gregorianDate : strtotime($gregorianDate);

        if (!$timestamp) {
            return '';
        }

        $g_y = date('Y', $timestamp);
        $g_m = date('n', $timestamp);
        $g_d = date('j', $timestamp);
        $h = date('H', $timestamp);
        $i = date('i', $timestamp);

        $j_date = self::convertToJalali($g_y, $g_m, $g_d);

        return sprintf(
            '%04d/%02d/%02d %02d:%02d',
            $j_date[0], $j_date[1], $j_date[2], $h, $i
        );
    }
}
