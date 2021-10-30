<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;

class HoldingController extends Controller
{
    
    /*
        Controller Explanation:
        Handle all about holding. per wallet, per token, or holding in form of USD value.

        List Function:
        1. generate_data_table_wallet_holding_temp()
            Calculate value holding per wallet, include PNL and Profit Sharing.
        2. generate_single_token_holding_in_usd()
            Generate data per single token hold for now, include amount hold, price, and value hold
        3. generate_lp_token_holding_in_usd() 
            Generate data per LP token hold for now, include amount hold, price, and value hold
    */ 

    
    public function generate_data_table_wallet_holding_temp(){
        $wallet = DB::table("tbl_wallet")->get();
        $pnl_profit_sharing = 0;

        foreach ($wallet as $w) {
            $wallet_hold_value = 0;
            $pnl = 0;
            $pnl_percentage = 0;

            // calculate single token
            $single = DB::table("tbl_single_token_holding as hold")
                    ->join("tbl_single_price_temp as price", "price.token_id", "=", "hold.token_id")
                    ->where("hold.wallet_id", "=", $w->id)
                    ->select("price.price", "hold.amount")
                    ->get();
            foreach ($single as $s) {
                $wallet_hold_value = $wallet_hold_value + round(($s->price * $s->amount), 2);
            }

            // calculate lp token
            $lp = DB::table("tbl_lp_token_holding as hold")
                    ->join("tbl_lp_price_temp as price", "price.pair_id", "=", "hold.pair_id")
                    ->where("hold.wallet_id", "=", $w->id)
                    ->select("price.price", "hold.amount")
                    ->get();
            foreach ($lp as $l) {
                $wallet_hold_value = $wallet_hold_value + round(($l->price * $l->amount), 2);
            }

            // calculate pnl and profit sharing
            if($w->name === "Primary" || $w->name === "Secondary"){
                $pnl = round(($wallet_hold_value - $w->capital), 2);
                $pnl_percentage = round(($pnl / $w->capital * 100), 2);
            } else if($w->name === "Treasury"){
                $pnl = 0;
                $pnl_percentage = 0;
            } else { // wallet_hold_value = ( kondisi 1 = 900 , kondisi 2 = 1100 ) dengan capital = 1000
                $pnl = round((($wallet_hold_value - $w->capital) / 2), 2); // -100 jadi -50 , 100 jadi 50
                $pnl_percentage = round(($pnl / $w->capital * 100), 2);
                $pnl_profit_sharing = $pnl_profit_sharing + $pnl; // -50 , 50
                $wallet_hold_value = $wallet_hold_value - $pnl; // 900 - (-50) , 1100 - 50
            }
            
            // insert data if not exists, update if exists
            DB::table('tbl_holding_per_wallet_temp')
                ->updateOrInsert([
                    'wallet_id' => $w->id
                ], [
                    'wallet_name' => $w->name,
                    'capital' => $w->capital,
                    'net_worth' => $wallet_hold_value,
                    'pnl' => $pnl,
                    'pnl_percentage' => $pnl_percentage,
                    'holding_ratio' => 0
                ]);
        }
        
        // if profit sharing is minus update treasury, if surplus update primary
        if($pnl_profit_sharing >= 0){
            // update primary because pnl surplus
            $treasury = DB::table("tbl_holding_per_wallet_temp")
                            ->where("wallet_id", "=", 1)
                            ->get();
            foreach ($treasury as $key) {
                $net_worth_new = round($key->net_worth + $pnl_profit_sharing, 2);
                $pnl_new = round(($net_worth_new - $key->capital), 2);
                $pnl_percentage_new = round(($pnl_new / $key->capital * 100), 2);
                $data = DB::table('tbl_holding_per_wallet_temp')
                        ->where('wallet_id', 1)
                        ->update([
                            'net_worth' => $net_worth_new,
                            'pnl' => $pnl_new,
                            'pnl_percentage' => $pnl_percentage_new
                        ]);
            }
        } else {
            // update treasury because pnl minus
            $treasury = DB::table("tbl_holding_per_wallet_temp")
                            ->where("wallet_id", "=", 8)
                            ->get();
            foreach ($treasury as $key) {
                $net_worth_new = round($key->net_worth + $pnl_profit_sharing, 2);
                $data = DB::table('tbl_holding_per_wallet_temp')
                        ->where('wallet_id', 8)
                        ->update([
                            'net_worth' => $net_worth_new
                        ]);
            }
        }

        // updating holding ratio
        $total_value_holding = DB::table("tbl_holding_per_wallet_temp")->sum("net_worth");
        $hold_temp = DB::table("tbl_holding_per_wallet_temp")->get();
        foreach ($hold_temp as $key) {
            $data = DB::table('tbl_holding_per_wallet_temp')
                ->where('wallet_id', $key->id)
                ->update([
                    'holding_ratio' => round(($key->net_worth / $total_value_holding * 100), 2)
                ]);
        }
    }


    
    public function generate_single_token_holding_in_usd(){
        $data = DB::table("tbl_token")->get();
        foreach ($data as $key) {
            $amount = DB::table("tbl_single_token_holding")
                        ->where("token_id", "=", $key->id)
                        ->sum("amount");
            $price = DB::table("tbl_single_price_temp")
                        ->where("token_id", "=", $key->id)
                        ->sum("price");
            // echo $key->real_name." = ".$amount." ".$price."<br>";
            DB::table('tbl_single_token_holding_temp')
                ->updateOrInsert([
                    'token_id' => $key->id
                ], [
                    'amount_total' => round($amount, 3),
                    'value_usd' => round(($amount * $price), 2),
                    'yield_usd' => round((($amount * $price) * $key->yield_percentage / 100 / 12), 2)
                ]);
        }
    }


    
    public function generate_lp_token_holding_in_usd(){
        $data = DB::table("tbl_lp_pair")->get();
        foreach ($data as $key) {
            $amount = DB::table("tbl_lp_token_holding")
                        ->where("pair_id", "=", $key->id)
                        ->sum("amount");
            $price = DB::table("tbl_lp_price_temp")
                        ->where("pair_id", "=", $key->id)
                        ->sum("price");
            // echo $key->token1_id."-".$key->token2_id." = ".$amount."    $".$price."<br>";
            DB::table('tbl_lp_token_holding_temp')
                ->updateOrInsert([
                    'pair_id' => $key->id
                ], [
                    'amount_total' => round($amount, 3),
                    'value_usd' => round(($amount * $price), 2),
                    'yield_usd' => round((($amount * $price) * $key->yield_percentage / 100 / 12), 2)
                ]);
        }
    }

