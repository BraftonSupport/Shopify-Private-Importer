<?php

class BraftonApi {
	public $api;
	public $private_key;
	public $public_key;
	public $domain;
	private $base;

	function __construct($key,$root,$pvt,$pub){
		$this->api = $key;
		$this->domain = $root;
		$this->private_key = $pvt;
		$this->public_key = $pub;
	}

	//gather brafton ids from XML feed
	public function getBraftonFeedIds(){
		$this->base = 'http://api.'.$this->domain.'.com/'.$this->api.'/news/';
		$xml = simplexml_load_file($this->base);
		$items = $xml->newsListItem;
		$id_array = array();
		foreach($items as $item){
			array_push($id_array,$item->id); 
		}
		return $id_array;
	}

	//fetch article from XML feed
	public function getBraftonArticle($bft){
		$url = $this->base.$bft;
		$xml = simplexml_load_file($url);
		$photo = $this->getPhoto($xml->photos[0]['href']);
		$byline = $xml->byline ? $xml->byline : 'brafton'; //using 'brafton' as default author otherwise we get 'Shopify API'
		$cats = $this->getCategories($url);
		$article = array(
				'headline'=> $xml->headline, 
				'id'=>$xml->id, 
				'created'=> $xml->createdDate, 
				'publish'=> $xml->publishDate,
				'byline'=> $byline,
				'text'=> $xml->text,
				'excerpt'=>$xml->extract,
				'image_url'=> $photo->photo->instances[0]->instance->url,
				'image_width'=> $photo->photo->instances[0]->instance->width,
				'image_height'=> $photo->photo->instances[0]->instance->height,
				'caption'=> $photo->photo->caption,
				'categories'=> $cats->category->name
		);
		return $article;
	}

	//fetch photo data from XML feed
	public function getPhoto($ph){

		$xml = simplexml_load_file($ph);
		return $xml;
	}

	//fetch categories from XML feed
	public function getCategories($url){
		$cat_url = $url.'/categories';
		$xml = simplexml_load_file($cat_url);
		return $xml;
	}

