<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

use EDAM\UserStore\UserStoreClient;
use EDAM\NoteStore\NoteStoreClient;
use EDAM\Types\Data, EDAM\Types\Note, EDAM\Types\Resource, EDAM\Types\ResourceAttributes;
use EDAM\Error\EDAMUserException, EDAM\Error\EDAMErrorCode;

require_once("libraries/lib-evernote/Thrift.php");
require_once("libraries/lib-evernote/transport/TTransport.php");
require_once("libraries/lib-evernote/transport/THttpClient.php");
require_once("libraries/lib-evernote/protocol/TProtocol.php");
require_once("libraries/lib-evernote/protocol/TBinaryProtocol.php");
require_once("libraries/lib-evernote/packages/Errors/Errors_types.php");
require_once("libraries/lib-evernote/packages/Types/Types_types.php");
require_once("libraries/lib-evernote/packages/UserStore/UserStore.php");
require_once("libraries/lib-evernote/packages/UserStore/UserStore_constants.php");
require_once("libraries/lib-evernote/packages/NoteStore/NoteStore.php");
require_once("libraries/lib-evernote/packages/Limits/Limits_constants.php");

$access_token = 'S=s1:U=1c900:E=13837101358:C=13831e9b758:P=185:A=cloudmanic:H=d22166db58ae8eea4bbb144406ba82f1';
$evernoteHost = "sandbox.evernote.com";

$userStoreHttpClient = new THttpClient($evernoteHost, '80', '/edam/user', 'http');
$userStoreProtocol = new TBinaryProtocol($userStoreHttpClient);
$userStore = new UserStoreClient($userStoreProtocol, $userStoreProtocol);

// Get the URL used to interact with the contents of the user's account
// When your application authenticates using OAuth, the NoteStore URL will
// be returned along with the auth token in the final OAuth request.
// In that case, you don't need to make this call.
$noteStoreUrl = $userStore->getNoteStoreUrl($access_token);

$parts = parse_url($noteStoreUrl);
$noteStoreHttpClient = new THttpClient($parts['host'], '80', $parts['path'], 'http');
$noteStoreProtocol = new TBinaryProtocol($noteStoreHttpClient);
$noteStore = new NoteStoreClient($noteStoreProtocol, $noteStoreProtocol);

// List all of the notebooks in the user's account        
$notebooks = $noteStore->listNotebooks($access_token);
//echo '<pre>' . print_r($notebooks, TRUE) . '</pre>';

// ------------------- Create New Note -------------------------- //

/*
// To create a new note, simply create a new Note object and fill in 
// attributes such as the note's title.
$note = new Note();
$note->title = "Test note from EDAMTest.php";

// To include an attachment such as an image in a note, first create a Resource
// for the attachment. At a minimum, the Resource contains the binary attachment 
// data, an MD5 hash of the binary data, and the attachment MIME type. It can also 
// include attributes such as filename and location.
$filename = "enlogo.png";
$image = fread(fopen($filename, "rb"), filesize($filename));
$hash = md5($image, 1);

$data = new Data();
$data->size = strlen($image);
$data->bodyHash = $hash;
$data->body = $image;

$resource = new Resource();
$resource->mime = "image/png";
$resource->data = $data;
$resource->attributes = new ResourceAttributes();
$resource->attributes->fileName = $filename;

// Now, add the new Resource to the note's list of resources
$note->resources = array( $resource );

// To display the Resource as part of the note's content, include an <en-media>
// tag in the note's ENML content. The en-media tag identifies the corresponding
// Resource using the MD5 hash.
$hashHex = md5($image, 0);

// The content of an Evernote note is represented using Evernote Markup Language
// (ENML). The full ENML specification can be found in the Evernote API Overview
// at http://dev.evernote.com/documentation/cloud/chapters/ENML.php
$note->content =
  '<?xml version="1.0" encoding="UTF-8"?>' .
  '<!DOCTYPE en-note SYSTEM "http://xml.evernote.com/pub/enml2.dtd">' .
  '<en-note>Here is the Evernote logo:<br/>' .
  '<en-media type="image/png" hash="' . $hashHex . '"/>' .
  '</en-note>';

// When note titles are user-generated, it's important to validate them
$len = strlen($note->title);
$min = $GLOBALS['EDAM_Limits_Limits_CONSTANTS']['EDAM_NOTE_TITLE_LEN_MIN'];
$max = $GLOBALS['EDAM_Limits_Limits_CONSTANTS']['EDAM_NOTE_TITLE_LEN_MAX'];
$pattern = '#' . $GLOBALS['EDAM_Limits_Limits_CONSTANTS']['EDAM_NOTE_TITLE_REGEX'] . '#'; // Add PCRE delimiters
if ($len < $min || $len > $max || !preg_match($pattern, $note->title)) {
  print "\nInvalid note title: " . $note->title . '\n\n';
  exit(1);
}

// Finally, send the new note to Evernote using the createNote method
// The new Note object that is returned will contain server-generated
// attributes such as the new note's unique GUID.
$createdNote = $noteStore->createNote($access_token, $note);

echo "Successfully created a new note with GUID: " . $createdNote->guid . "\n";
*/

// ------------------- Get A Note -------------------------- //

$note = $noteStore->getNote($access_token, 'f2104518-361c-4081-9aea-676b3d088cd0', true, true, false, false);

/*
foreach($note->resources AS $key => $row)
{
	echo $row->attributes->fileName;
}
*/

echo '<pre>' . print_r($note, TRUE) . '</pre>';