<?php

include 'constants.php';

$crude = file_get_contents("shop-data.JSON");
$refined = json_decode($crude);

DefinedConstants::getConstants($refined);