	public function getBraftonVideos($titles,$existing_topics){
		echo " Videos: <br/>";
        $params = array('max'=>99);
        $baseURL = 'http://livevideo.api.'.str_replace('http://', '',$this->domain).'.com/v2/';
        echo $baseURL;
        $videoClient = new AdferoVideoClient($baseURL, $this->public_key, $this->private_key);
        $client = new AdferoClient($baseURL, $this->public_key, $this->private_key);
        $videoOutClient = $videoClient->videoOutputs();
        $photos = $client->ArticlePhotos();
        $photoURI = 'http://'.str_replace('http://api', 'pictures',$this->domain).'/v2/';
        $photoClient = new AdferoPhotoClient($photoURI);
        $scale_axis = 500;
        $scale = 500;
        $feeds = $client->Feeds();
        $feedList = $feeds->ListFeeds(0,10);
        $articleClient=$client->Articles();
        //CHANGE FEED NUM HERE
        $articles = $articleClient->ListForFeed($feedList->items[0]->id,'live',0,100);
        $articles_imported = 0;
        //fix for video feed issue present on june 24 2015.  video feeds are supposed to be sorted by modified date in desc order but are showing up in asc order instead. //remove once fixed. note dk
        $articles->items = array_reverse($articles->items);
       foreach ($articles->items as $a) {
            //max of five articles imported
            $thisArticle = $client->Articles()->Get($a->id);
            $strPost = '';
            $createCat = array();
            $post_title = $thisArticle->fields['title'];
            $post_date = $thisArticle->fields['lastModifiedDate'];
            $post_content = $thisArticle->fields['content'];
            $post_excerpt = $thisArticle->fields['extract'];
            $brafton_id = $a->id;
            // check against existing posts here.  Use title.
            echo "Checking: ".$post_title."<br/>";
            if (compare_post_titles($post_title,$titles)) continue;
            $articles_imported++;
            if($articles_imported>5) break;
            echo "POSTING: ".$post_title."<br>";
            $slug=str_replace(' ','-',$post_title);
            //$meta = $a->getHtmlMetaDescription();
            // Enter Author Tag
            $author = author_id;
			$categories = $client->Categories();
			if(isset($categories->ListForArticle($a->id,0,100)->items[0]->id)){
				$categoryId = $categories->ListForArticle($a->id,0,100)->items[0]->id;
				$category = $categories->Get($categoryId);
				echo "<br><b>Category Name:</b>".$category->name."<br>";
				$createCat[] = $category->name;
			}
			$article_topics = array();
			foreach($createCat as $brafton_cat){
				echo "topic first loop: $brafton_cat <br/>";
                $topic_exists = false;
				$new_cat = false;
                foreach ($existing_topics as $topic) {
				echo "topic second loop: $topic <br/>";
                    if(strtolower($brafton_cat) == strtolower($topic)){
			echo "topic exists <br/>";
			echo "brafton cat: ". $brafton_cat . '<br/>';
                        echo "topic: $topic <br/>";
			$article_topics[] = array_search( $topic, $existing_topics);
                        $topic_exists = true;
			break;
                    }
                }
				if(!$topic_exists){
					echo "creating topic: $brafton_cat <br/>";
					$c = create_topic($brafton_cat,$existing_topics);
					$response = $c[0];
					$existing_topics = $c[1];
					$article_topics[] = $response->id;
				}
			}
            $thisPhoto = $photos->ListForArticle($brafton_id,0,100);
            if(isset($thisPhoto->items[0]->id)){
                    $photoId = $photos->Get($thisPhoto->items[0]->id)->sourcePhotoId;
                echo 'Photo Id : '.$photoId.'<br/>';
                    $photoURL = $photoClient->Photos()->GetScaleLocationUrl($photoId, $scale_axis, $scale)->locationUri;
                                echo 'Photo url : '.$photoURL.'<br/>';
                    $post_image = strtok($photoURL, '?');
                                echo 'Photo image : '.$post_image.'<br/>';
                    $post_image_caption = $photos->Get($thisPhoto->items[0]->id)->fields['caption'];
                                echo 'Photo caption : '.$post_image_caption.'<br/>';
                    $image_alt = '';
                    $image_id = $thisPhoto->items[0]->id;
                    if(image_import){
                        $post_image = upload_image($post_image);
                    }
                $excerptImage = '<img src="' . $post_image . '" style = "width:300px;height:auto;vertical-align:middle; margin-bottom: 3px;float:right"  alt="Google Logo" />';
                $post_excerpt =  $excerptImage . $post_excerpt;
            }
                // $photos = $a->getPhotos();
                // $image = $photos[0]->getLarge();
                // $post_image = $image->getUrl();
                // if(!empty($post_image)){
                //     $image_id = $photos[0]->getId();
                //     $image_small = $photos[0]->getThumb();
                //     $post_image_small = $image_small->getURL();
                //     $post_excerpt = $post_excerpt.'<img src = "'.$post_image.'" alt ="" /><p>'.$post_content.'</p>' ;
                // }
                $presplash = $thisArticle->fields['preSplash'];
                $postsplash = $thisArticle->fields['postSplash'];
                $videoList=$videoOutClient->ListForArticle($brafton_id,0,10);
                $list=$videoList->items;
                if (video_player == "atlantisjs")
                    $embedCode = sprintf( "<video id='video-%s' class=\"ajs-default-skin atlantis-js\" controls preload=\"auto\" width='512' height='288' poster='%s' >", $brafton_id, $presplash );
                else
                    $embedCode = sprintf( "<video id='video-%s' class='video-js vjs-default-skin' controls preload='auto' width='512' height='288' poster='%s' data-setup src='%s' >", $brafton_id, $presplash, $path );
                foreach($list as $listItem){
                    $output=$videoOutClient->Get($listItem->id);
                    //logMsg($output->path);
                    $type = $output->type;
                    $path = $output->path;
                    $resolution = $output->height;
                    $source = generate_source_tag( $path, $resolution );
                    $embedCode .= $source;
                }
                $embedCode .= '</video>';
                //old code
                //$embedCode = $videoClient->VideoPlayers()->GetWithFallback($brafton_id, 'redbean', 1, 'rcflashplayer', 1);
                $ctascript = '';
                if (video_player == "atlantisjs"){
                    /*
                    $script = '<script type="text/javascript">';
                    $script .=  'var atlantisVideo = AtlantisJS.Init({';
                    $script .=  'videos: [{';
                    $script .='id: "video-' . $brafton_id . '"';
                    $script .= '}]';
                    $script .= '});';
                    $script .=  '</script>';
                    $ctascript = $script;
                    */
                                $ctas = '';
            $pause_text = video_pause_text;
            $pause_link = video_pause_link;
            $end_title = video_end_title;
            $end_sub = video_end_subtitle;
            $end_link = video_end_button_link;
            $end_text = video_end_button_text;
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
                <script type="text/javascript">
                    var atlantisVideo = AtlantisJS.Init({
                        videos:[{
                            id: "video-$brafton_id"$ctas
                        }]
                    });
            </script>
EOC;
                }
                $strPost = $embedCode . $post_content;
                //echo $post_image;
            //echo $strPost."<br>";
            /*
            create/publish posts
            tbd: topics (categories), not hotlinking images?
            */
            $author = author_id;
            /*$post = new brafton_post($post_title,$post_excerpt,$slug,$strPost,$post_excerpt,$author,$article_topics,true, $post_date,$ctascript, $post_image);
            $id = $post->article_id;
            if(post_status == 'published'){
                $post->publish_post($id);
            }*/
        }
		return;
	}
}

?>