<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;

class PriceController extends Controller
{

    /*
        Controller Explanation:
        This Controller will handle all about Single and LP Price update from Coingecko

        List Function:
        1. update_single_price()
            Call API Coingecko from DB to get current Market Data
        2. update_lp_price()
            Calculating LP Price based on DB LP Pair and Single Token price
    */


    public function update_single_price(){
        // get list api token from db
        $data = DB::table("tbl_api as api")
                    ->join("tbl_token as token", "api.token_id", "=", "token.id")
                    ->select("token.id as token_id", "token.anon_name as token_name", "api.url as token_url")
                    ->get();

        foreach($data as $key){
            // execute api
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_URL, $key->token_url);
            $result = curl_exec($ch);
            curl_close($ch);
            $obj = json_decode($result);

            // insert data if not exists, update if exists
            DB::table('tbl_single_price_temp')
                ->updateOrInsert([
                    'token_id' => $key->token_id, 
                    'token_name' => $key->token_name
                ], [
                    'price' => round($obj->market_data->current_price->usd, 2),
                    'price_change_percentage' => round($obj->market_data->price_change_percentage_24h_in_currency->usd, 2),
                    'high' => round($obj->market_data->high_24h->usd, 2),
                    'low' => round($obj->market_data->low_24h->usd, 2),
                    'ath' => round($obj->market_data->ath->usd, 2),
                    'ath_change_percentage' => round($obj->market_data->ath_change_percentage->usd, 2)
                ]);
        }
    }


    public function update_lp_price(){
        // get data pair from db
        $data = DB::table("tbl_lp_pair as pair")
                    ->join('tbl_single_price_temp as price1', "pair.token1_id", "=", "price1.token_id")
                    ->join('tbl_single_price_temp as price2', "pair.token2_id", "=", "price2.token_id")
                    ->select("pair.id", "pair.token1_id", "pair.token2_id", "price1.token_name as token1_name", 
                    "price2.token_name as token2_name", "price1.price as token1_price", "price2.price as token2_price",
                    "price1.price_change_percentage as token1_price_change_percentage", "price2.price_change_percentage as token2_price_change_percentage", 
                    "pair.amount_token1", "pair.amount_token2", "pair.amount_lp")
                    ->get();

        // calculate and store
        foreach ($data as $key) {
            $lp_price = round((($key->token1_price * $key->amount_token1) + ($key->token2_price * $key->amount_token2)) / $key->amount_lp, 2);
            $lp_price_change_percentage = round(($key->token1_price_change_percentage + $key->token2_price_change_percentage) / 2, 2);
            DB::table('tbl_lp_price_temp')
                ->updateOrInsert([
                    'pair_id' => $key->id
                ], [
                    'pair_name' => $key->token1_name."-".$key->token2_name,
                    'price' => $lp_price,
                    'price_change_percentage' => $lp_price_change_percentage
                ]);
        }
    }

}