    public function update_holding(){
        $single_hold = DB::table("tbl_single_token_holding")
                            ->select("token_id")->groupBy("token_id")->get();
        $lp_hold = DB::table("tbl_lp_token_holding as hold")
                        ->select("pair_id")->groupBy("pair_id")->get();
        
        foreach($single_hold as $key){
            $data = DB::table("tbl_single_token_holding")
                        ->where("token_id", $key->token_id)->get();
            $amount_total = DB::table("tbl_single_token_holding")
                                ->where("token_id", $key->token_id)->sum("amount");
            foreach($data as $key2){
                // handle if no token left after transaction
                $ratio = 0;
                if($amount_total > 0){
                    $ratio = round(($key2->amount / $amount_total * 100), 2);
                }
                
                DB::table('tbl_single_token_holding')
                    ->updateOrInsert([
                        'token_id' => $key->token_id,
                        'wallet_id' => $key2->wallet_id
                    ], [
                        'holding_ratio' => $ratio
                    ]);
            }
        }

        foreach($lp_hold as $key){
            $data = DB::table("tbl_lp_token_holding")
                        ->where("pair_id", $key->pair_id)->get();
            $amount_total = DB::table("tbl_lp_token_holding")
                                ->where("pair_id", $key->pair_id)->sum("amount");
            foreach($data as $key2){
                // handle if no token left after transaction
                $ratio = 0;
                if($amount_total > 0){
                    $ratio = round(($key2->amount / $amount_total * 100), 2);
                }
                
                DB::table('tbl_lp_token_holding')
                    ->updateOrInsert([
                        'pair_id' => $key->pair_id,
                        'wallet_id' => $key2->wallet_id
                    ], [
                        'holding_ratio' => $ratio
                    ]);
            }
        }
    }


