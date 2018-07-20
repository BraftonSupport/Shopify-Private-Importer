<?php
require_once 'RCClientLibrary/AdferoArticlesVideoExtensions/AdferoVideoClient.php';
require_once 'RCClientLibrary/AdferoArticles/AdferoClient.php';
require_once 'RCClientLibrary/AdferoPhotos/AdferoPhotoClient.php';
require_once 'SampleAPIClientLibrary/ApiHandler.php';
require_once 'general-functions.php';

spl_autoload_register(function ($class_name) {
    include 'classes/'.$class_name .'.php';
});

?>