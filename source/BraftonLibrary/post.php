<?php
class brafton_post{

    public $article_id;

    private $name;
    private $meta_description;
    private $slug;
    private $post_body;
    private $post_summary;
    private $topics;
    private $date;
    private $author;

    public function __construct($title,$meta_desc,$slug,$body,$summary,$author,$topics = false,$video = false, $date = false,$ctas=false, $featured=false){

        //creates blog post in draft format, updates it with desired data
        //returns article id, so it can be published if desired
        //3 step process recommended by hubspot api docs

        $this->name = $title;
        $this->meta_description = $meta_desc;
        $this->slug = $slug;
        $this->body = $body;
        $this->summary = $summary;
        $this->topics = $topics;
        $this->date = strtotime($date)*1000;
        $this->author = $author;
        $this->featured_image = $featured;
        $this->use_featured_image = false;
        
        if($featured){
            $this->featured_image = $featured;
            $this->use_featured_image = true;
            
        }



        $article = array(
            'name'=>$this->name,
            'content_group_id'=>blog_id,
        );

        $article_json = $this->import_post($article);
        $hubCheck = $article_json;
        // Debug the hubspot object sent back from creating article
        /*echo '<pre>';
        var_dump($hubCheck);
        echo '</pre>';
        */
        $this->article_id = $article_json->id;
        
        $updated_article = array(
            'blog_author_id'=>$this->author,
            'meta_description'=>$this->meta_description,
            'slug'=>$this->slug,
            'post_body'=>$this->body,
            'publish_immediately'=>true,
            "post_summary"=> $this->summary,
            "featured_image" => $this->featured_image,
            "preview_image_src" => $this->featured_image,
            "use_featured_image" => $this->use_featured_image
        );

        if($topics){
            $topics_string = "[";
            foreach($topics as $topic){
                $topics_string .= $topic . ',';
            }

            $topics_string .= "]";
            $updated_article['topic_ids'] = $topics;
        }
        $hubUpdate = $updated_article;
        //Debug the update array for sending to hubspot with complete article info
        /*echo '<pre>';
        var_dump($hubUpdate);
        echo '</pre>';
        */
        if($video){
        $updated_article['head_html'] = '<link rel="stylesheet" href="http://atlantisjs.brafton.com/v1/atlantisjsv1.3.css" type="text/css" /><script src="http://atlantisjs.brafton.com/v1/atlantis.min.v1.3.js" type="text/javascript"></script>';
        $updated_article['footer_html'] = $ctas;
        }
        //Dump out the return from Hubspot 
        var_dump($this->update_post($this->article_id,$updated_article));



    }

    private function import_post($jsonbody){
        $url =  'https://api.hubapi.com/content/api/v2/blog-posts?hapikey=' . hub_apiKey;
        $body = json_encode($jsonbody);

        return execute_post_request($url, $body,true);
    }

    private function update_post($article_id,$json_body){

        $url =  'https://api.hubapi.com/content/api/v2/blog-posts/' . $article_id . '?hapikey=' . hub_apiKey;

        $body = json_encode($json_body);

        return execute_put_request($url, $body,true);

    }

    public function publish_post($article_id,$castleford = false, $publish_date = false){

        $url =  "https://api.hubapi.com/content/api/v2/blog-posts/$article_id/publish-action?portalId=" . portal  . "&hapikey=" . hub_apiKey;
        
        //echo $url;
        $date = $castleford ? $this->date - 86400000 : $this->date;
        
        $updated_article = array(
            "publish_date"=> $date,
            );
       $response = $this->update_post($article_id,$updated_article);
        
        $json_body = array(
            'action'=>'schedule-publish',
        );
        
        $body = json_encode($json_body);

        $a = execute_post_request($url, $body,true);
        
        if(DEBUG){
            echo '<pre>';
            var_dump($a, $response);
            echo '</pre>';
        }

    }

}   

?>
