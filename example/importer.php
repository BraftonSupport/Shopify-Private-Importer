<?php

include '../source/libraries.php';

$crude = file_get_contents("shop-data.JSON");
$refined = json_decode($crude);

DefinedConstants::getConstants($refined);

//override master importer methods here
class ShopifyImporter extends MasterImporter{

}
//set up a new store
$store = new store(SHOPIFY_PRIVATE_KEY,SHOPIFY_PRIVATE_PW,SHOPIFY_STORE_NAME,SHOPIFY_BLOG_ID);
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
	die('<br /> Please check your API key');
}

$articles = $brafton_connect->getNewsHTML();
if(!$articles) {
    exit('Article feed is empty.<br />');
}

$importer = new ShopifyImporter(); //begin import process here
$post_updates = $importer->compareCollections($articles, $storeConnection); // return array of newsListItems
if(count($post_updates)>0){
    $importer->importArticles($post_updates,$storeConnection);
}
if(video_import) :
	$videos = $importer->getBraftonVideos($collection, $storeConnection);
endif;