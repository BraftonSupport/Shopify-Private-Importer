<?php

class DefinedConstants {
    public static function getConstants($refined){
        define('SHOPIFY_PRIVATE_KEY', $refined->shop_private);
        define('SHOPIFY_PRIVATE_PW', $refined->shop_pw);
        define('SHOPIFY_STORE_NAME',$refined->store_name);
        define('SHOPIFY_BLOG_ID', $refined->blog_id);
        define('domain','https://api.brafton.com');
        define('brafton_api',$refined->brafton_api);
        define('video_import',$refined->video);
        if(video_import == true) :
            define('brafton_private_key',$refined->brafton_private);
            define('brafton_public_key',$refined->brafton_public);
        endif;
    }
}

?>