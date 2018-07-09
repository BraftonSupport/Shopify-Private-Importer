<?php

require_once 'libraries.php';
include 'specs.php';

spl_autoload_register(function ($class_name) {
    include 'classes/'.$class_name .'.php';
});

//set up a new store
$store = new Store(SHOPIFY_PRIVATE_KEY,SHOPIFY_PRIVATE_PW,SHOPIFY_STORE_NAME,SHOPIFY_BLOG_ID);
$url = $store->getStoreRoot()."/articles.json";

//set connection to Shopify blog
$storeConnection = new storeConnect($store->getStoreRoot(),$url);


//return collection of brafton ids from Shopify blog
$collection = $storeConnection->getArticleMeta();

//connect to Brafton XML feed
try{
	$brafton_connect = new ApiHandler(brafton_api,domain);
} catch (Exception $e) {
	echo 'Caught exception: ',  $e->getMessage();
	echo '<br /> Please check your API key';
	die();
}

$articles = $brafton_connect->getNewsHTML();
if(!$articles) {
	echo 'Article feed is empty.<br />';
}
compareCollections($articles, $collection,$storeConnection,'articles');

if(video_import) :
	$videos = getBraftonVideos($collection, $storeConnection);
endif;
  
?>