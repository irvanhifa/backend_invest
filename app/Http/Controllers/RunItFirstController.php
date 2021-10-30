<?php

namespace App\Http\Controllers;
use App\Http\Controllers\PriceController;
use App\Http\Controllers\HoldingController;

class RunItFirstController extends Controller
{

    /*
        Controller Explanation:
        Handle all function need to run first before the other to get valid data.

        List Function:
        1. update_price()
            execute update single and lp price, and generate data holding per wallet 
    */ 

    
    public function update_price(){
        $price_controller = new PriceController();
        $holding_controller = new HoldingController();
        
        // updating price
        $price_controller->update_single_price();
        $price_controller->update_lp_price();

        // updating holding per wallet
        $holding_controller->generate_data_table_wallet_holding_temp();

        // updating ratio lp pair
        $holding_controller->generate_new_ratio_lp_pair();

        return response(json_encode(["data" => "DB Updated"]), 200)->header('Content-Type', 'application/json');
    }
}
