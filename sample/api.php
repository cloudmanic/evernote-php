<?php 
ini_set('display_errors', 1);
error_reporting(E_ALL);

include 'vendor/autoload.php';

// Setup parms.
$newline = "\n";
$sandbox = true;
$access_token = 'Your API Key Here';


// Set access token
Cloudmanic\Evernote\Api::set_access_token($access_token, $sandbox);

// Get the user attached to this access token.
if(! $user = Cloudmanic\Evernote\Api::get_user())
{
	echo Cloudmanic\Evernote\Api::get_error_string() . $newline;
} else
{
	echo '<pre>' . print_r($user, TRUE) . '</pre>' . $newline;
}

// Get all the notebooks that are part of the account.
if(! $notebooks = Cloudmanic\Evernote\Api::get_notebooks())
{
	echo Cloudmanic\Evernote\Api::get_error_string() . $newline;
} else
{	
	echo '<pre>' . print_r($notebooks, TRUE) . '</pre>' . $newline;
}

// Create a new notebook (or get the GUID if the notebook already exists.
if(! $notebook_guid = Cloudmanic\Evernote\Api::new_notebook('My First Notebook'))
{
	echo Cloudmanic\Evernote\Api::get_error_string() . $newline;
} else
{
	echo $notebook_guid . $newline;
}
	
// Create a new note in the notebook we created above.
//Cloudmanic\Evernote\Api::add_file('/Users/spicer/Desktop/test.jpg');
if(! $note_guid = Cloudmanic\Evernote\Api::new_note('A Note From Cloudmanic Labs', 'Hello World, From Cloudmanic Labs', $notebook_guid))
{
	echo Cloudmanic\Evernote\Api::get_error_string() . $newline;
} else
{
	echo $note_guid . $newline;
}
	
/* End File */