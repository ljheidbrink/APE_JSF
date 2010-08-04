<?php

error_reporting (E_ALL);

set_include_path(get_include_path() . PATH_SEPARATOR . '/usr/local/www/ape-jsf/Demos/lib');
include("php_serial/php_serial_wendi.linux.class.php");
include("wendi/wendi_mote.class.php");
//include("../lib/php_serial/php_serial_wendi.linux.class.php");
//include("../lib/wendi/wendi_mote.class.php");

define("DEBUG", 3);
// 1 - dump command sent to ape
// 2 - dump response received from ape

$APEserver = 'http://ape-test.local:6969/?';
$APEPassword = 'testpasswd';

$WendiMote = new wendiMote();

$nrounds = $_GET['n'];
if (empty($nrounds))
	$nrounds = 20;

if ($WendiMote->startup()) {

	for ($i = 1; $i <= $nrounds; $i++) {

		$tsnix = microtime(true);
		$utimearray = explode(".", $tsnix);
		$ts = date('H:i:s', $utimearray[0]) . '.' . $utimearray[1];

		$messages = array(
			'ts'		=> $ts,
			'tsnix' => $tsnix,
			'ch1' 	=> $WendiMote->getADCvalue(1),
			'ch2'		=> $WendiMote->getADCvalue(2),
			'ch3' 	=> $WendiMote->getADCvalue(3)
		);

		$cmd = array(array( 
			'cmd' => 'inlinepush', 
			'params' =>  array( 
				'password'  => $APEPassword, 
				'raw'       => 'postmsg', 
				'channel'   => 'testchannel', 
				'data'      => $messages
			 ) 
		)); 

		if (DEBUG & 1) {
			var_dump($APEserver.rawurlencode(json_encode($cmd)));
			echo '<br/>';
		}

		$data_json = file_get_contents($APEserver.rawurlencode(json_encode($cmd))); 
		$data = json_decode($data_json,true);
		if (strtolower($data[0]["data"]["value"]) != 'ok') {
			echo "Error sending message.<br/>";
			var_dump($data_json);
			break;
		}

		if (DEBUG & 2) {
			var_dump($data_json);
			echo '<br/>';
		}

	}

}

$WendiMote->shutdown();

?>
