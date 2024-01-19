<?php
//ACTIVE SET VOTING POWER

$monikers = array();
$tokens = array();

$conta=1;
$posvoting=0;


//load gentx vars<br />
echo "<table style=''><tr style='border-bottom:#CCC medium solid;line-height:2.5em'><th style='text-align:left'>Rank</th><th style='text-align:left'>Validator</th><th style='text-align:left'>Voting Power</th><th style='text-align:left'>Commission</th></tr>";

$data_json = file_get_contents('http://74.208.16.201/valinfodym.json');
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
	


if ($status=="BOND_STATUS_BONDED"){
		echo "<tr style=''>
		<td>💈".$conta++."</td><td><a href='validator.php?validator=".$operator_address."'>".$moniker."</a></td><td> ".$token." dym</td>	<td> ".$rate."%</td>	
		</tr>";		
		
}						
	}					
		
	echo "</table>";

?>