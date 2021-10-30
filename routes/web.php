<?php

// testing
$router->get('/', 'OverviewController@test');

// -----------------------------------------------------------------

// to get current market data from coingecko and calculate LP price
$router->get('/update_price', 'RunItFirstController@update_price');
$router->get('/update_holding', 'HoldingController@update_holding');

// -----------------------------------------------------------------

// to get all token price and market data
$router->get('/get_price', 'OverviewController@return_token_price');
// to get holding value per wallet (for table summary)
$router->get('/get_holding_per_wallet', 'OverviewController@return_holding_value_per_wallet');
// to get what token hold for now and value in usd (for overview page) 
$router->get('/get_holding_value', 'OverviewController@return_holding_value');
// to get yield data (for overview page)
$router->get('/get_yield_data', 'OverviewController@return_yield_data');

// to get latest transaction
$router->get('/get_latest_transaction', 'TransferController@get_latest_transaction');

$router->get('/get_detail_holding_per_wallet', 'HoldingController@generate_single_token_hold_per_wallet');

$router->get('/test', 'HoldingController@generate_single_token_hold_per_wallet');
$router->post('/compound', 'TransferController@compound');
$router->post('/swap', 'TransferController@swap');
$router->post('/add', 'TransferController@add');
$router->post('/remove', 'TransferController@remove');