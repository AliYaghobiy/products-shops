<?php

namespace App\Helpers;

use Morilog\Jalali\Jalalian;

class PersianDateHelper
{
    /**
     * تبدیل تاریخ میلادی به شمسی
     */
    public static function toPersian(string $date, string $format = 'Y/m/d'): string
    {
        if (empty($date) || $date === '0000-00-00 00:00:00') {
            return '';
        }

        return Jalalian::fromCarbon(\Carbon\Carbon::parse($date))
            ->format($format);
    }

    /**
     * تبدیل تاریخ میلادی به شمسی (فقط تاریخ)
     */
    public static function toPersianDate(string $date): string
    {
        return self::toPersian($date, 'Y/m/d');
    }

    /**
     * تبدیل تاریخ میلادی به شمسی (فقط زمان)
     */
    public static function toPersianTime(string $date): string
    {
        return self::toPersian($date, 'H:i:s');
    }

    /**
     * دریافت تاریخ و زمان فعلی شمسی
     */
    public static function now(string $format = 'Y/m/d'): string
    {
        return Jalalian::now()->format($format);
    }

    /**
     * دریافت تاریخ امروز شمسی
     */
    public static function today(): string
    {
        return Jalalian::now()->format('Y/m/d');
    }

    /**
     * دریافت تاریخ دیروز شمسی
     */
    public static function yesterday(): string
    {
        return Jalalian::now()->subDays(1)->format('Y/m/d');
    }

    /**
     * بررسی اینکه آیا تاریخ امروز است یا نه
     */
    public static function isToday(string $date): bool
    {
        if (empty($date)) return false;

        $persianDate = self::toPersianDate($date);
        return $persianDate === self::today();
    }

    /**
     * بررسی اینکه آیا تاریخ دیروز است یا نه
     */
    public static function isYesterday(string $date): bool
    {
        if (empty($date)) return false;

        $persianDate = self::toPersianDate($date);
        return $persianDate === self::yesterday();
    }
}
