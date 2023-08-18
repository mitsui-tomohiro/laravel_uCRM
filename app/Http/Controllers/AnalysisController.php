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
        // 期間指定
        $startDate = '2022-08-01';
        $endDate = '2022-08-10';

        // 日別
        $subQuery = Order::betweenDate($startDate, $endDate)
        ->where('status', true)
        ->groupBy('id')
        ->selectRaw('id, sum(subtotal) as totalPerPurchase,
        DATE_FORMAT(created_at, "%Y%m%d") as date');

        $data = DB::table($subQuery)
        ->groupBy('date')
        ->selectRaw('date, sum(totalPerPurchase) as total')
        ->get();

        // dd($data);

        return Inertia::render('Analysis');
    }
}
