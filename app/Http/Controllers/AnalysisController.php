<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AnalysisController extends Controller
{
    public function index()
    {
        return Inertia::render('Analysis');
    }

    public function decile() {
        // 期間指定
        $startDate = '2022-08-01';
        $endDate = '2022-08-10';

        // 1. 購買IDごとにまとめる
        $subQuery = Order::betweenDate($startDate, $endDate)
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

        dd($data);
    }

    public function rfm() {
        // RFM分析
        // 期間指定
        $startDate = '2023-08-20';
        $endDate = '2023-08-22';

        // 1. 購買IDごとにまとめる
        $subQuery = Order::betweenDate($startDate, $endDate)
        ->groupBy('id')
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
        $rfmPrms = [14, 28, 60, 90, 7, 5, 3, 2, 300000, 200000, 100000, 30000];
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
        $total = DB::table($subQuery)->count();
        $rCount = DB::table($subQuery)
        ->groupBy('r')
        ->selectRaw('r, COUNT(r)')
        ->orderBy('r', 'DESC')
        ->get()
        ->pluck('COUNT(r)');
        
        $fCount = DB::table($subQuery)
        ->groupBy('f')
        ->selectRaw('f, COUNT(f)')
        ->orderBy('f', 'DESC')
        ->get()
        ->pluck('COUNT(f)');
        
        $mCount = DB::table($subQuery)
        ->groupBy('m')
        ->selectRaw('m, COUNT(m)')
        ->orderBy('m', 'DESC')
        ->get()
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
        ->groupBy('r')
        ->selectRaw('CONCAT("r_", r) AS rRank,
        COUNT(CASE WHEN f = 5 THEN 1 END) AS f_5,
        COUNT(CASE WHEN f = 4 THEN 1 END) AS f_4,
        COUNT(CASE WHEN f = 3 THEN 1 END) AS f_3,
        COUNT(CASE WHEN f = 2 THEN 1 END) AS f_2,
        COUNT(CASE WHEN f = 1 THEN 1 END) AS f_1')
        ->orderBy('rRank', 'DESC')
        ->get();
    }
}
