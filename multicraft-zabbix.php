#!/usr/bin/php
<?php
$shortopts = "";
$longopts  = array(
  "config::",
  "item:",
  "mcserver::",
  "mcserver-dimension::",
  "dimension::"
);
$options = getopt($shortopts, $longopts);


if (array_key_exists('config', $options)){
  $config = include($options['config']);
}
else{
  $config = include('/var/lib/zabbix/multicraft-config.php');
};


require($config['apifile']);
$api = new MulticraftAPI($config['apiurl'], $config['apiuser'], $config['apipassword']);

function check_for_option($options,$opt){
  if (array_key_exists($opt, $options)){
    return $options[$opt];
  } else {
    fwrite(STDERR, "Unable to find required cli option $opt.\n");
    exit(1);
  };
};

function forgeTPS($api, $server_id) {
  $api->sendConsoleCommand($server_id, '/forge tps');
  return sleep(1);
}

function getTpsTickRate($api,$config,$options,$item_match){
  $mcsdim = explode(",", check_for_option($options, 'mcserver-dimension'));

  $mcserver = $mcsdim[0];
  $dimension = $mcsdim[1];

  $matchstring = false;

  switch ($item_match) {
    case "tps":
      $matchstring = "/(\d\d:\d\d:\d\d).*($dimension) :.* Mean TPS: ([\d\.]*)/";
      break;
    case "tick_time":
      $matchstring = "/(\d\d:\d\d:\d\d).*($dimension) :.* Mean tick time: ([\d\.]*)/";
      break;
  }

  $tps_value = false;
  do {
    $logarray = $api->getServerLog($mcserver)['data'];
    foreach (array_reverse($logarray) as $value){
      $line = $value['line'];
     

      if (strpos($line, $dimension)){
        $matches = array();
        preg_match($matchstring,$line,$matches);
        #echo "Found dimension tps: $line\n";
        if (strtotime('-10 seconds') <= strtotime($matches[1])) {
          $tps_value = $matches[3];
          return $tps_value;
        }
        break;
      }
    }
  } while ( !$tps_value && !forgeTPS($api, $mcserver));
};

function getMCServers($api,$options){
  $servers = $api->listServers()['data']['Servers'];
  $servers_zabbix = array();
  foreach ($servers as $server_id => $server_name ){
    array_push($servers_zabbix, array("{#MCSID}" => $server_id, "{#MCSNAME}" => $server_name));
  };
  return json_encode(array("data" => $servers_zabbix));
};

function getMCServerStatus($api,$options){
  $mcserver = check_for_option($options, 'mcserver');
  $return_value =  $api->getServerStatus($mcserver, false);
  if ($return_value['success']){
   return $return_value['data']['status'];
  } else {
    fwrite(STDERR, "Error getting MCServer status.\n");
    exit(1);
  };
};

function getMCServerMaxPlayers($api,$options){
  $mcserver = check_for_option($options, 'mcserver');
  $return_value =  $api->getServerStatus($mcserver, false);
  if ($return_value['success']){
   return $return_value['data']['maxPlayers'];
  } else {
    fwrite(STDERR, "Error getting MCServer Online Players.\n");
    exit(1);
  };
};

function getMCServerOnlinePlayers($api,$options){
  $mcserver = check_for_option($options, 'mcserver');
  $return_value =  $api->getServerStatus($mcserver, false);
  if ($return_value['success']){
   return $return_value['data']['onlinePlayers'];
  } else {
    fwrite(STDERR, "Error getting MCServer Online Players.\n");
    exit(1);
  };
};

function getMCServerCPUUsage($api,$options){
  $mcserver = check_for_option($options, 'mcserver');
  $return_value =  $api->getServerResources($mcserver);
  if ($return_value['success']){
   return $return_value['data']['cpu'];
  } else {
    fwrite(STDERR, "Error getting MCServer CPU Usage.\n");
    exit(1);
  };
};

function getDimensions($api,$options){
  $servers = $api->listServers()['data']['Servers'];
  $matchstring = "/Server thread\/INFO (.*) : Mean tick time:/";
  $dims_data = array();
  foreach ($servers as $server_id => $server_name ){
    $dims = array();
    forgeTPS($api, $server_id);
    $logarray = $api->getServerLog($server_id)['data'];
    foreach (array_reverse($logarray) as $value){
      $line = $value['line'];
      if (strpos($line, 'Mean tick time')){
        $matches = array();
        preg_match($matchstring,$line,$matches);
        $dim = $matches[1];
        if (!in_array($dim, $dims)){
          array_push($dims, $dim);
          array_push($dims_data, array("{#MCSID_DIM}" => "$server_id,$dim", "{#MCSNAME_DIM}" => "$server_name, $dim"));
        };
      }
    };
  };
  return json_encode(array("data" => $dims_data));
};

if (array_key_exists('item', $options)){
  $item = $options['item'];
} else {
  fwrite(STDERR, "item unspecified\n");
  exit(1);
};



switch ($item) {
  case "getDimensions":
    echo getDimensions($api,$options);
    break;
  case "getTPS":
    echo getTpsTickRate($api,$config,$options,"tps");
    break;
  case "getTick":
    echo getTpsTickRate($api,$config,$options,"tick_time");
    break;
  case "getMCServers":
    echo getMCServers($api,$options);
    break;
  case "getMCServerStatus":
    echo getMCServerStatus($api,$options);
    break;
  case "getMCServerMaxPlayers":
    echo getMCServerMaxPlayers($api,$options);
    break;
  case "getMCServerOnlinePlayers":
    echo getMCServerOnlinePlayers($api,$options);
    break;
  case "getMCServerCPUUsage":
    echo getMCServerCPUUsage($api,$options);
    break;
  default:
    echo "unknown option";
}
?>
