<?php  
/********************************************************************************************
 *
 * Function for uploading Images to clients hubspot account eliminates hotlinking
 *
 *********************************************************************************************
 */
$debug = isset($_GET['debug']) ? true : false;
define('DEBUG', $debug);
function dynamicAuthor($byLine_author){
    $byLine_author = urlencode($byLine_author);
    $url = "https://api.hubapi.com/blogs/v3/blog-authors/search/?fullName=$byLine_author&hapikey=".hub_apiKey;
    $authInfo = execute_get_request($url);
    return $authInfo->objects[0]->id;

}
function upload_image($img){

    echo 'image url: '.$img;
    $url = "https://api.hubapi.com/filemanager/api/v2/files?hapikey=".hub_apiKey;

    $filename = basename($img);
    $ext = pathinfo($filename, PATHINFO_EXTENSION); //Get image extention
    /**
     * Hubspot only accepts filenames less than 150 characters long so we need to rename some filenames to comply
     */
    $filename = explode('.',$filename);
    $filename = substr($filename[0], 0, 146);
    $filename .= '.'.$ext;
    echo $filename;
    
    $ic = curl_init($img);
    $io = fopen($filename, 'wb');
    curl_setopt($ic, CURLOPT_FILE, $io);
    curl_setopt($ic, CURLOPT_HEADER, 0);
    curl_exec($ic);
    curl_close($ic);
    fclose($io);
    //$imgUrl = fopen('Desert.jpg', 'rb');
    $imgUrl = $filename;
    $filetype = mime_content_type($imgUrl);

    $contenttype = 'image/jpeg';
    /* for PHP < 5.4
    $cfile = "@$imgUrl";
    $cfile .= ';filename='.$filename.';type='.$filetype;
    */
    // for PHP > 5.4 
    $cfile = curl_file_create($filename, $filetype, $filename);
    $json_body = array(
        'files' => $cfile,
        'folder_paths'  => image_folder
        );
    $myvar = $json_body;
    
    echo '<pre>';
    var_dump($myvar);
    echo '</pre>';
    
    $ch = curl_init();
    $options = array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER  => true,
        CURLINFO_HEADER_OUT => true,
        CURLOPT_HEADER => false,
        CURLOPT_HTTPHEADER  => array('Content-Type:multipart/form-data'),
        CURLOPT_SSL_VERIFYPEER  => false,
        CURLOPT_POST    => true,
        CURLOPT_POSTFIELDS  => $json_body
        );
    curl_setopt_array($ch, $options);
    $result = curl_exec($ch);
    $header_info = curl_getInfo($ch, CURLINFO_HEADER_OUT);
    curl_close($ch);
    unlink($imgUrl);
    $newData = json_decode($result);
    $nm = $newData;
    
    echo '<pre>From Hubspot';
    var_dump($nm);
    echo '</pre>';
    
    return $newData->objects[0]->friendly_url;
}

function list_blogs($params){
    $url = 'https://api.hubapi.com/content/api/v2/blogs';

    $url_params=params_to_string($params);

    $blogsInfo = execute_get_request($url . $url_params);

    echo "blog names and id's:<br/>";

    foreach ($blogsInfo->objects as $blog){
        echo $blog->html_title . ' - ' . $blog->id . '<br/>';
    }
}

function check_blog_id(){

    $blog_id_set=false;

    $url = 'https://api.hubapi.com/content/api/v2/blogs';

    $params = array(
        'hapikey'=>hub_apiKey,
    );

    $url_params=params_to_string($params);

    $blogsInfo = execute_get_request($url . $url_params);


    foreach($blogsInfo->objects as $blog){
            if($blog->id==blog_id) $blog_id_set = 1;
    }

    return $blog_id_set;

}

function list_post_titles($params){   
    /*
     * get a list of up to 50 most recent articles from the Drafts, Published, and Scheduled Sections.
     * List will be up to 150 articles by the end
     * 
     */

    $url = 'https://api.hubapi.com/content/api/v2/blog-posts';
    $titles = array();
    
    $params['state'] = 'DRAFT';
    $url_params = params_to_string($params);
    $postsInfo = execute_get_request($url . $url_params);
    foreach($postsInfo->objects as $post){
        $titles[] = $post->name;
    }
    $params['state'] = 'PUBLISHED';
    $url_parms2 = params_to_string($params);
    $postsInfo2 = execute_get_request($url . $url_parms2);
    foreach($postsInfo2->objects as $post){
        $titles[] = $post->name;
    }
    $params['state'] = 'SCHEDULED';
    $url_parms3 = params_to_string($params);
    $postsInfo3 = execute_get_request($url . $url_parms3);
    foreach($postsInfo3->objects as $post){
        $titles[] = $post->name;
    }
    return $titles;
}

