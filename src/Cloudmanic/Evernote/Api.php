<?php
//
// Company: Cloudmanic Labs, LLC
// By: Spicer Matthews 
// Email: spicer@cloudmanic.com
// Website: http://cloudmanic.com
// Date: 3/7/2013
//

namespace Cloudmanic\Evernote;

class Api
{
	private static $_access_token = null;
	private static $_client = null;
	private static $_error = '';
	private static $_error_code = '';
	private static $_error_parameter = '';
	private static $_files = array();

	//
	// Set access token.
	//
	public static function set_access_token($token, $sandbox = false)
	{
		self::$_access_token = $token;
		self::$_client = new \Evernote\Client(array('token' => self::$_access_token, 'sandbox' => $sandbox));
	}

	// -------------- Error ------------------ //	
	
	//
	// Return the error string.
	//
	public static function get_error_string()
	{
		return self::$_error . ':' . self::$_error_code . ':' . self::$_error_parameter;  
	}
	
	//
	// Return the error message.
	//
	public static function get_error()
	{
		return self::$_error;
	}
	
	//
	// Return the error code.
	//
	public static function get_error_code()
	{
		return self::$_error_code;
	}
	
	//
	// Return the error parameter.
	//
	public static function get_error_parameter()
	{
		return self::$_error_parameter;
	}
	
	// -------------- User Store ------------------ //
	
	//
	// Get the user data.
	//
	public static function get_user()
	{
		try {
		  $data = array();
		  $userStore = self::$_client->getUserStore();
		 
		  // Grab the user and convert it to an array.
		  foreach($userStore->getUser() AS $key => $row)
		  {
			  if($key == 'accounting')
			  {
				  $data[$key] = array();
				  foreach($row AS $key2 => $row2)
				  {
					  $data[$key][$key2] = $row2;
				  }
			  } else
			  {
				  $data[$key] = $row;
				}
		  }
		  
		  self::_clear_error();
		  return $data;
		} 
		
		catch(\EDAM\Error\EDAMSystemException $e) 
		{
	    self::_exception_error($e);
		  return false;
    } 
    
    catch(\EDAM\Error\EDAMUserException $e) {
	    self::_exception_error($e);
		  return false;
    } 
    
    catch(\EDAM\Error\EDAMNotFoundException $e) {
	    self::_exception_error($e);
		  return false;
    } 
    
    catch(Exception $e) {
	    self::_exception_error($e);
	    return false;
    }	
	}

	// -------------- Notes ---------------------- //

	//
	// Create a new note. Returns new notes GUID
	//
	public static function new_note($title, $body, $notebook = null, $body_files = true)
	{
    // Make sure the title is not blank.
    if(empty($title))
    {
	    self::$_error = 'Title can not be blank';
	    return false;
    }
	
    // Build Note object.
		$note = new \EDAM\Types\Note();
	
		// Title
		$note->title = $title;
		
		// Notebook
		if(! is_null($notebook))
		{
			$note->notebookGuid = $notebook;
		}
		
		// Add file resources if we have any.
		if(count(self::$_files) > 0)
		{
			$note->resources = self::$_files;
			
			if($body_files)
			{
				foreach(self::$_files AS $key => $row)
				{
					$body .= '<en-media type="' . $row->mime . '" hash="' . $row->attributes->hash . '"/>';
				}
			}
		}
		
		// Content
		$note->content =
    '<?xml version="1.0" encoding="UTF-8"?>' .
    '<!DOCTYPE en-note SYSTEM "http://xml.evernote.com/pub/enml2.dtd">' .
    '<en-note>' . $body . '</en-note>';
        
    // Create the note.
    try {
	    $noteStore = self::$_client->getNoteStore();
    	$createdNote = $noteStore->createNote($note);
    }
    
		catch(\EDAM\Error\EDAMSystemException $e) 
		{
	    self::_exception_error($e);
		  return false;
    } 
    
    catch(\EDAM\Error\EDAMUserException $e) {
	    self::_exception_error($e);
		  return false;
    } 
    
    catch(\EDAM\Error\EDAMNotFoundException $e) {
	    self::_exception_error($e);
		  return false;
    } 
    
    catch(Exception $e) {
	    self::_exception_error($e);
	    return false;
    }	
    
    // Clear stuff.
    self::$_files = array();
    self::_clear_error();
    
    return $createdNote->guid;
	}

