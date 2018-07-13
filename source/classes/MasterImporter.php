<?php
spl_autoload_register(function ($class_name) {
    include $class_name .'.php';
});

abstract class MasterImporter{

    private $linker = array();
    private $articles_to_post = array();

    //compare collections of articles between Brafton XML feed and Shopify Blog API, returns an array of newsListItems to be posted to shopify blog
    public function compareCollections($items, $s) {
        foreach ($items as $item) {
            $brafton_id = $item->getId();
            $this->linker = $s->getLinkArray();
            if(!array_key_exists($brafton_id,$this->linker) || $this->checkForUpdate($s, $this->linker, $item, $brafton_id)){
                array_push($this->articles_to_post,$item);
            } else {
                echo '<span style="font-size:22px;display: block;text-align: center;">Article  '.$brafton_id.' already exists in blog </span><br />';
            }
        }
        return $this->articles_to_post;
    }

    //check to see whether existing article needs updating
    public function checkForUpdate($obj, $link, $current, $brafton_id ){
        $single = $obj->getShopifyArticle($link[$brafton_id]);
        $shop_date = $single->article->updated_at;
        if($current->getLastModifiedDate() > $shop_date && isset($_GET['override2'])) { //add get parameter test
            return true;
        } else {
            return false;
        }
    }

    //begin posting process
    public function importArticles($package,$shop){
        foreach($package as $item){
            $article_data = $this->setArticleData($item);
            echo '<br /> Adding post '.$item->getHeadline().' to the Blog.';
            $shop->postArticle($article_data,'article');
        }
    }

    //get article data from Brafton XML feed
    public function setArticleData($a){
        $image = $a->getPhotos();
        $large = $image[0]->getLarge();
        $cats = $a->getCategories();
        $ready_data = array('headline'=> $a->getHeadline(), 
            'id'=> $a->getId(), 
            'created'=> $a->getCreatedDate(), 
            'publish'=> $a->getPublishDate(),
            'byline'=> $a->getByLine() ?: 'brafton',
            'text'=> $a->getText(),
            'excerpt'=> $a->getExtract() ?: $a->getHtmlMetaDescription(),
            'image_url'=> $large->getUrl(),
            'image_width'=> $large->getWidth(),
            'image_height'=> $large->getHeight(),
            'caption'=> $image[0]->getAlt(),
            'categories'=> $this->setCatString($cats)
        );
        return $ready_data;
    }

    //get video blog data from Brafton video XML feed
    public function setVideoData($title,$excerpt,$date,$strContent,$image,$braf_id,$kitty=null){
        $ready_video_data = array('headline'=> $title, 
            'id'=> $braf_id, 
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
    public function getBraftonVideos($collection, $st){
        $private = brafton_private_key;
        $public = brafton_public_key; 
        $domain = preg_replace('/https:\/\//','',domain);
        $params = array('max'=>99);
        $baseURL = 'http://livevideo.'.str_replace('http://', '',$domain).'/v2/';
        $videoClient = new AdferoVideoClient($baseURL, $public, $private);
        $client = new AdferoClient($baseURL, $public, $private);
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
            //check if video blog does not exist in Shopify
            if(!in_array($a->id,$collection)) {
                $strPost = '';
                $createCat = array();
                $post_title = $thisArticle->fields['title'];
                $post_date = $thisArticle->fields['lastModifiedDate'];
                $post_content = $thisArticle->fields['content'];
                $post_excerpt = $thisArticle->fields['extract']  ? $thisArticle->fields['extract'] : ' ';
                $brafton_id = $a->id;
                $articles_imported++;
                if($articles_imported>5) break;
                //echo "POSTING: ".$post_title."<br>";
                $slug=str_replace(' ','-',$post_title);
                // Enter Author Tag
                $categories = $client->Categories();
                $single_cat;
                if(isset($categories->ListForArticle($a->id,0,100)->items[0]->id)){
                    $categoryId = $categories->ListForArticle($a->id,0,100)->items[0]->id;
                    $category = $categories->Get($categoryId);
                    $createCat[] = $category->name;
                    $single_cat = $category->name ? $category->name : ' ';
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
                    $video_cache = setVideoData($post_title,$post_excerpt, $post_date, $strPost, $post_image,$brafton_id,$single_cat);
                    $st->postArticle($video_cache, 'video');
        }
    }
}
}                   