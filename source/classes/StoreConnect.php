<?php

class StoreConnect {
	public $base;
	public $postData;
	public $getUrl;
	public $postUrl;
	public $currentArticles = array();
	private $brafton_collection = array();
	private $link_array = array();

	function __construct($root,$url){
		$this->base = $root;
		$this->getUrl = $url;
	}

	//get individual Shopify Blog Article
	public function getShopifyArticle($_id){
		$url = $this->base.'/articles/'.$_id.'.json';
		$single = $this->storeGetRequest($url);
		return $single;
	}

	//return collection of brafton ids on demand
	public function getBraftonCollection(){
		return $this->brafton_collection;
	}

	//update existing Shopify article
	public function updateArticle($arr, $shopify_article_id){
		$url = $this->base.'/articles/'.$arr['brafton_id'].'.json';
		$obj = $this->setPutData($arr);
		$this->storePutRequest($url,$obj);
	}
	
	//get article data from Shopify API
	public function getArticles(){
		$location = $this->getUrl;
		$output = $this->storeGetRequest($location);
		$this->currentArticles = $output->articles;
		return $this->currentArticles;
	}

	//Use for GET requests to Shopify API
	public function storeGetRequest($location){
		$shopcurl = curl_init();
		curl_setopt($shopcurl, CURLOPT_URL, $location);
		curl_setopt($shopcurl, CURLOPT_HTTPHEADER, array('Content-Type:multipart/form-data'));
		curl_setopt($shopcurl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($shopcurl, CURLOPT_HEADER, 0);
		curl_setopt($shopcurl, CURLOPT_CUSTOMREQUEST, "GET");
		curl_setopt($shopcurl, CURLOPT_SSL_VERIFYPEER, false);
		$response = curl_exec($shopcurl);
		$output = json_decode($response);
		curl_close($shopcurl);
		return $output;
	}

	//Use for POST requests to Shopify API
	public function storePostRequest($location,$obj){
		$crl = curl_init();
		curl_setopt($crl, CURLOPT_URL, $location);
		curl_setopt($crl, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
		curl_setopt($crl, CURLOPT_POSTFIELDS, $obj);                                                                  
		curl_setopt($crl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($crl, CURLOPT_HTTPHEADER, array(                                                                          
		    'Content-Type: application/json',                                                                                
		    'Content-Length: ' . strlen($obj))                                                                       
		);
		curl_setopt($crl, CURLOPT_SSL_VERIFYPEER, false);                                                                                                                                                                                                                                        
		$result = curl_exec($crl);
		$errno = curl_errno($crl);
    	$error = curl_error($crl);
    	curl_close($crl);
    	if ($errno > 0) {
			echo 'cURL error: ' . $error;
			return $error;
	    } else {
    		return json_decode($result);
    	}
	}
	//use for PUT requests to Shopify API
	public function storePutRequest($location,$obj){
		$crl = curl_init();
		curl_setopt($crl, CURLOPT_URL, $location);
		curl_setopt($crl, CURLOPT_CUSTOMREQUEST, "PUT");                                                                     
		curl_setopt($crl, CURLOPT_POSTFIELDS, $obj);                                                                  
		curl_setopt($crl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($crl, CURLOPT_HTTPHEADER, array(                                                                          
		    'Content-Type: application/json',                                                                                
		    'Content-Length: ' . strlen($obj))                                                                       
		);
		curl_setopt($crl, CURLOPT_SSL_VERIFYPEER, false);                                                                                                                                                                                                                                        
		$result = curl_exec($crl);
		$errno = curl_errno($crl);
    	$error = curl_error($crl);
    	curl_close($crl);
    	if ($errno > 0) {
	        echo 'cURL error: ' . $error;
	    } else {
    		return json_decode($result);
    	}
	}

	//Post Article to Shopify Blog
	public function postArticle($arr, $article_type=null){
		$obj = $this->setPostData($arr);
				
		$this->postUrl = $this->base.'/articles.json';
		if(in_array($arr['brafton_id'], $this->getBraftonCollection())) { 
			$update = $this->setPutData($arr);
			$this->updateArticle($update,$this->link_array[$arr['brafton_id']]); //passing shopify article id and json ready data, no need to add metafield
		} else{
			$dis = $this->storePostRequest($this->postUrl,$obj);
			//post meta field data to newly created blog, need to send an array of objects here and loop through in order to utilize more than one metafield
			$meta_array = $this->setPostMeta('brafton_id',$arr['brafton_id'], $article_type);
			foreach($meta_array as $meta){
				$json_meta = json_encode($meta);
				$this->postArticleMeta($dis->article->id, $json_meta);
			}
		}	
	}

	//ready article meta data for posting to Shopify API
	public function setPostMeta($key, $value, $type){ 
		$metafields = array( array('metafield'=>
										array(
												'key'=> $key,
												'value'=> (string)$value,
												'value_type'=> 'string',
												'namespace'=> 'blog'
										)
									),
						array('metafield'=>
									array(
											'key'=> 'type',
											'value'=> $type,
											'value_type'=> 'string',
											'namespace'=> 'blog'
									)
								)
		);
		return $metafields;
	}

	//build url for article metafield endpoint
	public function getMetafieldEndpoint($a){
		$endpoint = $this->base.'/articles/'.$a.'/'.'metafields.json';
		return $endpoint;
	}

	//array helper function to extract brafton id from shopify metafields endpoint object
	public function metaHelper($meta_fields){
		foreach($meta_fields as $field){
			if($field->key == 'brafton_id'){
				return $field->value;
			}				
		}
	}

	//Get collection of brafton ids from target blog
	public function getArticleMeta(){
		$this->getArticles(); //get article data from shopify API
		foreach($this->currentArticles as $article){
			$metaUrl = $this->getMetafieldEndpoint($article->id);
			$metaOut = $this->storeGetRequest($metaUrl);
			$brafton_meta = $this->metaHelper($metaOut->metafields);
			if(isset($brafton_meta)){ //look at index, loop through all metafields in search of brafton_id key
				array_push($this->brafton_collection,$brafton_meta);
				$this->setLinkArray($brafton_meta,$article->id);
			}
		}
		return $this->brafton_collection;
	}


	//set link array which associates brafton id with shopify article id
	public function setLinkArray($bm, $artid){
		$this->link_array[$bm] = (string)$artid;
	}

	//get link array 
	public function getLinkArray(){
		return $this->link_array;
	}

	//post article meta to blog article.  this happens after the article post to the shopify API
	public function postArticleMeta($id,$meta){
		$url = $this->getMetafieldEndpoint($id);
		$this->storePostRequest($url,$meta);
	}

	//ready article general data for updating existing Shopify Article
	public function setPutData($article){
		if($article['archive']==true) :
			$raw = substr($article['publish'],0,7);
			$archival = date('Y F', strtotime($raw));
		endif;
		$post_data = array('article'=> 
					array(
						'id'=> $article['brafton_id'],
						'title'=> $article['headline'],
						'author'=>$article['byline'], 
						'body_html'=>$article['text'], 
						'tags'=> $article['categories'], $archival = null,
						'summary_html'=> $article['excerpt'],
						'image'=> array(
							'width'=>$article['image_width'],
							'height'=>$article['image_height'],
							'src'=>$article['image_url'],
							'caption'=>$article['caption']
						)
					)
				);
		return json_encode($post_data);
	}
	//ready general article data for posting to Shopify	API
	public function setPostData($article){
		if($article['archive']==true) :
			$raw = substr($article['publish'],0,7);
			$archival = date('Y F', strtotime($raw));
		endif;
		$post_data = array('article'=> 
					array(
						'title'=> $article['headline'],
						'author'=>$article['byline'], 
						'body_html'=>$article['text'], 
						'tags'=> $article['categories'].', '. $archival=null,
						'summary_html'=> $article['excerpt'],
						'image'=> array(
							'width'=>$article['image_width'],
							'height'=>$article['image_height'],
							'src'=>$article['image_url'],
							'caption'=>$article['caption']
						)
					)
				);
		return json_encode($post_data);
	}
}
?>