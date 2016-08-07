<?php
require __DIR__ . '/../vendor/autoload.php';

$config = new \FastD\Config\Config();

$config->loadPrivateConfig(__DIR__ . '/../tests/config/private.ini');

$config->load(__DIR__ . '/../tests/config/private.php');

var_dump($config->get('db_user'));