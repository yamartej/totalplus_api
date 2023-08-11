<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesReportController extends Controller
{
    public function salesByPeriod($start_date, $end_date)
    {
        $sales = DB::table('sales')
            ->whereBetween('created_at', [$start_date, $end_date])
            ->get();

        return response()->json($sales);
    }

    public function salesByCustomer($customer_id)
    {
        $sales = DB::table('sales')
            ->where('customer_id', $customer_id)
            ->get();

        return response()->json($sales);
    }

    public function salesByProduct($product_id)
    {
        $sales = DB::table('sales')
            ->where('product_id', $product_id)
            ->get();

        return response()->json($sales);
    }
}
