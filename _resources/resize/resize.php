<?php
require_once('phpthumb/ThumbLib.inc.php');
$fileName = (isset($_GET['file'])) ? urldecode($_GET['file']) : null;
if (!file_exists($fileName))
{
    
}
try
{
     $thumb = PhpThumbFactory::create($fileName);
}
catch (Exception $e)
{
    // handle error here however you'd like
}
$thumb->adaptiveResize(200, 200);
$thumb->show();
?>