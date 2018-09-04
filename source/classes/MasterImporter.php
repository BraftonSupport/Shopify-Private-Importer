<?php

abstract class MasterImporter{
    /**
     * @var array
     */
    private $linker = array();
    /**
     * Associative array of shopify id's with brafton idds as keys
     *
     * @var array
     */
    private $articles_to_post = array();
    /**
     * jjjj
     *
     * @var array
     */
    private $brafton_articles;
    /**
     * 
     *
     * @var array
     */
    private $id_collection;
    /**
     * 
     *
     * @var object
     */
    private $storeConnection;
    public function __construct(){
        $this->setup();
    }

    //set-up method
    private function setup(){
        //set up a new store
        $store = new Store(SHOPIFY_PRIVATE_KEY,SHOPIFY_PRIVATE_PW,SHOPIFY_STORE_NAME,SHOPIFY_BLOG_ID);

        //set store articles endpoint from Shopify API
        $articles_endpoint = $store->getStoreRoot()."/articles.json";

        //set connection to Shopify blog
        $this->storeConnection = new StoreConnect($store->getStoreRoot(),$articles_endpoint);

        //get collection of existing brafton ids 
        $this->id_collection = $this->storeConnection->getArticleMeta();

        //connect to Brafton XML feed
        try{
            $brafton_connect = new ApiHandler(BRAFTON_API,DOMAIN);
        } catch (Exception $e) {
            echo 'Caught exception: ',  $e->getMessage();
            die('<br /> Please check your API key');
        }

        //get collection of articles from Brafton XML feed.
        $this->brafton_articles = $brafton_connect->getNewsHTML();
        if(!$this->brafton_articles) {
            exit('Article feed is empty.<br />');
        }
    }
    //compare collections of articles between Brafton XML feed and Shopify Blog API, returns an array of newsListItems to be posted to shopify blog
    public function compareCollections() {
        foreach ($this->brafton_articles as $article) {
            $brafton_id = $article->getId();
            $this->linker = $this->storeConnection->getLinkArray();
            $existing_article = array_key_exists($brafton_id,$this->linker);
            if($existing_article==true) {
                $need_update = $this->checkForUpdate($this->storeConnection, $this->linker, $article, $brafton_id);
            }
            if(!$existing_article || $need_update){   //adding articles to array that either do not exist in the 
                array_push($this->articles_to_post,$article);                  //Shopify store blog or exist, but require updating
            } else {
                echo '<span style="font-size:22px;display: block;text-align: center;">Article  '.$brafton_id.' already exists in blog </span><br />';
            }
        }
        return $this->articles_to_post; //returning list of new and or updated articles
    }

    //check to see whether existing article needs updating
    public function checkForUpdate($obj, $link, $current, $brafton_id=2 ){
        $single = $obj->getShopifyArticle($link[$brafton_id]);
        $shop_date = $single->article->updated_at;
        if($current->getLastModifiedDate() > $shop_date && isset($_GET['override'])) { //add get parameter test
            return true;
        } else {
            return false;
        }
    }

    //begin posting process
    public function importArticles($article_collection){
        foreach($article_collection as $article){
            $article_data = $this->setArticleData($article);
            echo '<br /> Adding post '.$article->getHeadline().' to the Blog.';
            $this->storeConnection->postArticle($article_data,'article');
        }
    }

    /**
     * overridable function in ShopifyImporter classs
     *
     * @param string $html
     *  @return string
     */
    public function filterContent($html){
        return $html;
    }

