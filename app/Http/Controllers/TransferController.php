<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\HoldingController;

class TransferController extends Controller
{

    /*
        Controller Explanation:
        Lorem ipsum dolor sit amet consectetur adipisicing elit.

        List Function:
        1. function_name()
            Lorem ipsum dolor sit amet consectetur adipisicing elit. 
    */ 

    // deposit
    public function deposit(){

    }

    // withdraw
    public function withdraw(){
        
    }

    // transfer
    public function transfer(){
        
    }


    public function get_latest_transaction(){
        
        $time = DB::table("tbl_transaction")
                    ->orderBy('id', 'desc')
                    ->select('date')
                    ->first();
        
            $data = DB::table("tbl_transaction")
                    ->where('date', $time->date)
                    ->get();
        
        return response(json_encode(["data" => $data]), 200)->header('Content-Type', 'application/json');
    }


    public function get_token_list(){
        $data_single = DB::table("tbl_single_price_temp")
                            ->select("token_id as id", "token_name as name")
                            ->get();
        $data_lp = DB::table("tbl_lp_price_temp")
                            ->select("pair_id as id", "pair_name as name")
                            ->get();
        return response(json_encode([
            "single_token" => $data_single, 
            "lp_token" => $data_lp
        ]), 200)->header('Content-Type', 'application/json');
    }
    
    // compound
    public function compound(){
        $amount = $_POST["amount"];
        $token_id_single = $_POST["token_id_single"];
        $token_id_lp = $_POST["token_id_lp"];
        $blockchain = $_POST["blockchain"];

        date_default_timezone_set("Asia/Jakarta");
        $date_transaction = date("Y-m-d");
        $amount_fee = 0;
        $token_fee = "";

        if($blockchain == "Binance"){
            $amount_fee = 0.003;
            $token_fee = "A";
        } else if($blockchain == "Polygon"){
            $amount_fee = 0.0025;
            $token_fee = "K";
        } else if($blockchain == "Solana"){
            $amount_fee = 0.0001;
            $token_fee = "S";
        } else if($blockchain == "Avalanche"){
            $amount_fee = 0.05;
            $token_fee = "M";
        } else if($blockchain == "Fantom"){
            $amount_fee = 0.06;
            $token_fee = "G";
        } else { /* do nothing */ }
        
        if(!empty($token_id_single)){
            $treasury_hold = DB::table("tbl_single_token_holding")
                                ->where([
                                    ["wallet_id", "=", 8],
                                    ["token_id", "=", $token_id_single]
                                ])->sum("amount");
            $data = DB::table('tbl_single_token_holding')
                        ->where([
                            ["wallet_id", "=", 8],
                            ["token_id", "=", $token_id_single]
                        ])
                        ->update([
                            'amount' => round($amount + $treasury_hold, 3)
                        ]);
            
            // add transaction to tbl_transaction
            $token_name = DB::table("tbl_token")->where("id", $token_id_single)->select("anon_name")->get();
            foreach($token_name as $token){
                // echo $from->pair_name." => ".$to->anon_name."<br>";
                DB::table('tbl_transaction')->insert([
                    'token_from' => $token->anon_name, 
                    'amount_from' => $amount,
                    'token_to' => $token->anon_name,
                    'amount_to' => $amount,
                    'token_fee' => $token_fee,
                    'amount_fee' => $amount_fee,
                    'transaction_type' => "Compound",
                    'date' => $date_transaction
                ]);
            }

            // echo "berhasil";
        } else if(!empty($token_id_lp)){
            $treasury_hold = DB::table("tbl_lp_token_holding")
                                ->where([
                                    ["wallet_id", "=", 8],
                                    ["pair_id", "=", $token_id_lp]
                                ])->sum("amount");
            $data = DB::table('tbl_lp_token_holding')
                        ->where([
                            ["wallet_id", "=", 8],
                            ["pair_id", "=", $token_id_lp]
                        ])
                        ->update([
                            'amount' => round($amount + $treasury_hold, 3)
                        ]);

            // add transaction to tbl_transaction
            $token_name = DB::table("tbl_lp_price_temp")->where("pair_id", $token_id_lp)->select("pair_name")->get();
            foreach($token_name as $token){
                // echo $from->pair_name." => ".$to->anon_name."<br>";
                DB::table('tbl_transaction')->insert([
                    'token_from' => $token->pair_name, 
                    'amount_from' => $amount,
                    'token_to' => $token->pair_name,
                    'amount_to' => $amount,
                    'token_fee' => $token_fee,
                    'amount_fee' => $amount_fee,
                    'transaction_type' => "compound",
                    'date' => $date_transaction
                ]);
            }

            // echo "berhasil";
        }else {
            echo "token id kosong baik single maupun lp";
        }

        // update holding ratio
        $holding_controller = new HoldingController();
        $holding_controller->update_holding();

        return response(json_encode(["message" => "Compound Success."]), 200)->header('Content-Type', 'application/json');
    }

