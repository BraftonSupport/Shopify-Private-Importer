<?php


class BraftonApi {
	public $api;
	public $domain;
	private $base;

	function __construct($key,$root){
		$this->api = $key;
		$this->domain = $root;
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
}

?>