<?php
require_once 'vendor/autoload.php';

use Youtube\Model\ItagInfoModel;

$id = 33;
$data = new ItagInfoModel($id);

var_dump($data);
