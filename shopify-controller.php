<?php

require_once 'libraries.php';

spl_autoload_register(function ($class_name) {
    include 'classes/'.$class_name .'.php';
});

//set up a new store
$store = new Store('private_key','pw','brafton-importer','12680069178');
$video_client = true;
$url = $store->getStoreRoot()."/articles.json";

//set connection to Shopify blog
$storeConnection = new storeConnect($store->getStoreRoot(),$url);
$private = '29f07cf9-50c8-42b5-abf0-123eb614d4fd';
$public = 'U4S7U8A9'; 

//return collection of brafton ids from Shopify blog
$collection = $storeConnection->getArticleMeta();

//connect to Brafton XML feed
$brafton_connect = new ApiHandler('92599a2e-a5d6-41ff-93ac-11484207dc9d','https://api.brafton.com');
$articles = $brafton_connect->getNewsHTML();

//compare collections
foreach ($articles as $item) {

	$brafton_id = $item->getId();
	if(in_array($brafton_id,$collection)){
		echo '<span style="font-size:22px;display: block;text-align: center;">Article  '.$brafton_id.' already exists in blog </span><br />';
	}else{
		$article_data = setArticleData($item);
		//set new array here using values derived from API Handler and pass to postArticle function
		$storeConnection->postArticle($article_data);
		echo '<span style="font-size:22px;display: block;text-align: center;">Posted '.$brafton_id.' to blog </span><br />';
	}
}

function setArticleData($a){

	$image = $a->getPhotos();
	$large = $image[0]->getLarge();
	$cats = $a->getCategories();
	$ready_data = array('headline'=> $a->getHeadline(), 
		'id'=> $a->getId(), 
		'created'=> $a->getCreatedDate(), 
		'publish'=> $a->getPublishDate(),
		'byline'=> $a->getByLine() ?: 'brafton',
		'text'=> $a->getText() ?: $a->getHtmlMetaDescription(),
		'excerpt'=> $a->getExtract(),
		'image_url'=> $large->getUrl(),
		'image_width'=> $large->getWidth(),
		'image_height'=> $large->getHeight(),
		'caption'=> $image[0]->getAlt(),
		'categories'=> $cats[0]->getName() //fix later to accommodate multiple categories
	);
	return $ready_data;

}

if($video_client) :

//$videos = $brafton_connect->getBraftonVideos($collection, $tags);
endif;
  
?>