	//
	// Set a file to be uploaded as a resource to a note.
	// Returns an array with data about the file.
	//
	public static function add_file($path)
	{
		// To include an attachment such as an image in a note, first create a Resource
		// for the attachment. At a minimum, the Resource contains the binary attachment 
		// data, an MD5 hash of the binary data, and the attachment MIME type. It can also 
		// include attributes such as filename and location.
		$file = fread(fopen($path, "rb"), filesize($path));
		$hash = md5($file, 1);
		
		$data = new \EDAM\Types\Data();
		$data->size = strlen($file);
		$data->bodyHash = $hash;
		$data->body = $file;
		
		$resource = new \EDAM\Types\Resource();
		$resource->mime = mime_content_type($path);
		$resource->data = $data;
		$resource->attributes = new \EDAM\Types\ResourceAttributes();
		$resource->attributes->fileName = basename($path);
		$resource->attributes->hash = md5($file, 0);
		
		// Now, add the new Resource to the note's list of resources
		self::$_files[] = $resource;
		
		return array('hash' => md5($file, 0), 'mime' => $resource->mime, 'name' => $resource->attributes->fileName, 'size' => filesize($path));
	}

	// -------------- Notebooks ------------------ //
	
	// 
	// Get all note books.
	//
	public static function get_notebooks()
	{
		$data = array();
		
		try {
			$noteStore = self::$_client->getNoteStore();
			$notebooks = $noteStore->listNotebooks();	
			
			// Loop through the notebooks and formate the data.
			foreach($notebooks AS $key => $row)
			{
			  $data[] = array(
			  	'guid' => $row->guid, 
			  	'name' => $row->name, 
			  	'stack' => $row->stack, 
			  	'updateSequenceNum' => $row->updateSequenceNum
			  );
			}
			
			self::_clear_error();
			return $data;
		} 
		
		catch(\EDAM\Error\EDAMSystemException $e) 
		{
	    self::_exception_error($e);
		  return false;
    } 
    
    catch(\EDAM\Error\EDAMUserException $e) {
	    self::_exception_error($e);
		  return false;
    } 
    
    catch(\EDAM\Error\EDAMNotFoundException $e) {
	    self::_exception_error($e);
		  return false;
    } 
    
    catch(Exception $e) {
	    self::_exception_error($e);
	    return false;
    }
	}
	
	//
	// Create a notebook. Returns the notebook GUID.
	//
	public static function new_notebook($name, $stack = null)
	{
		// First we make sure we do not already have this notebook.
		if(! $books = self::get_notebooks())
		{
			return false;
		}
		
		// Loop through the notebooks.
		foreach($books AS $key => $row)
		{		
			if(strtoupper(trim($row['name'])) == strtoupper(trim($name)))
			{
				return $row['guid'];
			}
		}

		// Add the notebook.	
		try {
			$nb = new \EDAM\Types\Notebook(array('name' => trim($name), 'stack' => $stack));
			$noteStore = self::$_client->getNoteStore();
			$rt = $noteStore->createNotebook(self::$_access_token, $nb);
			self::_clear_error();
			return $rt->guid;
		} 		
		
		catch(\EDAM\Error\EDAMSystemException $e) 
		{
	    self::_exception_error($e);
		  return false;
    } 
    
    catch(\EDAM\Error\EDAMUserException $e) {
	    self::_exception_error($e);
		  return false;
    } 
    
    catch(\EDAM\Error\EDAMNotFoundException $e) {
	    self::_exception_error($e);
		  return false;
    } 
    
    catch(Exception $e) {
	    self::_exception_error($e);
	    return false;
    }	
  }
	
	// ------------- Private helper function ---------------- //
	
	//
	// Clear error.
	//
	private static function _clear_error()
	{
		self::$_error_code = '';
		self::$_error_parameter = '';
		self::$_error = '';
	}
	
	//
	// Deal with exception error.
	//
	private static function _exception_error($e)
	{
		if(isset(\EDAM\Error\EDAMErrorCode::$__names[$e->errorCode])) 
		{
		  self::$_error_code = $e->errorCode;
		  self::$_error_parameter = $e->parameter;
		  self::$_error = \EDAM\Error\EDAMErrorCode::$__names[$e->errorCode];
		} else 
		{
		  self::$_error_code = $e->getCode();
		  self::$_error = $e->getMessage();;
		}
	}
}

/* End File */