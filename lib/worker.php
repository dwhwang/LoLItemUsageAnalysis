<?php
/**
 * This is a background task and should not run in browser.
 **/
require('./config.php');
require('./SQLiteQueue.php');

$db = new SQLiteQueue('../queue.db');
$apilimit = max(10/RATE_PER_10SEC, 600/RATE_PER_10MIN)*1000000; //usec
if(!is_dir('../json')) mkdir('../json');

$time_start = microtime(true);
while(true){
  //check if queue has item
  $item = $db->peek();
  if($item){
    if (cacheMatch($item['region'], $item['match'])) {
      $db->pop(); // remove the processed item
    }
    usleep($apilimit);
  }else{
    // nothing here, sleep for some time
    //echo "waiting for queue....\n";
    //sleep(10);
    break;
  }
}
$time_end = microtime(true);
$time = $time_end - $time_start;
printf("Done. Time used: %.3f seconds.\n", $time);

// parse http header response into array
function response_parser($raw){
  $h = array();
  $lastkey = '';
  $c = 0;

  foreach( explode("\n", $raw) as $v){
    $v = trim($v);
    if(empty($v)) continue;

    $v = explode(':', $v, 2);
    if(isset($v[1])){ // property : value
      $key = trim($v[0]);
      if(isset($h[$key])){ // duplicate property name
        if(is_array($h[$key])){
          $h[$key][] = trim($v[1]);
        }else{
          $h[$key] = [$h[$key], trim($v[1])];
        }
      }else{ // first encounter property name
        $h[$key] = trim($v[1]);
      }
      $lastkey = $key;
    }else{ // single line
      if($v[0][0]=="\t"){ // folded, should be continue at the last key
        $h[$lastkey] = $h[$lastkey]."\r\n\t".$v[0];
      }else{ // single line headers
        $h[] = trim($v[0]);
        $lastkey = $c;
        $c++;
      }
    }
  }

  return $h;
}

// cache match data if not cached
// return true if match is processed (cached or don't exsist)
// return false if rate limit exceed
function cacheMatch($region, $matchid){
  $file = '../json/'.$region.'_'.$matchid.'.json';
  $url = 'https://'.$region.'.api.pvp.net/api/lol/'.$region.'/'.MATCH_VERSION.'/match/'.$matchid.'?includeTimeline=true&api_key='.API_KEY;

  //TODO: check the processed match id
  if(file_exists($file)){
      echo "Match: ".$region." ".$matchid." Already cached.\n";
      return true;
  }else{
    // curl call api
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,false);
    curl_setopt($ch, CURLOPT_HEADER, true);
    $result = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = response_parser(substr($result, 0, $header_size));
    $body = substr($result, $header_size);
    curl_close($ch);

    if ($code == 200) { //success
      //cached json
      $r = file_put_contents($file, $body);
      if ($r === false) {
        echo "Match: ".$region." ".$matchid." File write in failure.\n";
        exit(1);
      } else {
        echo "Match: ".$region." ".$matchid." Cached\n";
        return true;
      }

    } elseif ($code == 400) { //bad request

      $msg = "Error: 400 Bad Request. URL parameter is missing.\n";
      $msg = $msg."Request: ".$url."\n";
      msglog($msg);
      exit(1);

    } elseif ($code == 401) { //unauthorized

      $msg = "Error: 401 Unauthorized. Check your api key.\n";
      msglog($msg);
      exit(1);

    } elseif ($code == 403) { //black listed

      $msg = "Error: 403 blacked listed. \n";
      msglog($msg);
      exit(1);

    } elseif ($code == 404) { //not found
      // skip this request
      $msg = 'Match not found: '.$region.' match '.$matchid."\n";
      msglog($msg);
      return true;

    } elseif ($code == 429) { //rate limit exceeded
      // wait for time then try again
      //TODO: service rate limit and user rate limit handling
      $delay = 1; // 1 seconds back off if not api rate exceeded
      if(isset($header['Retry-After'])) $delay = $header['Retry-After'];

      $msg = "Error: 429 Rate Limit Exceeded. Retry after ".$delay." sec.\n";
      //$msg = $msg.print_r($header, true)."\n";
      msglog($msg);
      sleep($delay);
      return false;

    } elseif ($code >= 500) { //server error or service unavailable
      // wait for some time then try again
      $msg = "Error: ".$code." ".$region." Service Unavailable. Retry after 1 min...\n";
      msglog($msg);
      sleep(60); // wait 1 min for server to come back up
      return false;

    } else { // other response
      //TODO: other response handling
      $msg = "Response Code: ".$code." Match: ".$region." ".$matchid."\n";
      msglog($msg);
      return true;

    }
  }
}

function msglog($message){
  $log = './log.txt';
  echo $message;
  file_put_contents($log, $message, FILE_APPEND);
}
?>
