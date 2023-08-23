<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class RFMService
{
    public static function rfm($subQuery, $rfmPrms) {
        // RFM分析
        // 1. 購買IDごとにまとめる
        $subQuery = $subQuery->groupBy('id')
        ->selectRaw('id, customer_id, customer_name, SUM(subtotal) AS totalPerPurchase, created_at');

        // datediffで日付の差分、maxで日付の最新日
        // 2. 会員ごとにまとめて最終購入日、回数、合計金額を取得
        $subQuery = DB::table($subQuery)
        ->groupBy('customer_id')
        ->groupBy('customer_name')
        ->selectRaw('customer_id, customer_name, 
        MAX(created_at) AS recentDate, 
        DATEDIFF(NOW(), MAX(created_at)) AS recency, 
        COUNT(customer_id) AS frequency, 
        SUM(totalPerPurchase) AS monetary');

        // 4. 会員ごとのRFMランクを計算
        $subQuery = DB::table($subQuery)
        ->selectRaw('customer_id, customer_name, recentDate, recency, frequency, monetary,
        CASE
            WHEN recency < ? THEN 5
            WHEN recency < ? THEN 4
            WHEN recency < ? THEN 3
            WHEN recency < ? THEN 2
            ELSE 1
        END AS r,
        CASE
            WHEN frequency >= ? THEN 5
            WHEN frequency >= ? THEN 4
            WHEN frequency >= ? THEN 3
            WHEN frequency >= ? THEN 2
            ELSE 1
        END AS f,
        CASE
            WHEN monetary >= ? THEN 5
            WHEN monetary >= ? THEN 4
            WHEN monetary >= ? THEN 3
            WHEN monetary >= ? THEN 2
            ELSE 1
        END AS m', $rfmPrms);
        
        // 5. ランクごとの数を計算する
        $totals = DB::table($subQuery)->count();
        $rCount = DB::table($subQuery)
        ->rightJoin('ranks', 'ranks.rank', '=', 'r')
        ->groupBy('rank')
        ->selectRaw('rank AS r, COUNT(r)')
        ->orderBy('r', 'DESC')
        ->pluck('COUNT(r)');
       
        $fCount = DB::table($subQuery)
        ->rightJoin('ranks', 'ranks.rank', '=', 'f')
        ->groupBy('rank')
        ->selectRaw('rank AS f, COUNT(f)')
        ->orderBy('f', 'DESC')
        ->pluck('COUNT(f)');
        
        $mCount = DB::table($subQuery)
        ->rightJoin('ranks', 'ranks.rank', '=', 'm')
        ->groupBy('rank')
        ->selectRaw('rank AS m, COUNT(m)')
        ->orderBy('m', 'DESC')
        ->pluck('COUNT(m)');

        $eachCount = []; // Vue側に渡す用の空の配列
        $rank = 5; // 初期値5

        for ($i = 0; $i < 5; $i++) {
            array_push($eachCount, [
                'rank' => $rank,
                'r' => $rCount[$i],
                'f' => $fCount[$i],
                'm' => $mCount[$i],
            ]);
            $rank--; // rankを1ずつ減らす
        }
        
        // 6. RとFで2次元で表示してみる
        $data = DB::table($subQuery)
        ->rightJoin('ranks', 'ranks.rank', '=', 'r')
        ->groupBy('rank')
        ->selectRaw('CONCAT("r_", rank) AS rRank,
        COUNT(CASE WHEN f = 5 THEN 1 END) AS f_5,
        COUNT(CASE WHEN f = 4 THEN 1 END) AS f_4,
        COUNT(CASE WHEN f = 3 THEN 1 END) AS f_3,
        COUNT(CASE WHEN f = 2 THEN 1 END) AS f_2,
        COUNT(CASE WHEN f = 1 THEN 1 END) AS f_1')
        ->orderBy('rRank', 'DESC')
        ->get();

        return [$data, $totals, $eachCount]; // 複数の変数を渡すのでいったん配列に入れる
    }
}