<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class AnalysisController extends Controller
{
    public function index(Request $request)
    {
        $subQuery = Order::betweenDate($request->startDate, $request->endDate);

        if($request->type === 'perDay') {
            $subQuery->where('status', true)
            ->groupBy('id')
            ->selectRaw('SUM(subtotal) AS totalPerPurchase, DATE_FORMAT(created_at, "%Y%m%d") AS date')
            ->groupBy('date');

            $data = DB::table($subQuery)
            ->groupBy('date')
            ->selectRaw('date, SUM(totalPerPurchase) AS total')
            ->get();

            $labels = $data->pluck('date');
            $totals = $data->pluck('total');
        }

        // Ajax通信なのでJsonで返却する必要がある
        return response()->json([
            'data' => $data,
            'type' => $request->type,
            'labels' => $labels,
            'totals' => $totals
        ], Response::HTTP_OK);
    }
}
