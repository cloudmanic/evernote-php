<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

include_once('libraries/Evernote.php');

$notebook = $argv[1];
$file = $argv[2];

$access_token = 'S=s1:U=1c900:E=13837101358:C=13831e9b758:P=185:A=cloudmanic:H=d22166db58ae8eea4bbb144406ba82f1';
$evernoteHost = "sandbox.evernote.com";
$evernote = new Evernote($access_token, $evernoteHost, '80', 'http');

/*
$backup = new Backup();
$notebook = $backup->add_profile($profile);
$backup->backup_file($notebook, $profile, $file);
*/