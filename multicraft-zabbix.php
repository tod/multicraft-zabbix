#!/usr/bin/php
<?php
$shortopts = "";
$longopts  = array(
  "config::",
  "item:",
  "mcserver::",
  "dimension::"
);
$options = getopt($shortopts, $longopts);


if (array_key_exists('config', $options)){
  $config = include($options['config']);
}
else{
  $config = include(getenv("HOME") . '/multicraft-config.php');
};


require($config['apifile']);
$api = new MulticraftAPI($config['apiurl'], $config['apiuser'], $config['apipassword']);

function forgeTPS($api, $server_id) {
  $api->sendConsoleCommand($server_id, '/forge tps');
  return sleep(1);
}

function getTpsTickRate($api,$options,$item_match){

  $matchstring = false;

  switch ($item_match) {
    case "tps":
      $matchstring = "Mean TPS:";
      break;
    case "tick_time":
      $matchstring = "Mean tick time:";
      break;
  }

  if (array_key_exists('mcserver', $options)){
    $mcserver = $options['mcserver'];
  } else {
    fwrite(STDERR, "Multicraft server (mcserver) not specified.\n");
    exit(1);
  };
  if (array_key_exists('dimension', $options)){
    $dimension = $options['dimension'];
  } else {
    fwrite(STDERR, "Dimension not specified.\n");
    exit(1);
  };

  $tps_value = false;
  do {
    $logarray = $api->getServerLog($mcserver)['data'];
    foreach (array_reverse($logarray) as $value){
      $line = $value['line'];
     

      if (strpos($line, $dimension)){
        $matches = array();
        preg_match("/(\d\d:\d\d:\d\d).*($dimension).*$matchstring ([\d\.]*)/",$line,$matches);
        #echo "Found dimension tps: $line\n";
        if (strtotime('-1 minute') <= strtotime($matches[1])) {
          $tps_value = $matches[3];
          echo $tps_value;
          flush();
          exit(0);
        }
        flush();
        break;
      }
    }
  } while ( !$tps_value && !forgeTPS($api, $options['mcserver']));
};

function getMCServers($api,$options){
  $servers = $api->listServers()['data']['Servers'];
  $servers_zabbix = array();
  foreach ($servers as $server_id => $server_name ){
    array_push($servers_zabbix, array("{#MCSID}" => $server_id, "{#MCSNAME}" => $server_name));
  };
  echo json_encode(array("data" => $servers_zabbix));
};

if (array_key_exists('item', $options)){
  $item = $options['item'];
} else {
  fwrite(STDERR, "item unspecified\n");
  exit(1);
};



switch ($item) {
  case "listServers":
    print_r( $api->listServers() );
    break;
  case "getServerStatus":
    print_r($api->getServerStatus(2, false));
    break;
  case "sendConsoleCommand":
    print_r($api->sendConsoleCommand(2, '/cofh tps'));
    break;
  case "getServerLog":
    print_r($api->getServerLog('item', $options['mcserver']));
    break;
  case "getTPS":
    getTpsTickRate($api,$options,"tps");
    break;
  case "getTick":
    getTpsTickRate($api,$options,"tick_time");
    break;
  case "getMCServers":
    getMCServers($api,$options);
    break;
  default:
    echo "unknown option";
}
?>