    public function swap(){
        $amount_from = $_POST["amount_from"];
        $token_from = $_POST["token_from"];

        $amount_to = $_POST["amount_to"];
        $token_to = $_POST["token_to"];

        $transaction_type = $_POST["transaction_type"];
        $blockchain = $_POST["blockchain"];

        if($transaction_type == "sswap"){

            // this for swap single token to single token
            $this->exec_swap($amount_from, $token_from, $amount_to, $token_to, "swap", $blockchain);

        } else if($transaction_type == "lswap") {
            // this for swap lp token to lp token

            // get pair data
            $pair_token_from = DB::table("tbl_lp_pair")->where("id", $token_from)->get();
            $pair_token_to = DB::table("tbl_lp_pair")->where("id", $token_to)->get();

            // divide amount
            $amount_from = round($amount_from / 2, 3); 
            $amount_to = round($amount_to / 2, 3); 
            
            foreach($pair_token_from as $from){
                $amount1_to = round((($amount_from * 2) / $from->amount_lp) * $from->amount_token1, 3);
                $amount2_to = round((($amount_from * 2) / $from->amount_lp) * $from->amount_token2, 3);

                // remove
                $this->exec_swap($amount_from, $token_from, $amount1_to, $from->token1_id, "remove", $blockchain);
                $this->exec_swap($amount_from, $token_from, $amount2_to, $from->token2_id, "remove", $blockchain);
                // echo "Remove:<br>";
                // echo "1. From Token: ".$token_from." amount: ".$amount_from." To Token: ".$from->token1_id." amount: ".$amount1_to."<br>";
                // echo "2. From Token: ".$token_from." amount: ".$amount_from." To Token: ".$from->token2_id." amount: ".$amount2_to."<br>";
                
                foreach($pair_token_to as $to){
                    $amount1_from = round((($amount_to * 2) / $to->amount_lp) * $to->amount_token1, 3);
                    $amount2_from = round((($amount_to * 2) / $to->amount_lp) * $to->amount_token2, 3);
                    
                    // sswap
                    $this->exec_swap($amount1_to, $from->token1_id, $amount1_from, $to->token1_id, "swap", $blockchain);
                    $this->exec_swap($amount2_to, $from->token2_id, $amount2_from, $to->token2_id, "swap", $blockchain);
                    // echo "Swap:<br>";
                    // echo "1. From Token: ".$from->token1_id." amount: ".$amount1_to." To Token: ".$to->token1_id." amount: ".$amount1_from."<br>";
                    // echo "2. From Token: ".$from->token2_id." amount: ".$amount2_to." To Token: ".$to->token2_id." amount: ".$amount2_from."<br>";

                    // add
                    $this->exec_swap($amount1_from, $to->token1_id, $amount_to, $token_to, "add", $blockchain);
                    $this->exec_swap($amount2_from, $to->token2_id, $amount_to, $token_to, "add", $blockchain);
                    // echo "Add:<br>";
                    // echo "1. From Token: ".$to->token1_id." amount: ".$amount1_from." To Token: ".$token_to." amount: ".$amount_to."<br>";
                    // echo "2. From Token: ".$to->token2_id." amount: ".$amount2_from." To Token: ".$token_to." amount: ".$amount_to."<br>";
                }
            }
        } else {
            return response(json_encode(["message" => "Swap failed, transaction type not eligible."]), 500)->header('Content-Type', 'application/json');
        }

        // // update holding ratio
        // $holding_controller = new HoldingController();
        // $holding_controller->update_holding();

        return response(json_encode(["message" => "Swap Success."]), 200)->header('Content-Type', 'application/json');
    }


