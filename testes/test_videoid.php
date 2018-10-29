<?php
require_once 'vendor/autoload.php';

use Youtube\YoutubeDownloader;

$url = 'https://www.youtube.com/watch?v=jNQXAC9IVRw';

$yt = new YoutubeDownloader($url);
$id = $yt->getVideoId($url);

var_dump($id);
