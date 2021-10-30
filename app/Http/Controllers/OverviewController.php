<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\PriceController;
use App\Http\Controllers\HoldingController;

class OverviewController extends Controller
{
    /*
        Controller Explanation:
        Handle all function need to run first before the other to get valid data.

        List Function:
        1. return_token_price()
            Return JSON all token price 
        2. return_single_price()
            Get Single token price from DB
        3. return_lp_price()
            Get LP token price from DB
        4. return_holding_value_per_wallet()
            Return JSON holding value per wallet  
        5. return_holding_value()
            Return JSON data token currently hold in form of USD
        6. return_single_holding_value()
            Get Single token holding from DB 
        7. return_lp_holding_value()
            Get LP token holding from DB 
        8. return_yield_data()
            Calculate yield and treasury growth 
    */ 


    
    public function return_token_price(){
        return response(json_encode([
            "single_token_price" => $this->return_single_price(), 
            "lp_token_price" => $this->return_lp_price()
        ]), 200)->header('Content-Type', 'application/json');
    }


    
    public function return_single_price(){
        $data = DB::table("tbl_single_price_temp as temp")
                    ->join("tbl_token as token", "token.id", "=", "temp.token_id")
                    ->select("temp.token_id", "temp.token_name", "temp.price", "temp.price_change_percentage",
                    "temp.high", "temp.low", "temp.ath", "temp.ath_change_percentage")
                    ->get();
        return $data;
    }


    
    public function return_lp_price(){
        $data = DB::table("tbl_lp_price_temp as temp")
                    ->select("pair_id as token_id", "pair_name as token_name", "price", "price_change_percentage")
                    ->get();
        return $data;
    }


    
    public function return_holding_value_per_wallet(){
        // get the data and return as json
        $data = DB::table("tbl_holding_per_wallet_temp as hold")->get();
        return response(json_encode(["data" => $data]), 200)->header('Content-Type', 'application/json');
    }


    
    public function return_holding_value(){
        // updating data holding temp
        $holding_controller = new HoldingController();
        $holding_controller->generate_single_token_holding_in_usd();
        $holding_controller->generate_lp_token_holding_in_usd();

        // return the data
        return response(json_encode([
            "single_token_holding" => $this->return_single_holding_value(), 
            "lp_token_holding" => $this->return_lp_holding_value()
        ]), 200)->header('Content-Type', 'application/json');
    }


    
    public function return_single_holding_value(){
        $data = DB::table("tbl_single_token_holding_temp as hold")
                    ->join("tbl_single_price_temp as token", "token.token_id", "=", "hold.token_id")
                    ->select("hold.id as temp_id", "token.token_id", "token.token_name", 
                            "token.price", "token.price_change_percentage", "hold.amount_total", 
                            "hold.value_usd")
                    ->where([
                        ["hold.amount_total", ">", 0],
                        ["hold.value_usd", ">", 0]
                    ])
                    ->get();
        return $data;
    }


    
    public function return_lp_holding_value(){
        $data = DB::table("tbl_lp_token_holding_temp as hold")
                    ->join("tbl_lp_price_temp as pair", "pair.pair_id", "=", "hold.pair_id")
                    ->select("hold.id as temp_id", "pair.pair_id", "pair.pair_name", 
                            "pair.price", "pair.price_change_percentage", "hold.amount_total", 
                            "hold.value_usd")
                    ->where([
                        ["amount_total", ">", 0],
                        ["value_usd", ">", 0]
                    ])
                    ->get();
        return $data;
    }
    

    
    public function return_yield_data(){
        $treasury_balance = DB::table("tbl_holding_per_wallet_temp")
                                ->where("wallet_id", "=", 8)->sum("net_worth");
        DB::table('tbl_yield_data')
            ->updateOrInsert([
                'id' => 1
            ], [
                'data' => $treasury_balance,
                'caption' => "Treasury Balance",
                'variant' => "dollar"
            ]);

        $investors_balance = DB::table("tbl_holding_per_wallet_temp")
                                ->where("wallet_id", "!=", 1)
                                ->where("wallet_id", "!=", 2)
                                ->where("wallet_id", "!=", 8)
                                ->sum("net_worth");
        $treasury_progress = round(($treasury_balance / $investors_balance * 100), 2);
        DB::table('tbl_yield_data')
            ->updateOrInsert([
                'id' => 2
            ], [
                'data' => $treasury_progress,
                'caption' => "Treasury Progress",
                'variant' => "percentage"
            ]);

        $yield_single = DB::table("tbl_single_token_holding_temp")
                            ->where("yield_usd", ">", 0)
                            ->sum("yield_usd");
        $yield_lp = DB::table("tbl_lp_token_holding_temp")
                        ->where("yield_usd", ">", 0)
                        ->sum("yield_usd");
        $yield_estimate_usd = $yield_single + $yield_lp - 70;
        DB::table('tbl_yield_data')
            ->updateOrInsert([
                'id' => 3
            ], [
                'data' => $yield_estimate_usd,
                'caption' => "Yield Estimate ($)",
                'variant' => "dollar"
            ]);

        $total_holding_value = DB::table("tbl_holding_per_wallet_temp")->sum("net_worth");
        $yield_estimate_percentage = round(($yield_estimate_usd / $total_holding_value * 100), 2);
        DB::table('tbl_yield_data')
            ->updateOrInsert([
                'id' => 4
            ], [
                'data' => $yield_estimate_percentage,
                'caption' => "Yield Estimate (%)",
                'variant' => "percentage"
            ]);

        // return the data
        $data = DB::table("tbl_yield_data")->select("data", "caption", "variant")->get();
        return response(json_encode(["data" => $data]), 200)->header('Content-Type', 'application/json');
    }


    public function test(){
        
    }

}