    public function add(){
        $amount1_from = $_POST["amount1_from"];
        $token1_from = $_POST["token1_from"];

        $amount2_from = $_POST["amount2_from"];
        $token2_from = $_POST["token2_from"];

        // divide by 2 before execute
        $amount_to = round($_POST["amount_to"] / 2, 3);
        $token_to = $_POST["token_to"];

        $transaction_type = $_POST["transaction_type"];
        $blockchain = $_POST["blockchain"];

        $this->exec_swap($amount1_from, $token1_from, $amount_to, $token_to, $transaction_type, $blockchain);
        $this->exec_swap($amount2_from, $token2_from, $amount_to, $token_to, $transaction_type, $blockchain);

        // // update holding ratio
        // $holding_controller = new HoldingController();
        // $holding_controller->update_holding();

        return response(json_encode(["message" => "Add LP Success."]), 200)->header('Content-Type', 'application/json');
    }

    public function remove(){
        //divide by 2 before execute
        $amount_from = round($_POST["amount_from"] / 2, 3);
        $token_from = $_POST["token_from"];

        $amount1_to = $_POST["amount1_to"];
        $token1_to = $_POST["token1_to"];

        $amount2_to = $_POST["amount2_to"];
        $token2_to = $_POST["token2_to"];

        $transaction_type = $_POST["transaction_type"];
        $blockchain = $_POST["blockchain"];

        $this->exec_swap($amount_from, $token_from, $amount1_to, $token1_to, $transaction_type, $blockchain);
        $this->exec_swap($amount_from, $token_from, $amount2_to, $token2_to, $transaction_type, $blockchain);

        // // update holding ratio
        // $holding_controller = new HoldingController();
        // $holding_controller->update_holding();

        return response(json_encode(["message" => "Remove LP Success."]), 200)->header('Content-Type', 'application/json');
    }

