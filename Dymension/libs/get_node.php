<?php

//RPC SCAN
switch($pide=$_REQUEST['dato']){
case $pide=="rpc_scan":

echo "<table><tr><th>moniker</th><th>URL</th><th>height</th><th>synced</th><th>Validator</th><th>txIndex</th></tr>";

//error_reporting(0);
$data_json =file_get_contents("http://82.223.0.229/nodos_rpc.json"); //default value
$json = json_decode($data_json);

foreach ($json as $data) {
$moniker=$data->moniker;
$node=$data->node;
$blockHeight=$data->blockHeight;
if ($blockHeight=="")
{
	$blockHeight="no data";
}
$catchingUp=$data->catchingUp;
if ($catchingUp=="false")
{
	$catchingUp="YES";
}else{
	$catchingUp="NO";
}
$isValidator=$data->isValidator;
if ($isValidator=="YES")
{
	$isValidator="❗YES";
}else if($isValidator=="NO"){
	$isValidator="✔ NO";
}
$txIndex=$data->txIndex;

echo "<tr><td>".$moniker."</td><td><a href='".$node."'>".$node."</a></td><td>".$blockHeight."</td><td>".$catchingUp."</td><td>".$isValidator."</td><td>".$txIndex."</td></tr>";

}
echo "</table>";
break;

case $pide=="no_val_set":
//ACTIVE SET VOTING POWER
$monikers = array();
$tokens = array();

$conta=1;
$posvoting=0;

//load gentx vars<br />
echo "<table style=''><tr style='border-bottom:#CCC medium solid;line-height:2.5em'><th style='text-align:left'>Rank</th><th style='text-align:left'>Validator</th><th style='text-align:left'>Voting Power</th><th style='text-align:left'>Commission</th></tr>";

$data_json = file_get_contents('http://82.223.0.229/novalinfodym.json');
$json = json_decode($data_json);
foreach ($json as $data) {
	$operator_address=$data->operator_address;
	$description=$data->description;
	$moniker=$description->moniker;
	$identity=$description->identity;
	$utoken=$data->tokens;
	$token=round($utoken/1000000000000000000);
	$token=number_format((int)$token, 0, ',', '.');
	$status=$data->status;
	
	$commission=$data->commission;
	$commission_rates=$commission->commission_rates;
	$rate=$commission_rates->rate*100;
	


		echo "<tr style=''>
		<td>💈".$conta++."</td><td><a href='validator.php?validator=".$operator_address."'>".$moniker."</a></td><td> ".$token." dym</td>	<td> ".$rate."%</td>	
		</tr>";		
		
}						
		
	echo "</table>";
break;

case $pide=="val_set":
//ACTIVE SET VOTING POWER
$monikers = array();
$tokens = array();

$conta=1;
$posvoting=0;

//load gentx vars<br />
echo "<table style=''><tr style='border-bottom:#CCC medium solid;line-height:2.5em'><th style='text-align:left'>Rank</th><th style='text-align:left'>Validator</th><th style='text-align:left'>Voting Power</th><th style='text-align:left'>Commission</th></tr>";

$data_json = file_get_contents('http://82.223.0.229/valinfodym.json');
$json = json_decode($data_json);

if ($json !== null && isset($json->validators) && is_array($json->validators)) {
    foreach ($json->validators as $data) {
        $operator_address = $data->operator_address;
        $description = $data->description;
        $moniker = $description->moniker;
        $identity = $description->identity;
        $tokens = $data->tokens;
        $token = round($tokens / 1000000000000000000);
        $token = number_format($token, 0, ',', '.');
        $status = $data->status;

        $commission = $data->commission;
        $commission_rates = $commission->commission_rates;
        $rate = $commission_rates->rate * 100;

        // Ahora puedes usar $operator_address, $moniker, $identity, $tokens, $status y $rate aqu� seg�n lo necesites
  
if ($status=="BOND_STATUS_BONDED"){
		echo "<tr style=''>
		<td>💈".$conta++."</td><td><a href='validator.php?validator=".$operator_address."'>".$moniker."</a></td><td> ".$token." dym</td>	<td> ".$rate."%</td>	
		</tr>";		
		
}						
	}					
  
} else {
    echo "Error: No se pudieron obtener los datos de los validadores.";
}		
	echo "</table>";
break;


case $pide=="miss_block":
// MISS BLOCK VALIDATOR

$monikers = array();
$tokens = array();

$conta=0;

$posvoting=0;
$posiciona=0;


//load gentx vars<br />
$data_json = file_get_contents('http://74.208.94.42/valmissceles.json');
$json = json_decode($data_json);
$conta=0;
foreach ($json as $data) {
if (isset($data->operator_address)) {
$operator_address=$data->operator_address;
}else{
	$operator_address="";
}
$address=$data->address;

$jailed_until=$data->jailed_until;
$timestamp = strtotime($jailed_until);
	$jailed_data = date('d/m/Y', $timestamp);
if ($jailed_data=="01/01/1970"){
$jailed_data="never";
}
$missed_blocks_counter=$data->missed_blocks_counter;
$start_height=$data->start_height;
$index_offset=$data->index_offset;
$tombstoned=$data->tombstoned;
if($tombstoned=="true"){
	$tombstoned="Invalid validator";
}else{
	$tombstoned="NO";
}
if (isset($data->moniker)) {
$moniker=$data->moniker;
}else{
	$moniker=$address;
}
		
		
	if($moniker!="no existe"){

		echo "<div class='track'><h3 class='validator' title='".$operator_address."'><img src='img/rollico.png' width:'10%'>".$moniker,"</h3> 
		
		<div class='val'><span>Miss blocks:  ".$missed_blocks_counter."&nbsp;&nbsp;&nbsp;&nbsp; Jailed:  ".$jailed_data."  | tombstoned: ".$tombstoned."</div>
		<div class='val'>start_height: ".$start_height." index_offset: ".$index_offset."</div>
		</div>";
		
		echo "<br>";
		$conta++;
	}					
}					
echo "Total val: ".$conta;		
break;

case $pide=="val_data":
$validator=$_GET['validator'];

//VALIDATOR DATA
$monikers = array();
$tokens = array();

$conta=1;
$posvoting=0;


//load  vars

$data_json = file_get_contents('http://82.223.0.229/valinfodym.json');
$json = json_decode($data_json);

foreach ($json as $data) {
	$operator_address=$data->operator_address;
	$description=$data->description;
	$moniker=$description->moniker;
	$identity=$description->identity;
	$website=$description->website;
	$security_contact=$description->security_contact;
	if ($security_contact==""){
		$security_contact="?";
	}
	$details=$description->details;
	$utoken=$data->tokens;
	$token=round($utoken/1000000000000000000);
	$token=number_format((int)$token, 0, ',', '.');
	$status=$data->status;
	
	$commission=$data->commission;
	$commission_rates=$commission->commission_rates;
	
	$update_time=$commission->update_time;
	$timestamp = strtotime($update_time);
	$update = date('d/m/Y', $timestamp);


	
	$rate=$commission_rates->rate*100;
	$max_rate=$commission_rates->max_rate*100;
	$max_change_rate=$commission_rates->max_change_rate*100;
	
	$jailed=$data->jailed;
	if ($jailed==false){
		$jailed="no";
	}else{
		$jailed="yes";}
	$operator_address=$data->operator_address;
	$consensus_pubkey=$data->consensus_pubkey;
	$key=$consensus_pubkey->key;
	
	
	$rank=$conta++;

if ($operator_address==$validator){
	
		echo "<h2>".$moniker."</h2>";
		echo "<h3><a href='".$website."'>".$website."</a></h3>";
		echo "<p class='descrip'>".$details."</p>";
	
	echo "<br><br><br><table style=''><tr style='border-bottom:#CCC medium solid;line-height:2.5em'><th>Rank</th><th>Voting Power</th><th>Commission</th><th>Max Rate</th><th>Max Change Rate</th><th>Updated</th></tr>";

		echo "<tr>
		<td>??".$rank."</td><td> ".$token." dym</td>	<td> ".$rate."%</td><td> ".$max_rate."%</td><td> ".$max_change_rate."%</td><td> ".$update."</td>
		</tr>";				
							
	
echo "</table>";

		echo "<br><table style=''><tr style=''><th>Jailed</th><th>Security contact</th></tr>";

echo "<tr>
		<td>".$jailed."</td><td>".$security_contact."</td>
		</tr>";				

echo "<br><tr><th>Valoper</th></tr>";

echo "<tr>
		<td>".$operator_address."</td>
		</tr>";		

echo "<tr style='border-bottom:#CCC medium solid;line-height:2.5em'><th></th><th>pubKey</th></tr>";


echo "<tr>
		<td></td><td>".$key."</td>
		</tr>";		



}						
	}
echo "</table>";



break;
}
?>