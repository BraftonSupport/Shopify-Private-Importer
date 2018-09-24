<?php

include '../source/libraries.php';

echo 'starting API query.....<br />';
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
    public function setArticleData($a){
        $image = $a->getPhotos();
        $large = $image[0]->getLarge();
        $cats = $a->getCategories();
        $ready_data = array('headline'=> $a->getHeadline(), 
            'brafton_id'=> $a->getId(), 
            'created'=> $a->getCreatedDate(), 
            'publish'=> $a->getPublishDate(),
            'byline'=> $a->getByLine() ?: 'brafton',
            'text'=>$this->filterContent($a->getText()),
            'excerpt'=> $a->getExtract() ?: $a->getHtmlMetaDescription(),
            'image_url'=> $large->getUrl(),
            'image_width'=> $large->getWidth(),
            'image_height'=> $large->getHeight(),
            'caption'=> $image[0]->getAlt(),
            'categories'=> $this->setCatString($cats),
            'archive' => false
        );
        return $ready_data;
    }
}
//everything above here should be included in Shopify importer constructor
$shop_importer = new ShopifyImporter(); //begin import process here
$article_collection = $shop_importer->compareCollections(); // return array of newsListItems
if(count($article_collection)>0){
    $shop_importer->importArticles($article_collection);
}
// if(VIDEO_IMPORT) :
// 	$videos = $shop_importer->getBraftonVideos();
// endif;