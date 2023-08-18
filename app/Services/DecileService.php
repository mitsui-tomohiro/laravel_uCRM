<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DecileService
{
    public static function decile($subQuery) {
        // 1. 購買IDごとにまとめる
        $subQuery = $subQuery
            ->groupBy('id')
            ->selectRaw('id, customer_id, customer_name, SUM(subtotal) AS totalPerPurchase');

        // 2. 会員ごとにまとめて購入金額順にソートする
        $subQuery = DB::table($subQuery)
            ->groupBy('customer_id')
            ->groupBy('customer_name')
            ->selectRaw('customer_id, customer_name, SUM(totalPerPurchase) AS total')
            ->orderBy('total', 'desc');
        
        // statementで変数を設定できる
        // SET @変数名 = 値 (mysqlの書き方)
        // 3. 購入金額順に連番を振る
        DB::statement('SET @row_num = 0;');
        $subQuery = DB::table($subQuery)
            ->selectRaw('@row_num := @row_num + 1 AS row_num, customer_id, customer_name, total');

        // 4. 全体の件数を数え、1/10の値や合計金額を取得
        $count = DB::table($subQuery)->count();
        $total = DB::table($subQuery)->selectRaw('SUM(total) AS total')->get();
        $total = $total[0]->total; // 構成比用

        $decile = ceil($count / 10); // 10分の1の件数を変数に入れる

        $bindValues = [];
        $tempValue = 0;
        for($i = 1; $i <= 10; $i++) {
            array_push($bindValues, 1 + $tempValue);
            $tempValue += $decile;
            array_push($bindValues, 1 + $tempValue);
        }

        // 5. 10分割しグループごとに数字を振る
        DB::statement('SET @row_num = 0;');
        $subQuery = DB::table($subQuery)
            ->selectRaw('
                row_num,
                customer_id,
                customer_name,
                total,
                CASE
                    WHEN ? <= row_num AND row_num < ? THEN 1
                    WHEN ? <= row_num AND row_num < ? THEN 2
                    WHEN ? <= row_num AND row_num < ? THEN 3
                    WHEN ? <= row_num AND row_num < ? THEN 4
                    WHEN ? <= row_num AND row_num < ? THEN 5
                    WHEN ? <= row_num AND row_num < ? THEN 6
                    WHEN ? <= row_num AND row_num < ? THEN 7
                    WHEN ? <= row_num AND row_num < ? THEN 8
                    WHEN ? <= row_num AND row_num < ? THEN 9
                    WHEN ? <= row_num AND row_num < ? THEN 10
                END AS decile
            ', $bindValues); // SelectRaw第二引数にバインドしたい数値(配列)を入れる

        // round, avg は mysql の関数
        // 6. グループごとの合計・平均
        $subQuery = DB::table($subQuery)
            ->groupBy('decile')
            ->selectRaw('decile, ROUND(AVG(total)) AS average, SUM(total) AS totalPerGroup');

        // 7. 構成比
        DB::statement("SET @total = $total;");
        $data = DB::table($subQuery)
            ->selectRaw('decile, average, totalPerGroup, ROUND(100 * totalPerGroup / @total, 1) AS totalRatio')
            ->get();

        $labels = $data->pluck('decile');
        $totals = $data->pluck('totalPerGroup');

        return [$data, $labels, $totals]; // 複数の変数を渡すのでいったん配列に入れる
    }
}