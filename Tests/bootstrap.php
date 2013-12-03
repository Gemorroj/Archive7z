<?php
$baseDir = dirname(__DIR__);

$loader = require __DIR__.'/../vendor/autoload.php';
$loader->add('Archive7z', array($baseDir.'/src/', $baseDir.'/Tests/'));
$loader->register();