function compare_post_titles($needle,$haystack){
    //accepts string of title and array of titles (strings)
    if(DEBUG){
        echo '<pre>';var_dump($haystack);echo '</pre>';
    }
    $match = false;
    //echo "needle " . $needle . "<br/>";
    foreach($haystack as $hay){
        //echo "hay: " . $hay . "<br/>";
        if($needle == $hay) $match=true;
    }

    return $match;
}

function list_authors(){
    $url = 'https://api.hubapi.com/blogs/v3/blog-authors?hapikey=' . hub_apiKey . '&casing=snake_case';

    $authorsInfo = execute_get_request($url);

    echo "blog author names and id's:<br/>";

    foreach ($authorsInfo->objects as $author){
        echo $author->full_name . ':  ' . $author->id;
    }
}

function list_topics(){
    $url = 'https://api.hubapi.com/blogs/v3/topics?hapikey=' . hub_apiKey . '&casing=snake_case' . '&limit=9999';

    $topicsInfo = execute_get_request($url);

    //echo "blog topics names and id's:<br/>";

    $topic_array = array();

    foreach ($topicsInfo->objects as $topic){
        //echo $topic->name . ':  ' . $topic->id . '<br/>';
        //Convert ID to string to avoid max int issue with large topic IDs
        $topic_id_str = strval($topic->id);
        $topic_array[$topic_id_str]=strtolower($topic->name);
    }
    if(DEBUG){
        $show_topics = $topic_array;
        echo '<pre>';
        var_dump($show_topics);
        echo '<pre>';
    }
    return $topic_array;
}

function compare_topics($needle, $haystack){

    $match = false;

    foreach ($haystack as $hay){
        if(DEBUG){
            echo "Compare $hay with $needle <br/>";
        }
        if($hay == $needle) $match = key($haystack);
    }
    return $match;
}

function create_topic($topic,$existing_topics){
    //global $existing_topics;

    $url = 'https://api.hubapi.com/blogs/v3/topics?hapikey=' . hub_apiKey . '&casing=snake_case';
    $params = array(
        'name' => $topic,
         'slug'  => str_replace('--','-',strtolower(preg_replace('/[^A-Za-z0-9\-]/','',str_replace(' ','-',$topic)))),
    );
    if(DEBUG){
        $check_params = $params;
        echo '<pre>';
        var_dump($check_params);
        echo '</pre>';
    }
    $json = json_encode($params);

    $response = execute_post_request($url, $json);
    if(DEBUG){
        $print_response = $response;
        echo '<pre>';
        var_dump($print_response);
        echo '</pre>';
    }
    $existing_topics[$response->id] = $topic;

    $return = array(
        '0'=>$response,
        '1'=>$existing_topics,
    );

    return $return;
}


function params_to_string($params){
    $url_params = "?";
    foreach(array_keys($params) as $key){
        $url_params .= $key . '=' . $params[$key] . '&';
    }
    return $url_params;
}




function execute_get_request($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, "haPiHP default UserAgent");  // new

    $output = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    curl_close($ch);
    if ( $errno > 0) {
        throw new Exception('cURL error: ' + $error);
    } else {
        return json_decode($output);
    }
}



function execute_post_request($url, $body, $formenc=FALSE) {  //new

    // intialize cURL and send POST data
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, "haPiHP default UserAgent");  // new
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
        'Content-Type: application/json')                                                                       
    );
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

   // if ($formenc)   // new
   //     curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded')); // new

    $output = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    curl_close($ch); 
    if ($errno > 0) {
        return 'cURL error: ' . $error;
    } else {
        return json_decode($output);
    }
}

function execute_put_request($url, $body, $formenc=FALSE) {  //new

    // intialize cURL and send POST data
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, "haPiHP default UserAgent");  // new
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
        'Content-Type: application/json')                                                                       
    );
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

   // if ($formenc)   // new
   //     curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded')); // new

    $output = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    curl_close($ch); 
    if ($errno > 0) {
        echo 'cURL error: ' . $error;
    } else {
        return json_decode($output);
    }
}

/* video updates*/
function generate_source_tag($src, $resolution)
{
    $tag = ''; 
    $ext = pathinfo($src, PATHINFO_EXTENSION); 

    return sprintf('<source src="%s" type="video/%s" data-resolution="%s" />', $src, $ext, $resolution );
}
?>