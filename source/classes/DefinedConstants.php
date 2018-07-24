<?php

class DefinedConstants {
    public static function getConstants($refined){
        define('SHOPIFY_PRIVATE_KEY', $refined->shop_private);
        define('SHOPIFY_PRIVATE_PW', $refined->shop_pw);
        define('SHOPIFY_STORE_NAME',$refined->store_name);
        define('SHOPIFY_BLOG_ID', $refined->blog_id);
        define('DOMAIN','https://api.brafton.com');
        define('BRAFTON_API',$refined->brafton_api);
        define('VIDEO_IMPORT',$refined->video);
        if(VIDEO_IMPORT == true) :
            define('BRAFTON_PRIVATE_KEY',$refined->brafton_private);
            define('BRAFTON_PUBLIC_KEY',$refined->brafton_public);
        endif;
    }
}

?>