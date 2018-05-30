<?php

require_once 'libraries.php';

spl_autoload_register(function ($class_name) {
    include 'classes/'.$class_name .'.php';
});

//set up a new store
$store = new Store('991bf02de855f038722f11eb71dac2ff','a7ceb55d6090e1edad82e4d48b6c303e','brafton-importer','12680069178');
$video_client = true;
$url = $store->getStoreRoot()."/articles.json";

//set connection to Shopify blog
$storeConnection = new storeConnect($store->getStoreRoot(),$url);

//connect to Brafton XML feed
$brafton_connect = new BraftonApi('92599a2e-a5d6-41ff-93ac-11484207dc9d','brafton', $private = '29f07cf9-50c8-42b5-abf0-123eb614d4fd', $public = 'U4S7U8A9');

//return collection of brafton ids from XML feed
$temp = $brafton_connect->getBraftonFeedIds();
$len = sizeof($temp); 

//return collection of brafton ids from Shopify blog
$collection = $storeConnection->getArticleMeta();

//compare collections
for($i=0;$i<$len;$i++){
	if(in_array($temp[$i],$collection)){
		echo '<span style="font-size:22px;display: block;text-align: center;">Article  '.$temp[$i].' already exists in blog </span><br />';
	}else {
		$xml = $brafton_connect->getBraftonArticle($temp[$i]);
		$storeConnection->postArticle($xml);
		echo '<span style="font-size:22px;display: block;text-align: center;">Posted '.$temp[$i].' to blog </span><br />';	
	}
}
$tags = $storeConnection->getStoreTags();
echo '<pre>';
var_dump($tags);
if($video_client) :

$videos = $brafton_connect->getBraftonVideos($collection, $tags);
endif;
  
?>