<?php

require_once 'RestClientMulti.php';
require_once 'RestClient.php';

$threads = [ 'aww', 'history', 'philosophy', 'diy', 'tifu', 'earchporn', 'fitness', 'news', 'creepy', 'books' ];
$multi = new RestClientMulti();

foreach ($threads as $thread) {
    $rc = new RestClient();
    $rc->autoExecute(false)->get("http://www.reddit.com/r/{$thread}/.json");
    $multi->addClient($rc);
}

$start = time();
$result = $multi->execute(function($results) {
    $retVal = array();
    foreach($results as $result) {
        $retVal[] = json_decode($result);
    }

    return $retVal;
});

$total = time() - $start;
print "Total Time: {$total}s or " . round($total/count($threads), 4) . " / thread\n";

var_dump(count($result));