<?php

include '../source/libraries.php';

$crude = file_get_contents("./specs/shop-data.JSON");
$refined = json_decode($crude);

DefinedConstants::getConstants($refined);

//override master importer methods here
class ShopifyImporter extends MasterImporter{
    // /**
    //  * overridable function in ShopifyImporter classs
    //  *
    //  * @param string $html
    //  *  @return string
    //  */
    // public function filterContent($html){
    //     return $html;
    // }
    // public function embeedCode($video, $something):VideoCode;
}
//everything above here should be included in Shopify importer constructor
$shop_importer = new ShopifyImporter(); //begin import process here
$article_collection = $shop_importer->compareCollections(); // return array of newsListItems
if(count($article_collection)>0){
    $shop_importer->importArticles($article_collection);
}
if(VIDEO_IMPORT) :
	$videos = $shop_importer->getBraftonVideos();
endif;