    public function generate_new_ratio_lp_pair(){
        $data = DB::table("tbl_lp_pair as pair")
                    ->join("tbl_lp_price_temp as price_lp", "price_lp.pair_id", "=", "pair.id")
                    ->join("tbl_single_price_temp as price_token1", "price_token1.token_id", "=", "pair.token1_id")
                    ->join("tbl_single_price_temp as price_token2", "price_token2.token_id", "=", "pair.token2_id")
                    ->select("pair.id", "pair.amount_token1", "pair.amount_token2", "pair.amount_lp",
                            "price_lp.price as price_lp", "price_token1.price as price_token1",  
                            "price_token2.price as price_token2")
                    ->get();
        // dd($data);
        foreach($data as $key){
            $amount_token1 = round(($key->price_lp / 2) / $key->price_token1, 6);
            $amount_token2 = round(($key->price_lp / 2) / $key->price_token2, 6);
            // echo "<code>Pair ID: ".$key->id."<br>";
            // echo "Old pair data => (Amount 1: ".$key->amount_token1.") + (Amount 2: ".$key->amount_token2.") = (".$key->amount_lp." LP)<br>";
            // echo "New pair data => (Amount 1: ".$amount_token1.") + (Amount 2: ".$amount_token2.") = (1 LP)<br><br></code>";

            DB::table('tbl_lp_pair')
                    ->updateOrInsert([
                        'id' => $key->id
                    ], [
                        'amount_token1' => $amount_token1,
                        'amount_token2' => $amount_token2,
                        'amount_lp' => 1,
                    ]);
        }
    }

    public function generate_single_token_hold_per_wallet(){
        $single_token = array();
        $data_single_token = DB::table("tbl_single_token_holding_temp as hold")
                        ->join("tbl_token as token", "token.id", "=", "hold.token_id")
                        ->select("token.anon_name as token_name", "hold.token_id as token_id")
                        ->where("hold.value_usd", ">", 0)->get();
        foreach($data_single_token as $dt){
            $data_wallet = DB::table("tbl_single_token_holding as hold")
                            ->join("tbl_wallet as wallet", "wallet.id", "=", "hold.wallet_id")
                            ->select("wallet.name", "hold.amount", "hold.holding_ratio")
                            ->where("token_id", "=", $dt->token_id)->get();
            array_push($single_token, [
                "token_name" => $dt->token_name,
                "token_id" => $dt->token_id,
                "data" => $data_wallet
            ]);
        }

        $lp_token = array();
        $data_lp_token = DB::table("tbl_lp_token_holding_temp as hold")
                        ->join("tbl_lp_price_temp as pair", "pair.id", "=", "hold.pair_id")
                        ->select("pair.pair_name as token_name", "hold.pair_id as token_id")
                        ->where("hold.value_usd", ">", 0)->get();
        foreach($data_lp_token as $dt){
            $data_wallet = DB::table("tbl_lp_token_holding as hold")
                            ->join("tbl_wallet as wallet", "wallet.id", "=", "hold.wallet_id")
                            ->select("wallet.name", "hold.amount", "hold.holding_ratio")
                            ->where("pair_id", "=", $dt->token_id)->get();
            array_push($lp_token, [
                "token_name" => $dt->token_name,
                "token_id" => $dt->token_id,
                "data" => $data_wallet
            ]);
        }

        return response(json_encode([
            "single_token_holding" => $single_token,
            "lp_token_holding" => $lp_token
        ]), 200)->header('Content-Type', 'application/json');
    }

}
