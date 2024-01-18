<?php

echo "<table><tr><th>moniker</th><th>URL</th><th>height</th><th>synced</th><th>Validator</th><th>txIndex</th></tr>";


//error_reporting(0);
$data_json =file_get_contents("http://74.208.16.201/nodos_rpc.json"); //default value
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
if ($catchingUp=="")
{
	$catchingUp="no data";
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
?>