    //get article data from Brafton XML feed
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
            'archive' => true
        );
        return $ready_data;
    }

    //get video blog data from Brafton video XML feed
    public function setVideoData($title,$excerpt,$date,$strContent,$image,$braf_id,$kitty=null){
        $ready_video_data = array('headline'=> $title, 
            'brafton_id'=> $braf_id, 
            'created'=> $date, 
            'publish'=> $date,
            'byline'=> 'brafton',
            'text'=> $strContent,
            'excerpt'=> $excerpt,
            'image_url'=> $image,
            'image_width'=> '',
            'image_height'=> '',
            'caption'=> '',
            'categories'=> $kitty
        );
        return $ready_video_data;
    } 
    
    //set category string here
    public function setCatString($cats){
        $string_cats = array();
        foreach($cats as $cat) {
            array_push($string_cats, $cat->getName());
        }
        return implode(', ', $string_cats); //send over multiple categories as comma-separated string
    }


    public function convertProtocol($address){
        $pos = strpos($address, 'http');
        if ($pos === false) {
            return $address;
        } else{
            return str_replace('http','https',$address);
        }	
    }

    //used in building video tag source element delivered to blog
    public function generate_source_tag($src, $resolution){
        $tag = ''; 
        $ext = pathinfo($src, PATHINFO_EXTENSION); 
        return sprintf('<source src="%s" type="video/%s" data-resolution="%s" />', $src, $ext, $resolution );
    }

    //get Brafton video blogs from video XML feed
    public function getBraftonVideos(){
        $domain = preg_replace('/https:\/\//','',DOMAIN);
        $params = array('max'=>99);
        $baseURL = 'http://livevideo.'.str_replace('http://', '',$domain).'/v2/';
        $videoClient = new AdferoVideoClient($baseURL, BRAFTON_PUBLIC_KEY, BRAFTON_PRIVATE_KEY);
        $client = new AdferoClient($baseURL, BRAFTON_PUBLIC_KEY, BRAFTON_PRIVATE_KEY);
        $videoOutClient = $videoClient->videoOutputs();
        $photos = $client->ArticlePhotos();
        $photoURI = 'http://'.str_replace('api', 'pictures',$domain).'/v2/';
        $photoClient = new AdferoPhotoClient($photoURI);
        $scale_axis = 500;
        $scale = 500;
        $feeds = $client->Feeds();
        $feedList = $feeds->ListFeeds(0,10);
        $articleClient=$client->Articles();
        //CHANGE FEED NUM HERE
        $articles = $articleClient->ListForFeed($feedList->items[0]->id,'live',0,100);
        $articles_imported = 0;
        $articles->items = array_reverse($articles->items);
        foreach ($articles->items as $a) {
            $thisArticle = $client->Articles()->Get($a->id);
            if(!in_array($a->id,$this->id_collection)) {
                $strPost = '';
                $createCat = array();
                $post_title = $thisArticle->fields['title'];
                $post_date = $thisArticle->fields['lastModifiedDate'];
                $post_content = $thisArticle->fields['content'];
                $post_excerpt = isset($thisArticle->fields['extract'])  ?: ' ';
                $brafton_id = $a->id;
                $articles_imported++;
                if($articles_imported>7) break;
                echo 'Adding video blog: '.$a->id . ' : '.$thisArticle->fields['title'].'<br />';
                $slug=str_replace(' ','-',$post_title);
                // Enter Author Tag
                $categories = $client->Categories();
                $single_cat;
                if(isset($categories->ListForArticle($a->id,0,100)->items[0]->id)){
                    $categoryId = $categories->ListForArticle($a->id,0,100)->items[0]->id;
                    $category = $categories->Get($categoryId);
                    $createCat[] = $category->name;
                    $single_cat = isset($category->name) ? : ' ';
                } else {
                    $single_cat = '';
                } 
                $thisPhoto = $photos->ListForArticle($brafton_id,0,100);
                if(isset($thisPhoto->items[0]->id)){
                        $photoId = $photos->Get($thisPhoto->items[0]->id)->sourcePhotoId;
                        $photoURL = $photoClient->Photos()->GetScaleLocationUrl($photoId, $scale_axis, $scale)->locationUri;
                        $post_image = strtok($photoURL, '?');
                        $post_image_caption = $photos->Get($thisPhoto->items[0]->id)->fields['caption'];
                        $image_alt = '';
                        $image_id = $thisPhoto->items[0]->id;
                        $excerptImage = '<img src="' . $post_image . '" style = "width:300px;height:auto;vertical-align:middle; margin-bottom: 3px;float:right"  alt="Google Logo" />';
                }
                $presplash = $thisArticle->fields['preSplash'];
                $presplash = $this->convertProtocol($presplash);
                $postsplash = $thisArticle->fields['postSplash'];
                $videoList=$videoOutClient->ListForArticle($brafton_id,0,10);
                $list=$videoList->items;
                $embedCode = sprintf( "<video id='video-%s' class=\"ajs-default-skin atlantis-js\" controls preload=\"auto\" width='512' height='288' poster='%s' >", $brafton_id, $presplash );
                foreach($list as $listItem){
                    $output=$videoOutClient->Get($listItem->id);
                    $type = $output->type;
                    $path = $output->path;
                    $resolution = $output->height;
                    $source = $this->generate_source_tag( $path, $resolution );
                    $embedCode .= $source;
                }
                $embedCode .= '</video>';
                $ctascript = '';
                $video_player = "atlantisjs";
                if ($video_player == "atlantisjs"){

                    $script = '<script type="text/javascript">';
                    $script .=  'var atlantisVideo = AtlantisJS.Init({';
                    $script .=  'videos: [{';
                    $script .='id: "video-' . $brafton_id . '"';
                    $script .= '}]';
                    $script .= '});';
                    $script .=  '</script>';
                    $ctascript = $script;
                    $ctas = '';
                    $pause_text = '';
                    $pause_link = '';
                    $end_title = '';
                    $end_sub = '';
                    $end_link = '';
                    $end_text = '';
                    if($pause_text != ''){
                        $ctas =<<<EOT
                            ,
                            pauseCallToAction: {
                                text: "<a href='$pause_link'>$pause_text</a>"
                            },
                            endOfVideoOptions:{
                                callToAction: {
                                    title: "$end_title",
                                    subtitle: "$end_sub",
                                    button: {
                                        link: "$end_link",
                                        text: "$end_text"
                                    }
                                }
                            }
EOT;
                    }
                    $ctascript =<<<EOC
                    <script type='text/javascript' src='https://atlantisjs.brafton.com/v1/atlantis.min.v1.3.js'></script>
                        <script type="text/javascript">
                            var atlantisVideo = AtlantisJS.Init({
                                videos:[{
                                    id: "video-$brafton_id"$ctas
                                }]
                            });
                    </script>
EOC;
                    }
                    $strPost = $embedCode . $ctascript . $post_content;
                    $post_image = $this->convertProtocol($post_image);
                    $video_cache = $this->setVideoData($post_title,$post_excerpt, $post_date, $strPost, $post_image,$brafton_id,$single_cat);
                    $this->storeConnection->postArticle($video_cache, 'video');
        }
    }
}
}                   