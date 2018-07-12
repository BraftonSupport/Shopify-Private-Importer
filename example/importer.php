<?php

include '../source/constants.php';
include '../source/libraries.php';

$crude = file_get_contents("shop-data.JSON");
$refined = json_decode($crude);

DefinedConstants::getConstants($refined);

echo SHOPIFY_PRIVATE_KEY;
class ShopifyImporter extends MasterImporter{

}

//$importer = new ShopifyImporter();