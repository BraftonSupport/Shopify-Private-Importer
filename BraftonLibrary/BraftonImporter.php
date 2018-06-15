<?php
class BraftonImporter {
/*****************************************************************************************
 *
 * Import Articles from the XML Feeds
 *
 *****************************************************************************************
 */
    function import_articles($titles,$existing_topics){
        echo ' Articles: <br/>';
        $limit = isset($_GET['limit']) ? $_GET['limit'] : 5;
        if(isset($_GET['archive']) && file_exists('archive-'.client.'.xml')){
            echo 'From Archive File<br/>';
            //$dir = 'http://tech.brafton.com/hubspot/cos/'.client.'/';
            $dir = '/var/www/html/tech/hubspot/cos/'.client.'/';
            echo $dir;
            $articles = NewsItem::getNewsList($dir.'archive-'.client.'.xml', "html");
            $limit = count($articles);
            echo ' Import ' . $limit . ' articles<br/>';
        }else{
            $fh = new ApiHandler(brafton_apiKey, domain );
            $articles = $fh->getNewsHTML();
            echo 'From API Feed<br/>';
        }
        $articles_imported = 0;

        foreach ($articles as $a) {

            $strPost = '';
            $createCat = array();
            $brafton_id = $a->getId();
            $post_title = $a->getHeadline();
            echo "Checking: ".$post_title."<br/>";
            // check against existing posts here.  Use title.
            if (compare_post_titles($post_title,$titles)) continue;
            if($articles_imported>$limit) break;
            echo "POSTING: ".$post_title."<br>";

            $post_date = $a->getPublishDate();
            $post_content = $a->getText();
            $post_excerpt = $a->getExtract();
            $words = str_word_count($post_title,1);

            $count = 0;
            $slug=str_replace(' ','-',$post_title);
			$num = count($words);

            $CatColl = $a->getCategories();
            $meta = $a->getHtmlMetaDescription();
            // Enter Author Tag
            $author = author_id;

            foreach ($CatColl as $category){
                if(!$category) break;
                $createCat[] = $category->getName();
            }

			$article_topics = array();

			foreach($createCat as $brafton_cat){

                $topic_exists = false;
				$new_cat = false;
                foreach ($existing_topics as $topic) {
                    if(strtolower($brafton_cat) == strtolower($topic)){
			            $article_topics[] = array_search( $topic, $existing_topics);
                        $topic_exists = true;
			             break;
                    }

                }

				if(!$topic_exists){
					$c = create_topic($brafton_cat,$existing_topics);
					$response = $c[0];
					$existing_topics = $c[1];
					$article_topics[] = $response->id;
				}
			}

                $photos = $a->getPhotos();
                if(!empty($photos)){
                    $image = $photos[0]->getLarge();
                    $post_image = $image->getUrl();
                }
                $post_summary = $post_excerpt;
                if(!empty($post_image)){
                    $image_id = $photos[0]->getId();
                    $image_small = $photos[0]->getThumb();
                    $post_image_caption = "<div style='padding:6px;'>" . $photos[0]->getCaption() . "</div>";

                    $post_image_small = $image_small->getURL();
                    if(image_import){
                        $post_image = upload_image($post_image);
                    }

                    $divcode = '<div style="width:300px;background-color: #F9F9F9; border-radius:10px; padding: 3px;font: 11px/1.4em Arial, sans-serif;margin: 0.5em 0.8em 0.5em 0.5em; float:right;">';
                    $imgcode = '<img src="' . $post_image . '" style = "width:300px;vertical-align:middle; margin-bottom: 3px;"  alt="'.$photos[0]->getCaption().'" />';
                    $excerptImage = '<img src="' . $post_image . '" style = "width:300px;height:auto;vertical-align:middle; margin-bottom: 3px;float:right"  alt="'.$photos[0]->getCaption().'" />';
                    $div = $divcode . $imgcode . '<br/>' . $post_image_caption . '</div>';
                    $strPost = $strPost.$div.$post_content;
                    $post_summary =  $excerptImage . $post_excerpt;
                } else {
                    $strPost= $strPost . $post_content;
                }
            if(defined('dynamic_author') && (dynamic_author)){
                $author = dynamicAuthor($a->getByLine());
            }
            else{
                $author = author_id;
            }

            echo '<br/>This is the author : '.$author.'<br/>';
            $post = new brafton_post($post_title,$post_excerpt,$slug,$strPost,$post_summary,$author,$article_topics,false,$post_date, false, $post_image);

            $id = $post->article_id;
            $featuredImage = array(
                'featured_image'    => $post_image
                );
            /*
            if(imgage_import){
                $response = $post->update_post($id, $featuredImage);
                if(DEBUG){
                    echo '<pre>';
                    var_dump($response);
                    echo '</pre>';
                }
            }
            */
            if(post_status == 'published'){
                $post->publish_post($id);
            }
            // broken...  what is time format of date on brafton feed?  will almost certainly need to convert.
            $articles_imported++;
        }
    }

    /**********************************************************************************************
 *
 * Import Videos from the XML Feeds
 *
 **********************************************************************************************
 */
    function import_videos($titles,$existing_topics){
        echo " Videos: <br/>";
        $params = array('max'=>99);

        $baseURL = 'http://livevideo.'.str_replace('http://', '',domain).'/v2/';
        echo $baseURL;
        $videoClient = new AdferoVideoClient($baseURL, brafton_video_publicKey, brafton_video_secretKey);
        $client = new AdferoClient($baseURL, brafton_video_publicKey, brafton_video_secretKey);
        $videoOutClient = $videoClient->videoOutputs();

        $photos = $client->ArticlePhotos();
        $photoURI = 'http://'.str_replace('http://api', 'pictures',domain).'/v2/';
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
            $post = new brafton_post($post_title,$post_excerpt,$slug,$strPost,$post_excerpt,$author,$article_topics,true, $post_date,$ctascript, $post_image);

            $id = $post->article_id;

            if(post_status == 'published'){
                $post->publish_post($id);
            }

        }

    }
}
?>
