<?php

spl_autoload_register(function ($class_name) {
    include 'classes/'.$class_name .'.php';
});

//set up a new store
$store = new Store('-----------------','---------------------','brafton-importer','12680069178');

$url = $store->getStoreRoot()."/articles.json";

//set connection to Shopify blog
$storeConnection = new storeConnect($store->getStoreRoot(),$url);

//connect to Brafton XML feed
$brafton_connect = new BraftonApi('6e8debbd-a687-4386-a115-6fac8f2456a6','brafton');

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
  
?>