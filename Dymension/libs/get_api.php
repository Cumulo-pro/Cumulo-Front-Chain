
<?php
error_reporting(0);

// Endpoint de la API de CoinGecko para obtener información sobre Celestia
$url = "https://api.coingecko.com/api/v3/coins/dymension";

// Realizar la solicitud y decodificar la respuesta
$response = file_get_contents($url);
$data = json_decode($response, true);

// token from coingeko
switch($pide=$_REQUEST['dato']){
case $pide=="get_token":
echo "<strong>Token chain:</strong> " . $data['symbol'] . "<br>";
echo "<strong>Base token:</strong> <span>u" . $data['symbol'] . "</span><br>";
break;

// market from coingeko
case $pide=="get_market":
echo "<strong>Market cap ranking:</strong>  <span>" . $data['market_cap_rank'] . "</span><br>";
echo "<strong>Market Cap (USD):</strong> <span>" . number_format($data['market_data']['market_cap']['usd'], 0, ',', '.') . "</span><br>";
echo "<strong>Volume 24h (USD):</strong> <span>" . number_format($data['market_data']['total_volume']['usd'], 0, ',', '.') . "</span><br>";
echo "<strong>Ath_date: </strong> <span>" . $data['market_data']['ath']['usd'] . " usdc</span><br>";
echo "<strong>Atl_date: </strong><span>" . $data['market_data']['atl']['usd'] . " usdc</span><br>";
break;


// val github
case $pide=="get_github":

$usuarioGitHub = 'dymensionxyz'; // Sustituye esto por el nombre de usuario de GitHub para Celestia
$repositorioGitHub = 'dymension'; // Sustituye esto por el nombre del repositorio de Celestia

$urlRepo = "https://api.github.com/repos/$usuarioGitHub/$repositorioGitHub";

// Configuración inicial de cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $urlRepo);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'NombreDeTuAplicacion'); // GitHub requiere un User-Agent

$response = curl_exec($ch);

$repoData = json_decode($response, true);

echo "<strong>Stars:</strong> " . $repoData['stargazers_count'] . "<br>";

$urlContributors = "https://api.github.com/repos/$usuarioGitHub/$repositorioGitHub/contributors";

// Configura y realiza la solicitud como antes
curl_setopt($ch, CURLOPT_URL, $urlContributors);
$response = curl_exec($ch);
$contributors = json_decode($response, true);
curl_close($ch);

echo "<strong>Contributors:</strong> " . count($contributors) . "<br>";break;

}

?>