    public function exec_swap($amount_from, $token_from, $amount_to, $token_to, $transaction_type, $blockchain){
        date_default_timezone_set("Asia/Jakarta");
        $date_transaction = date("Y-m-d");
        $amount_fee = 0;
        $token_fee = "";

        if($blockchain == "Binance"){
            $amount_fee = 0.003;
            $token_fee = "A";
        } else if($blockchain == "Polygon"){
            $amount_fee = 0.0025;
            $token_fee = "K";
        } else if($blockchain == "Solana"){
            $amount_fee = 0.0001;
            $token_fee = "S";
        } else if($blockchain == "Avalanche"){
            $amount_fee = 0.05;
            $token_fee = "M";
        } else if($blockchain == "Fantom"){
            $amount_fee = 0.06;
            $token_fee = "G";
        } else { /* do nothing */ }
        
        if($transaction_type == "swap"){ // swap single token (single token to single token)
            $wallet = DB::table("tbl_single_token_holding")
                                ->where("token_id", $token_from)->get();
            foreach($wallet as $key){
                // subtraction token_from
                $amount_after_subtraction = round(($key->amount - ($amount_from * $key->holding_ratio / 100)), 3);
                DB::table('tbl_single_token_holding')
                    ->updateOrInsert([
                        'token_id' => $token_from,
                        'wallet_id' => $key->wallet_id
                    ], [
                        'amount' => $amount_after_subtraction
                    ]);

                // addition token_to
                $amount_to_wallet_have = DB::table("tbl_single_token_holding")
                                            ->where([
                                                ["token_id", $token_to],
                                                ["wallet_id", $key->wallet_id]
                                            ])->sum("amount");
                $amount_after_addition = round($amount_to_wallet_have + ($amount_to * $key->holding_ratio / 100), 3);
                DB::table('tbl_single_token_holding')
                    ->updateOrInsert([
                        'token_id' => $token_to,
                        'wallet_id' => $key->wallet_id
                    ], [
                        'amount' => $amount_after_addition
                    ]);
            }

            // add transaction to tbl_transaction
            $token_from_name = DB::table("tbl_token")->where("id", $token_from)->select("anon_name")->get();
            $token_to_name = DB::table("tbl_token")->where("id", $token_to)->select("anon_name")->get();
            foreach($token_from_name as $from){
                foreach($token_to_name as $to){
                    // echo $from->pair_name." => ".$to->anon_name."<br>";
                    DB::table('tbl_transaction')->insert([
                        'token_from' => $from->anon_name,
                        'amount_from' => $amount_from,
                        'token_to' => $to->anon_name,
                        'amount_to' => $amount_to,
                        'token_fee' => $token_fee,
                        'amount_fee' => $amount_fee,
                        'transaction_type' => $transaction_type,
                        'date' => $date_transaction
                    ]);
                }
            }

        } else if($transaction_type == "add"){ // swap lp token (single token to lp token)
            $wallet = DB::table("tbl_single_token_holding")
                                ->where("token_id", $token_from)->get();
            foreach($wallet as $key){
                // subtraction token_from
                $amount_after_subtraction = round(($key->amount - ($amount_from * $key->holding_ratio / 100)), 3);
                DB::table('tbl_single_token_holding')
                    ->updateOrInsert([
                        'token_id' => $token_from,
                        'wallet_id' => $key->wallet_id
                    ], [
                        'amount' => $amount_after_subtraction
                    ]);

                // addition token_to
                $amount_to_wallet_have = DB::table("tbl_lp_token_holding")
                                            ->where([
                                                ["pair_id", $token_to],
                                                ["wallet_id", $key->wallet_id]
                                            ])->sum("amount");
                $amount_after_addition = round($amount_to_wallet_have + ($amount_to * $key->holding_ratio / 100), 3);
                DB::table('tbl_lp_token_holding')
                    ->updateOrInsert([
                        'pair_id' => $token_to,
                        'wallet_id' => $key->wallet_id
                    ], [
                        'amount' => $amount_after_addition
                    ]);
            }

            // add transaction to tbl_transaction
            $token_from_name = DB::table("tbl_token")->where("id", $token_from)->select("anon_name")->get();
            $token_to_name = DB::table("tbl_lp_price_temp")->where("pair_id", $token_to)->select("pair_name")->get();
            foreach($token_from_name as $from){
                foreach($token_to_name as $to){
                    // echo $from->pair_name." => ".$to->anon_name."<br>";
                    DB::table('tbl_transaction')->insert([
                        'token_from' => $from->anon_name,
                        'amount_from' => $amount_from,
                        'token_to' => $to->pair_name,
                        'amount_to' => $amount_to,
                        'token_fee' => $token_fee,
                        'amount_fee' => $amount_fee,
                        'transaction_type' => $transaction_type,
                        'date' => $date_transaction
                    ]);
                }
            }
            
        } else if($transaction_type == "remove"){ // swap lp token (lp token to single token)
            $wallet = DB::table("tbl_lp_token_holding")
                                ->where("pair_id", $token_from)->get();
            foreach($wallet as $key){
                // subtraction token_from
                $amount_after_subtraction = round(($key->amount - ($amount_from * $key->holding_ratio / 100)), 3);
                DB::table('tbl_lp_token_holding')
                    ->updateOrInsert([
                        'pair_id' => $token_from,
                        'wallet_id' => $key->wallet_id
                    ], [
                        'amount' => $amount_after_subtraction
                    ]);

                // addition token_to
                $amount_to_wallet_have = DB::table("tbl_single_token_holding")
                                            ->where([
                                                ["token_id", $token_to],
                                                ["wallet_id", $key->wallet_id]
                                            ])->sum("amount");
                $amount_after_addition = round($amount_to_wallet_have + ($amount_to * $key->holding_ratio / 100), 3);
                DB::table('tbl_single_token_holding')
                    ->updateOrInsert([
                        'token_id' => $token_to,
                        'wallet_id' => $key->wallet_id
                    ], [
                        'amount' => $amount_after_addition
                    ]);
            }

            // add transaction to tbl_transaction
            $token_from_name = DB::table("tbl_lp_price_temp")->where("pair_id", $token_from)->select("pair_name")->get();
            $token_to_name = DB::table("tbl_token")->where("id", $token_to)->select("anon_name")->get();
            foreach($token_from_name as $from){
                foreach($token_to_name as $to){
                    // echo $from->pair_name." => ".$to->anon_name."<br>";
                    DB::table('tbl_transaction')->insert([
                        'token_from' => $from->pair_name,
                        'amount_from' => $amount_from,
                        'token_to' => $to->anon_name,
                        'amount_to' => $amount_to,
                        'token_fee' => $token_fee,
                        'amount_fee' => $amount_fee,
                        'transaction_type' => $transaction_type,
                        'date' => $date_transaction
                    ]);
                }
            }

        } else {
            echo "bukan untuk single atau lp";
        }

        // update holding ratio
        $holding_controller = new HoldingController();
        $holding_controller->update_holding();
    }



   
}
