<?php

require_once 'libraries.php';

spl_autoload_register(function ($class_name) {
    include 'classes/'.$class_name .'.php';
});

//set up a new store
$store = new Store('SHOPIFY_PRIVATE_KEY','SHOPIFY_PRIVATE_PW','brafton-importer','12680069178');
$video_client = true;
$url = $store->getStoreRoot()."/articles.json";

//set connection to Shopify blog
$storeConnection = new storeConnect($store->getStoreRoot(),$url);


//return collection of brafton ids from Shopify blog
$collection = $storeConnection->getArticleMeta();

//connect to Brafton XML feed
$brafton_connect = new ApiHandler('92599a2e-a5d6-41ff-93ac-11484207dc9d','https://api.brafton.com');
$articles = $brafton_connect->getNewsHTML();
compareCollections($articles, $collection,$storeConnection,'articles');

//compare collections
function compareCollections($items, $collection,$s,$type) {
	$linker = array();
	foreach ($items as $item) {
		$brafton_id = $item->getId();
		
		$linker = $s->getLinkArray();
		// echo '<pre>';
		// print_r($linker);
		if(array_key_exists($brafton_id,$linker)){
			
			$single = $s->getShopifyArticle($linker[$brafton_id]);
			$shop_date = $single->article->updated_at;
			if($item->getLastModifiedDate()>$shop_date) {
				echo 'post should be updated <br />';
				$article_data = setArticleData($item);
				$article_data['id'] = $linker[$brafton_id];
				$s->updateArticle($article_data);
			} else{
				echo '<span style="font-size:22px;display: block;text-align: center;">Article  '.$brafton_id.' already exists in blog </span><br />';
			}
		}else{
			$article_data = setBlogPostData($item,$type);
			echo '<br /> Adding post '.$item->getHeadline().' to the Blog.';
			$s->postArticle($article_data);
		}
	}
}

function setBlogPostData($item,$type) {
	if($type=='articles'){
		return setArticleData($item);
	}else{
		return setVideoData($item);
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
		'text'=> $a->getText(),
		'excerpt'=> $a->getExtract() ?: $a->getHtmlMetaDescription(),
		'image_url'=> $large->getUrl(),
		'image_width'=> $large->getWidth(),
		'image_height'=> $large->getHeight(),
		'caption'=> $image[0]->getAlt(),
		'categories'=> $cats[0]->getName() //fix later to accommodate multiple categories
	);
	return $ready_data;

}

function setVideoData($title,$excerpt,$date,$strContent,$image,$braf_id){
	$ready_video_data = array('headline'=> $title, 
		'id'=> $braf_id, 
		'created'=> $date, 
		'publish'=> $date,
		'byline'=> 'brafton',
		'text'=> $strContent,
		'excerpt'=> $excerpt,
		'image_url'=> '',
		'image_width'=> '',
		'image_height'=> '',
		'caption'=> '',
		'categories'=> ''
	);
	print_r($excerpt);
	die();
}

function generate_source_tag($src, $resolution){
    $tag = ''; 
    $ext = pathinfo($src, PATHINFO_EXTENSION); 
    return sprintf('<source src="%s" type="video/%s" data-resolution="%s" />', $src, $ext, $resolution );
}

function getBraftonVideos($collection){
	$private = '29f07cf9-50c8-42b5-abf0-123eb614d4fd';
	$public = 'U4S7U8A9'; 
	$domain = 'api.brafton.com';
	//echo " Videos: <br/>";
	$params = array('max'=>99);
	//$baseURL = 'http://livevideo.api.brafton.com/v2/';
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
	// echo '<pre>';
	// var_dump($articles);
	$articles_imported = 0;
	//fix for video feed issue present on june 24 2015.  video feeds are supposed to be sorted by modified date in desc order but are showing up in asc order instead. //remove once fixed. note dk
	$articles->items = array_reverse($articles->items);
   foreach ($articles->items as $a) {
		//max of five articles imported
		$thisArticle = $client->Articles()->Get($a->id);
		//var_dump($thisArticle);
		if(!in_array($a->id,$collection)) {
			$strPost = '';
			$createCat = array();
			$post_title = $thisArticle->fields['title'];
			$post_date = $thisArticle->fields['lastModifiedDate'];
			$post_content = $thisArticle->fields['content'];
			$post_excerpt = $thisArticle->fields['extract'];
			$brafton_id = $a->id;
			$articles_imported++;
			if($articles_imported>5) break;
			//echo "POSTING: ".$post_title."<br>";
			$slug=str_replace(' ','-',$post_title);
			// Enter Author Tag
			$categories = $client->Categories();
			if(isset($categories->ListForArticle($a->id,0,100)->items[0]->id)){
				$categoryId = $categories->ListForArticle($a->id,0,100)->items[0]->id;
				$category = $categories->Get($categoryId);
				//echo "<br><b>Category Name:</b>".$category->name."<br>";
				$createCat[] = $category->name;
			}
		
			$thisPhoto = $photos->ListForArticle($brafton_id,0,100);
			if(isset($thisPhoto->items[0]->id)){
					$photoId = $photos->Get($thisPhoto->items[0]->id)->sourcePhotoId;
					//echo 'Photo Id : '.$photoId.'<br/>';
					$photoURL = $photoClient->Photos()->GetScaleLocationUrl($photoId, $scale_axis, $scale)->locationUri;
					//echo 'Photo url : '.$photoURL.'<br/>';
					$post_image = strtok($photoURL, '?');
					//echo 'Photo image : '.$post_image.'<br/>';
					$post_image_caption = $photos->Get($thisPhoto->items[0]->id)->fields['caption'];
					//echo 'Photo caption : '.$post_image_caption.'<br/>';
					$image_alt = '';
					$image_id = $thisPhoto->items[0]->id;
					// if(image_import){
					// 	$post_image = upload_image($post_image);
					// }
				$excerptImage = '<img src="' . $post_image . '" style = "width:300px;height:auto;vertical-align:middle; margin-bottom: 3px;float:right"  alt="Google Logo" />';
				//$post_excerpt =  $excerptImage . $post_excerpt;
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
			$presplash = convertProtocol($presplash);
			$postsplash = $thisArticle->fields['postSplash'];
			$videoList=$videoOutClient->ListForArticle($brafton_id,0,10);
			$list=$videoList->items;
			
			$embedCode = sprintf( "<video id='video-%s' class=\"ajs-default-skin atlantis-js\" controls preload=\"auto\" width='512' height='288' poster='%s' >", $brafton_id, $presplash );
			foreach($list as $listItem){
				$output=$videoOutClient->Get($listItem->id);
				//logMsg($output->path);
				$type = $output->type;
				$path = $output->path;
				// echo $path;
				// die();
				$resolution = $output->height;
				$source = generate_source_tag( $path, $resolution );
				$embedCode .= $source;
			}
			$embedCode .= '</video>';
			//old code
			//$embedCode = $videoClient->VideoPlayers()->GetWithFallback($brafton_id, 'redbean', 1, 'rcflashplayer', 1);
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
				$post_image = convertProtocol($post_image);
				setVideoData($post_title,$post_excerpt, $post_date, $strPost, $post_image,$brafton_id);
				/*$post = new brafton_post($post_title,$post_excerpt,$slug,$strPost,$post_excerpt,$author,$article_topics,true, $post_date,$ctascript, $post_image);
				$id = $post->article_id;
				if(post_status == 'published'){
					$post->publish_post($id);
				}*/
	}
   }
}

function convertProtocol($address){
	return str_replace('http','https',$address);
}

if($video_client) :
	$videos = getBraftonVideos($collection);
endif;
  
?>