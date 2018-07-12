<?php 

//Basic class for Shopify Blog Client

class store {
	private $shopify_api;
	private $shopify_pw;
	private $shopify_store;
	private $brafton_api;
	private $shopify_blogId;
	private $storeRoot;

	function __construct($api,$pw,$store,$blogid){

		$this->shopify_api = $api;
		$this->shopify_pw = $pw;
		$this->shopify_store = $store;
		$this->shopify_blogId = $blogid;
		$this->storeRoot = $this->buildLocale();
	}

	public function buildLocale(){
		return 'https://'.$this->shopify_api.':'.$this->shopify_pw.'@'.$this->shopify_store.'.myshopify.com/admin/blogs/'.$this->shopify_blogId;
	}
	public static function getLocale(){
		return self::$storeRoot;
	}

	public function getStoreRoot(){
		return $this->storeRoot;
	}

}

?>