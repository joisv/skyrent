<?php

namespace App\Helpers;

class CashSuggestion
{
    /**
     * Create a new class instance.
     */
    protected static array $denominations = [
        100000,
        50000,
        20000,
        10000,
        5000,
        2000,
        1000,
    ];

    public static function generate(int $total): array
    {
        $suggestions = [];

        // Bayar pas
        $pieces = self::breakdown($total);

        $suggestions[] = [
            'pay' => $total,
            'change' => 0,
            'pieces' => $pieces,
        ];

        // Jika cukup satu lembar uang (100rb,50rb,dst)
        if (count($pieces) === 1) {
            return $suggestions;
        }

        /*
        |--------------------------------------------------------------------------
        | Saran pembayaran
        |--------------------------------------------------------------------------
        */

        $candidates = [];

        // kelipatan 5rb
        $candidates[] = ceil($total / 5000) * 5000;

        // kelipatan 10rb
        $candidates[] = ceil($total / 10000) * 10000;

        // kelipatan 50rb
        $candidates[] = ceil($total / 50000) * 50000;

        // kelipatan 100rb
        $candidates[] = ceil($total / 100000) * 100000;

        foreach ($candidates as $pay) {

            if ($pay <= $total) {
                continue;
            }

            $suggestions[] = [
                'pay' => $pay,
                'change' => $pay - $total,
                'pieces' => self::breakdown($pay),
            ];
        }

        // hapus nominal yang sama
        $suggestions = collect($suggestions)
            ->unique('pay')
            ->sortBy('pay')
            ->values()
            ->toArray();

        return $suggestions;
    }

    /**
     * Mengubah nominal menjadi pecahan uang.
     */
    protected static function breakdown(int $amount): array
    {
        $pieces = [];

        foreach (self::$denominations as $money) {

            $count = intdiv($amount, $money);

            if ($count > 0) {

                for ($i = 0; $i < $count; $i++) {
                    $pieces[] = $money;
                }

                $amount %= $money;
            }
        }

        return $pieces;
    }
}
