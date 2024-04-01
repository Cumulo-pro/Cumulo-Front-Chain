<!--
extract RPC cumulo Prometheus Dymension
-->
<?php
//CONECT PROMETHEUS/CHAIN
error_reporting(0);

header('Content-Type: text/html; charset=utf-8');
//choose chain
	$data = file_get_contents("http://82.223.0.229:26662");
	$ch="dymension_1100-1";


//data extract
switch($pide=$_REQUEST['dato']){
case $pide=="chain-id":
  echo $ch;
break;
case $pide=="num_tx":
  preg_match('|cometbft_consensus_total_txs{chain_id="'.$ch.'"}(.*?)# HELP|is' , $data , $cap ); 
  $num_tx=$cap[1];	
  echo rtrim(number_format($num_tx,6),0);
break;
case $pide=="num_tx_block":
  preg_match('|cometbft_consensus_num_txs{chain_id="'.$ch.'"}(.*?)# HELP|is' , $data , $cap ); 
  $num_tx=$cap[1];	
  echo rtrim(number_format($num_tx,6),0);
break;
case $pide=="block":
  preg_match('|cometbft_consensus_height{chain_id="'.$ch.'"}(.*?)# HELP|is' , $data , $cap ); 
  $bloquedato=$cap[1];
  echo rtrim(number_format($bloquedato,6),0);
break;
case $pide=="num_peers":
  preg_match('|cometbft_p2p_peers{chain_id="'.$ch.'"}(.*?)# HELP|is' , $data , $cap ); 
  $sal=$cap[1];
  echo $sal;
break;
case $pide=="num_val":
  preg_match('|cometbft_consensus_validators{chain_id="'.$ch.'"}(.*?)# HELP|is' , $data , $cap ); 
  $sal=$cap[1];
  echo $sal;
break;
case $pide=="block_size_b":
  preg_match('|cometbft_consensus_block_size_bytes{chain_id="'.$ch.'"}(.*?)# HELP|is' , $data , $cap ); 
  $sal=round($cap[1]/1024,2);
  echo $sal;
break;
case $pide=="val_power":
  preg_match('|cometbft_consensus_validators_power{chain_id="'.$ch.'"}(.*?)# HELP|is' , $data , $cap ); 
  $sal=$cap[1];
  echo rtrim(number_format($sal,6),0);
break;
case $pide=="missing_power":
  preg_match('|cometbft_consensus_missing_validators_power{chain_id="'.$ch.'"}(.*?)# HELP|is' , $data , $cap ); 
  $sal=$cap[1];
  echo rtrim(number_format($sal,6),0);
break;
case $pide=="block_time":
 
 // Buscando la suma total del tiempo de intervalo entre bloques
    preg_match('/cometbft_consensus_block_interval_seconds_sum\{chain_id="'.$ch.'"\}\s+([0-9.e+]+)/is', $data, $sum_match);
    $sum_total = isset($sum_match[1]) ? $sum_match[1] : null;
    
    // Buscando el número total de intervalos contados
    preg_match('/cometbft_consensus_block_interval_seconds_count\{chain_id="'.$ch.'"\}\s+([0-9]+)/is', $data, $count_match);
    $count_total = isset($count_match[1]) ? $count_match[1] : null;
    
    // Verificando que ambos valores hayan sido encontrados
    if ($sum_total !== null && $count_total !== null && $count_total > 0) {
        // Calculando el block time promedio
        $block_time_avg = $sum_total / $count_total;
        echo round($block_time_avg, 2);
    }
 
 
  
break;
case $pide=="online_validators":
  preg_match('|cometbft_consensus_validators{chain_id="'.$ch.'"}(.*?)# HELP|is' , $data , $cap );
  $sal=$cap[1];
  preg_match('|cometbft_consensus_missing_validators{chain_id="'.$ch.'"}(.*?)# HELP|is' , $data , $cap );
  $sal2=$cap[1];
  echo $sal-$sal2;
break;

}

?>
