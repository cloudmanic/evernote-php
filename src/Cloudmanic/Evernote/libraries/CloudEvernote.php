<?php

use EDAM\UserStore\UserStoreClient;
use EDAM\NoteStore\NoteStoreClient;
use EDAM\Types\Data, EDAM\Types\Note, EDAM\Types\Notebook, EDAM\NoteStore\NoteFilter, 
		EDAM\Types\Resource, EDAM\Types\ResourceAttributes;
use EDAM\Error\EDAMUserException, EDAM\Error\EDAMErrorCode;

require_once("lib-evernote/Thrift.php");
require_once("lib-evernote/transport/TTransport.php");
require_once("lib-evernote/transport/THttpClient.php");
require_once("lib-evernote/protocol/TProtocol.php");
require_once("lib-evernote/protocol/TBinaryProtocol.php");
require_once("lib-evernote/packages/Errors/Errors_types.php");
require_once("lib-evernote/packages/Types/Types_types.php");
require_once("lib-evernote/packages/UserStore/UserStore.php");
require_once("lib-evernote/packages/UserStore/UserStore_constants.php");
require_once("lib-evernote/packages/NoteStore/NoteStore.php");
require_once("lib-evernote/packages/Limits/Limits_constants.php");

class CloudEvernote
{
	private $_access_token = '';
	private $_evernote_host = '';
	private $_note_store = '';
	private $_port = '';
	private $_proto = '';
	private $_error = '';
	private $_files = array();

	//
	// Client init. We need to run this after calling set_access_token and set_evernote_host
	// 
	function client_init()
	{
		// Settup the http client.
		$userStoreHttpClient = new THttpClient($this->_evernote_host, $this->_port, '/edam/user', $this->_proto);
		$userStoreProtocol = new TBinaryProtocol($userStoreHttpClient);
		$userStore = new UserStoreClient($userStoreProtocol, $userStoreProtocol);
		
		// Get the URL used to interact with the contents of the user's account
		// When your application authenticates using OAuth, the NoteStore URL will
		// be returned along with the auth token in the final OAuth request.
		// In that case, you don't need to make this call.
		$noteStoreUrl = $userStore->getNoteStoreUrl($this->_access_token);
		
		$parts = parse_url($noteStoreUrl);
		$noteStoreHttpClient = new THttpClient($parts['host'], $this->_port, $parts['path'], $this->_proto);
		$noteStoreProtocol = new TBinaryProtocol($noteStoreHttpClient);
		$this->_note_store = new NoteStoreClient($noteStoreProtocol, $noteStoreProtocol);
	}

	//
	// Set access token.
	//
	function set_access_token($access_token)
	{
		$this->_access_token = $access_token;
	}

	//
	// Set evernote host.
	//
	function set_evernote_host($host, $port = '443', $proto = 'https')
	{
		$this->_evernote_host = $host;
		$this->_port = $port;
		$this->_proto = $proto;
	}

	//
	// Clear all data after doing an action.
	//
	function clear()
	{
		$this->_files = array();
	}

	// ---------------------- Notebooks ------------------------ //
	
	//
	// Returns all the notes in a particular notebook.
	// We pass in a notebook GUID.
	//
	function get_notes_by_notebook($guid, $search, $offset, $count)
	{
		$data = array();
		$filter = new NoteFilter(array('notebookGuid' => $guid, 'words' => $search));
		$notes = $this->_note_store->findNotes($this->_access_token, $filter, $offset, $count);

		foreach($notes->notes AS $key => $row)
		{
			$data[] = $this->_note_clean($row);
		}
		
		return array('notes' => $data, 'start' => $notes->startIndex, 'count' => $notes->totalNotes);
	}
	
	//
	// Create a notebook. Returns the notebook GUID.
	//
	function new_notebook($name, $stack = '')
	{
		// First we make sure we do not already have this notebook.
		$books = $this->get_notebooks();
		foreach($books AS $key => $row)
		{
			if(strtoupper(trim($row['name'])) == strtoupper(trim($name)))
			{
				return $row['guid'];
			}
		}

		// Add the notebook.	
		$nb = new Notebook(array('name' => trim($name), 'stack' => trim($stack)));
	
		$rt = $this->_note_store->createNotebook($this->_access_token, $nb);

		return $rt->guid;
	}
	
	//
	// Return a list of all notebooks.
	//
	function get_notebooks()
	{
		$data = array();
		$notebooks = $this->_note_store->listNotebooks($this->_access_token);
		
		foreach($notebooks AS $key => $row)
		{
			$data[] = array('guid' => $row->guid, 'name' => $row->name, 'stack' => $row->stack);
		}
		
		return $data;
	}
	
