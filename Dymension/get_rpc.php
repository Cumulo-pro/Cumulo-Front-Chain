<!--
extract RPC cumulo query 
-->

<?php
//error_reporting(0);
$rpc ="http://74.208.16.201:26647/"; //default value
header('Content-Type: text/html; charset=utf-8');
$net="froopyland_100-1";


//extract from RPC
switch($pide=$_REQUEST['dato']){
case $pide=="get_block_rpc":
$data_json = file_get_contents($rpc.'/abci_info?');
$json = json_decode($data_json);
$allData=$json->result;
	$response=$allData->response;
		$last_block_height=$response->last_block_height;
		echo $last_block_height;
break;
case $pide=="get_hash":
$param=$_REQUEST['param'];
$data_json = file_get_contents($rpc.'/block?height=%22'.$param.'%22');
$json = json_decode($data_json);
$allData=$json->result;
	$block=$allData->block_id;
		$hash=$block->hash;
		echo "chain-id: ".$net;
		echo "block hash ".$param.": ".$hash;
break;
case $pide=="get_token":
$data_json = file_get_contents('http://74.208.16.201:26647/genesis?');
$json = json_decode($data_json);
$allData=$json->result;
	$genesis=$allData->genesis;
		$app_state=$genesis->app_state;
			$bank=$app_state->bank;
				$denom_metadata=$bank->denom_metadata;		
				$symbol=$denom_metadata{0}->symbol;
				echo $symbol;
break;
case $pide=="get_base_token":
$data_json = file_get_contents('http://74.208.16.201:26647/genesis?');
$json = json_decode($data_json);
$allData=$json->result;
	$genesis=$allData->genesis;
		$app_state=$genesis->app_state;
			$bank=$app_state->bank;
				$denom_metadata=$bank->denom_metadata;		
				$base=$denom_metadata{0}->base;
				echo $base;
break;
case $pide=="get_max_validators":
$data_json = file_get_contents('http://74.208.16.201:26647/genesis?');
$json = json_decode($data_json);
$allData=$json->result;
	$genesis=$allData->genesis;
		$app_state=$genesis->app_state;
			$staking=$app_state->staking;
				$params=$staking->params;		
					$max_validators=$params->max_validators;
				echo $max_validators;
break;
case $pide=="get_unbonding_time":
$data_json = file_get_contents('http://74.208.16.201:26647/genesis?');
$json = json_decode($data_json);
$allData=$json->result;
	$genesis=$allData->genesis;
		$app_state=$genesis->app_state;
			$staking=$app_state->staking;
				$params=$staking->params;		
					$unbonding_time=$params->unbonding_time;
				echo intval($unbonding_time)/60/60/24;
break;
case $pide=="get_val_sign":
$param=$_REQUEST['param'];
$data_json = file_get_contents($rpc.'/signed_block?height=%22'.$param.'%22');
$json = json_decode($data_json);
$allData=$json->result;
	$validator_set=$allData->validator_set;
	$proposer=$validator_set->proposer;
		$pubkey=$proposer->pub_key;
			$value=$pubkey->value;
			echo $value;
break;
case $pide=="get_moniker_pubkey":
$data_json = file_get_contents($rpc.'/genesis?');
$json = json_decode($data_json);
$allData=$json->result;
$genesis=$allData->genesis;
		$app_state=$genesis->app_state;
			$genutil=$app_state->genutil;
				$gen_txs=$genutil->gen_txs;
$pkey=urlencode($_REQUEST['param']);
//$pkey="WyoR+T2WxbuJvI/4B+27iVvc+mu3y6pXF+OFzglQw68=";
//echo str_replace(" ","+",urldecode($pkey));
		foreach ($gen_txs as $val) {
					$body=$val->body;
						$messages=$body->messages;
						foreach ($messages as $message) {
							$description=$message->description;
								$moniker=$description->moniker;
								$pubkey=$message->pubkey;
								$key=$pubkey->key;
								if ($key==str_replace(" ","+",urldecode($pkey))){
									echo $moniker;
								}
						}
				}
break;
case $pide=="get_val_sign_moniker":
$param=$_REQUEST['param'];
$data_json = file_get_contents($rpc.'/signed_block?height=%22'.$param.'%22');
$json = json_decode($data_json);
$allData=$json->result;
	$validator_set=$allData->validator_set;
	$proposer=$validator_set->proposer;
		$pubkey=$proposer->pub_key;
			$value=$pubkey->value;			
	$data_json = file_get_contents($rpc.'/genesis?');
$json = json_decode($data_json);
$allData=$json->result;
$genesis=$allData->genesis;
		$app_state=$genesis->app_state;
			$genutil=$app_state->genutil;
				$gen_txs=$genutil->gen_txs;
$pkey=$value;
//$pkey="WyoR+T2WxbuJvI/4B+27iVvc+mu3y6pXF+OFzglQw68=";
//echo str_replace(" ","+",urldecode($pkey));
		foreach ($gen_txs as $val) {
					$body=$val->body;
						$messages=$body->messages;
						foreach ($messages as $message) {
							$description=$message->description;
								$moniker=$description->moniker;
								$pubkey=$message->pubkey;
								$key=$pubkey->key;
								if ($key==str_replace(" ","+",urldecode($pkey))){
									echo $moniker;
								}
						}
				}			
break;
case $pide=="get_block_sign_moniker":
$data_json = file_get_contents($rpc.'/abci_info?');
$json = json_decode($data_json);
$allData=$json->result;
	$response=$allData->response;
		$last_block_height=$response->last_block_height;
		
$param=$last_block_height;
$data_json = file_get_contents($rpc.'/signed_block?height="'.$param.'"');
$json = json_decode($data_json);
$allData=$json->result;
	$validator_set=$allData->validator_set;
	$proposer=$validator_set->proposer;
		$pubkey=$proposer->pub_key;
			$value=$pubkey->value;			
	$data_json = file_get_contents($rpc.'/genesis?');
$json = json_decode($data_json);
$allData=$json->result;
$genesis=$allData->genesis;
		$app_state=$genesis->app_state;
			$genutil=$app_state->genutil;
				$gen_txs=$genutil->gen_txs;
$pkey=$value;
//$pkey="WyoR+T2WxbuJvI/4B+27iVvc+mu3y6pXF+OFzglQw68=";
//echo str_replace(" ","+",urldecode($pkey));
		foreach ($gen_txs as $val) {
					$body=$val->body;
						$messages=$body->messages;
						foreach ($messages as $message) {
							$description=$message->description;
								$moniker=$description->moniker;
								$pubkey=$message->pubkey;
								$key=$pubkey->key;
								if ($key==str_replace(" ","+",urldecode($pkey))){
									echo $moniker;
								}
						}
				}			
break;
case $pide=="get_block_sign_pubkey":
$data_json = file_get_contents($rpc.'/abci_info?');
$json = json_decode($data_json);
$allData=$json->result;
	$response=$allData->response;
		$last_block_height=$response->last_block_height;
		
$param=$last_block_height;
$data_json = file_get_contents($rpc.'/signed_block?height="'.$param.'"');
$json = json_decode($data_json);
$allData=$json->result;
	$validator_set=$allData->validator_set;
	$proposer=$validator_set->proposer;
		$pubkey=$proposer->pub_key;
			$value=$pubkey->value;
			echo $value;	
break;
case $pide=="get_latest_block_time":
$data_json = file_get_contents($rpc.'/status?');
$json = json_decode($data_json);
$allData=$json->result;
		$sync_info=$allData->sync_info;
			$latest_block_time=$sync_info->latest_block_time;
break;
case $pide=="get_rpc_status":
//extract from RPC
			if($data_json = @file_get_contents($rpc.'/status?')){
				$json = json_decode($data_json);
				$allData=$json->result;
				$sync_info=$allData->sync_info;
				$catching_up=$sync_info->catching_up;
				if ($catching_up==false){
				echo "<div style='background:green;border-radius: 50%;width: 20px;height:20px;display:inline-block;position:relative;top:5px'> </div>";
											}else{
												echo "<div style='background:red;border-radius: 50%;width: 20px;height:20px;display:inline-block'> </div>";
											}		
				}else{
				echo "<div style='background:red;border-radius: 50%;width: 20px;height:20px;display:inline-block'> </div>";
}
break;
case $pide=="get_validator_data":
$data_json = file_get_contents($rpc.'/genesis?');
$json = json_decode($data_json);
$allData=$json->result;
$genesis=$allData->genesis;
		$app_state=$genesis->app_state;
			$genutil=$app_state->genutil;
				$gen_txs=$genutil->gen_txs;
$pkey=urlencode($_REQUEST['param']);
		foreach ($gen_txs as $val) {
					$body=$val->body;
						$messages=$body->messages;
						foreach ($messages as $message) {
							$validator_address=$message->validator_address;
							$delegator_address=$message->delegator_address;
							$evm_address=$message->evm_address;
							$description=$message->description;
								$moniker=$description->moniker;
								$website=$description->website;
								$details=$description->details;
								$security_contact=$description->security_contact;
								
								$pubkey=$message->pubkey;
								$key=$pubkey->key;
								if ($key==str_replace(" ","+",urldecode($pkey))){
									echo "<h1>".$moniker."</h1><br>";
									echo "<h3>".$details."</h3>";
									echo "<table>";
									echo "<tr><td><strong>Web: </strong><a href='".$website."'>".$website."</a></td></tr>";
									echo "<tr><td>Contact: ".$security_contact."</td></tr>";
									echo "<tr><td>Valoper: ".$validator_address."</td></tr>";
									echo "<tr><td>Address: ".$delegator_address."</td></tr>";
									echo "<tr><td>Pubkey: ".$pkey."</td></tr>";
									echo "<tr><td>EVM Address: ".$evm_address."</td></tr>";
									
									$data_json2 = file_get_contents($rpc.'/validators?per_page=100');
									$json2 = json_decode($data_json2);
									$allData2=$json2->result;
										$validators=$allData2->validators;
											foreach ($validators as $validator) {
														$address=$validator->address;
															$pub_key=$validator->pub_key;
																$value=$pub_key->value;
																$voting_power=$validator->voting_power;
																
							if ($value==str_replace(" ","+",urldecode($pkey))){
								echo "<tr><td>Voting power: ".$voting_power."</td></tr>";
								
							}
					}
						
							echo "</table>";
								}
						}
				}
break;  
}
?>


