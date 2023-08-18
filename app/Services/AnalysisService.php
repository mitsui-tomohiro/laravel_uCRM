<?php 

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AnalysisService
{
    public static function perDay($subQuery) {
        $query = $subQuery->where('status', true)
        ->groupBy('id')
        ->selectRaw('SUM(subtotal) AS totalPerPurchase, DATE_FORMAT(created_at, "%Y%m%d") AS date')
        ->groupBy('date');

        $data = DB::table($query)
        ->groupBy('date')
        ->selectRaw('date, SUM(totalPerPurchase) AS total')
        ->get();

        $labels = $data->pluck('date');
        $totals = $data->pluck('total');

        return [$data, $labels, $totals]; // 複数の変数を渡すのでいったん配列に入れる
    }

    public static function perMonth($subQuery) {
        $query = $subQuery->where('status', true)
        ->groupBy('id')
        ->selectRaw('SUM(subtotal) AS totalPerPurchase, DATE_FORMAT(created_at, "%Y%m") AS date')
        ->groupBy('date');

        $data = DB::table($query)
        ->groupBy('date')
        ->selectRaw('date, SUM(totalPerPurchase) AS total')
        ->get();

        $labels = $data->pluck('date');
        $totals = $data->pluck('total');

        return [$data, $labels, $totals]; // 複数の変数を渡すのでいったん配列に入れる
    }

    public static function perYear($subQuery) {
        $query = $subQuery->where('status', true)
        ->groupBy('id')
        ->selectRaw('SUM(subtotal) AS totalPerPurchase, DATE_FORMAT(created_at, "%Y") AS date')
        ->groupBy('date');

        $data = DB::table($query)
        ->groupBy('date')
        ->selectRaw('date, SUM(totalPerPurchase) AS total')
        ->get();

        $labels = $data->pluck('date');
        $totals = $data->pluck('total');

        return [$data, $labels, $totals]; // 複数の変数を渡すのでいったん配列に入れる
    }
}