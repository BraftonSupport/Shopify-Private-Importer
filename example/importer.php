<?php

include '../source/libraries.php';

$crude = file_get_contents("shop-data.JSON");
$refined = json_decode($crude);

DefinedConstants::getConstants($refined);

//override master importer methods here
class ShopifyImporter extends MasterImporter{

}

//set up a new store
$store = new Store(SHOPIFY_PRIVATE_KEY,SHOPIFY_PRIVATE_PW,SHOPIFY_STORE_NAME,SHOPIFY_BLOG_ID);

//set store articles endpoint from Shopify API
$url = $store->getStoreRoot()."/articles.json";

//set connection to Shopify blog
$storeConnection = new StoreConnect($store->getStoreRoot(),$url);

//get collection of existing brafton ids 
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

$shop_importer = new ShopifyImporter(); //begin import process here
$post_updates = $shop_importer->compareCollections($articles, $storeConnection); // return array of newsListItems
if(count($post_updates)>0){
    $shop_importer->importArticles($post_updates,$storeConnection);
}
if(video_import) :
	$videos = $shop_importer->getBraftonVideos($collection, $storeConnection);
endif;