	//
	// Return a list of all tags.
	//
	function get_tags()
	{
		$data = array();
		$notebooks = $this->_note_store->listTags($this->_access_token);
		
		foreach($notebooks AS $key => $row)
		{
			$data[] = array('guid' => $row->guid, 'name' => $row->name);
		}
		
		return $data;
	}
	
	// ---------------------- Notes ---------------------------- //

	//
	// Return just one note by GUID.
	//
	function get_note($guid)
	{
		$note = $this->_note_store->getNote($this->_access_token, $guid, true, true, false, false);
		return $this->_note_clean($note);
	}

	//
	// Create a new note. Returns new notes GUID
	//
	function new_note($title, $content, $notebook = NULL, $body_files = TRUE)
	{
		// To create a new note, simply create a new Note object and fill in 
		// attributes such as the note's title.
		$note = new Note();
		$note->title = $title;
		
		if(! is_null($notebook))
		{
			$note->notebookGuid = $notebook;
		}
		
		// Add file resources if we have any.
		if(count($this->_files) > 0)
		{
			$note->resources = $this->_files;
			
			if($body_files)
			{
				foreach($this->_files AS $key => $row)
				{
					$content .= '<en-media type="' . $row->mime . '" hash="' . $row->attributes->hash . '"/>';
				}
			}
		}
		
		// The content of an Evernote note is represented using Evernote Markup Language
		// (ENML). The full ENML specification can be found in the Evernote API Overview
		// at http://dev.evernote.com/documentation/cloud/chapters/ENML.php
		$note->content =
		  '<?xml version="1.0" encoding="UTF-8"?>' .
		  '<!DOCTYPE en-note SYSTEM "http://xml.evernote.com/pub/enml2.dtd">' .
		  '<en-note>' . $content . '</en-note>';
		
		// When note titles are user-generated, it's important to validate them
		$len = strlen($note->title);
		$min = $GLOBALS['EDAM_Limits_Limits_CONSTANTS']['EDAM_NOTE_TITLE_LEN_MIN'];
		$max = $GLOBALS['EDAM_Limits_Limits_CONSTANTS']['EDAM_NOTE_TITLE_LEN_MAX'];
		$pattern = '#' . $GLOBALS['EDAM_Limits_Limits_CONSTANTS']['EDAM_NOTE_TITLE_REGEX'] . '#'; // Add PCRE delimiters
		if($len < $min || $len > $max || !preg_match($pattern, $note->title)) 
		{
			$this->_error = "\nInvalid note title: " . $note->title . '\n\n';
			return FALSE;
		}
		
		// Finally, send the new note to Evernote using the createNote method
		// The new Note object that is returned will contain server-generated
		// attributes such as the new note's unique GUID.
		$createdNote = $this->_note_store->createNote($this->_access_token, $note);

		$this->clear();
		return $createdNote->guid;
	}
	
	//
	// Set a file to be uploaded as a resource to a note.
	// Returns an array with data about the file.
	//
	function add_file($path)
	{
		// To include an attachment such as an image in a note, first create a Resource
		// for the attachment. At a minimum, the Resource contains the binary attachment 
		// data, an MD5 hash of the binary data, and the attachment MIME type. It can also 
		// include attributes such as filename and location.
		$file = fread(fopen($path, "rb"), filesize($path));
		$hash = md5($file, 1);
		
		$data = new Data();
		$data->size = strlen($file);
		$data->bodyHash = $hash;
		$data->body = $file;
		
		$resource = new Resource();
		$resource->mime = mime_content_type($path);
		$resource->data = $data;
		$resource->attributes = new ResourceAttributes();
		$resource->attributes->fileName = basename($path);
		$resource->attributes->hash = md5($file, 0);
		
		// Now, add the new Resource to the note's list of resources
		$this->_files[] = $resource;
		
		return array('hash' => md5($file, 0), 'mime' => $resource->mime, 'name' => $resource->attributes->fileName, 'size' => filesize($path));
	}
	
	// ---------------------------- Private Functions -------------------------- //
	
	//
	// Note Clean - We do not like the evernote library objects so we clean
	// them up to be more like what we want.
	//
	private function _note_clean($note)
	{
		$data = array();
		
		// Clean up the content.
		if(! empty($note->content))
		{
			$content = explode('<en-note>', $note->content);
		} else
		{
			$content[1] = '';
		}
		
		$data['guid'] = $note->guid;
		$data['title'] = $note->title;
		$data['content'] = str_ireplace('</en-note>', '', $content[1]);
		
		return $data;
	}
}