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
	private static $_tags = array();
	private static $_attributes = null;

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
	// Get a note.
	//
	public static function get_note($guid, $content = true, $resources = true)
	{
		// Try and get the note.
    try {
			$noteStore = self::$_client->getNoteStore();
			$note = $noteStore->getNote(self::$_access_token, $guid, $content, $resources, false, false);			
			return self::_note_clean($note);
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
		$note->title = trim($title);
		
		// Add Atributes
		if(! is_null(self::$_attributes))
		{
			$note->attributes = self::$_attributes;
		}	
		
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
		
		// Add tags.
		if(count(self::$_tags) > 0)
		{
			$note->tagNames = self::$_tags;
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
    self::$_tags = array();
    self::_clear_error();
    
    return $createdNote->guid;
	}
	
	//
	// Update a note by GUID, must have full api access.
	//
	public static function update_note($guid, $title, $body, $notebook = null, $body_files = true)
	{
    // Make sure the title is not blank.
    if(empty($title))
    {
	    self::$_error = 'Title can not be blank';
	    return false;
    }
	
    // Build Note object.
		$note = new \EDAM\Types\Note();
	
		// Set the Guid.
		$note->guid = $guid;
	
		// Title
		$note->title = trim($title);
		
		// Add Atributes
		if(! is_null(self::$_attributes))
		{
			$note->attributes = self::$_attributes;
		}	
		
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
		
		// Add tags.
		if(count(self::$_tags) > 0)
		{
			$note->tagNames = self::$_tags;
		}
		
		// Content
		$note->content =
    '<?xml version="1.0" encoding="UTF-8"?>' .
    '<!DOCTYPE en-note SYSTEM "http://xml.evernote.com/pub/enml2.dtd">' .
    '<en-note>' . $body . '</en-note>';
        
    // Create the note.
    try {
	    $noteStore = self::$_client->getNoteStore();
    	$createdNote = $noteStore->updateNote(self::$_access_token, $note);
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
    self::$_tags = array();
    self::_clear_error();
    
    return $createdNote->guid;
	}
	
	//
	// Update a note's notebook. For some reason the title always has to be passed in.
	//
	public static function update_note_notebook($note_guid, $notebook, $title)
	{
    // Build Note object.
		$note = new \EDAM\Types\Note();

    // Make sure the title is not blank.
    if(empty($title))
    {
	    self::$_error = 'Title can not be blank';
	    return false;
    }
	
    // Build Note object.
		$note = new \EDAM\Types\Note();
	
		// Title
		$note->title = trim($title);

		// Set noteid.
		$note->guid = $note_guid;

		// Set notebook.
		$note->notebookGuid = $notebook;
	
    // Update the note.
    try {
	    $noteStore = self::$_client->getNoteStore();
			$noteStore->updateNote(self::$_access_token, $note);
			return true;
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
	// Add attributes to a note.
	//
	public static function add_attributes($attrs)
	{
		if(! is_array($attrs))
		{
			return false;
		}
		
		self::$_attributes = new \EDAM\Types\NoteAttributes();
	
		foreach($attrs AS $key => $row)
		{
			self::$_attributes->{$key} = $row;
		}
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
	// Get a notebook by id.
	//
	public static function get_notebook($guid)
	{
		$data = array();
		
		try {
			$noteStore = self::$_client->getNoteStore();
			$notebook = $noteStore->getNotebook(self::$_access_token, $guid);	
			
			// Set data.
			$data = array(
			  'guid' => $notebook->guid, 
			  'name' => $notebook->name, 
			  'stack' => $notebook->stack, 
			  'updateSequenceNum' => $notebook->updateSequenceNum
			);

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
  
	//
	// Update a notebook title
	//
	public static function update_notebook_title($guid, $name, $stack = null)
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
			$nb = new \EDAM\Types\Notebook(array('name' => trim($name), 'stack' => $stack, 'guid' => $guid));
			$noteStore = self::$_client->getNoteStore();
			$noteStore->updateNotebook(self::$_access_token, $nb);
			self::_clear_error();
			return $guid;
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
	
	// -------------- Tags ------------------ //
	
	//
	// Add tags.
	//
	public static function add_tag($name)
	{
		// Get or create the GUID for the tag.
		$guid = self::new_tag(trim($name));
		self::$_tags[] = trim($name);
	}
	
	//
	// Return a list of all tags.
	//
	public static function get_tags()
	{
		$data = array();
		
		try {
			$noteStore = self::$_client->getNoteStore();
			$tags = $noteStore->listTags();	
			
			// Loop through the notebooks and formate the data.
			foreach($tags AS $key => $row)
			{
				$data[] = array(
					'guid' => $row->guid, 
					'name' => $row->name,
					'updateSequenceNum' => $row->updateSequenceNum,
					'parentGuid' => $row->parentGuid
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
	// Create a tag. Returns the tag GUID.
	//
	public static function new_tag($name)
	{
		// First we make sure we do not already have this notebook.
		if(! $tags = self::get_tags())
		{
			return false;
		}
		
		// Loop through the notebooks.
		foreach($tags AS $key => $row)
		{		
			if(strtoupper(trim($row['name'])) == strtoupper(trim($name)))
			{
				return $row['guid'];
			}
		}

		// Add the notebook.	
		try {
			$tag = new \EDAM\Types\Tag(array('name' => trim($name)));
			$noteStore = self::$_client->getNoteStore();
			$rt = $noteStore->createTag(self::$_access_token, $tag);
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
	// Note Clean - We do not like the evernote library objects so we clean
	// them up to be more like what we want.
	//
	private static function _note_clean($note)
	{		
		$data = array( 'files' => array() );
		
		// Clean up the content.
		if(! empty($note->content))
		{
			$content = explode('<en-note>', $note->content);
		} else
		{
			$content[1] = '';
		}
		
		// Deal with resources.
		if(isset($note->resources) && is_array($note->resources))
		{
			foreach($note->resources AS $key => $row)
			{
				if(isset($row->attributes->fileName) && isset($row->data->body) &&
						(! empty($row->data->body) && (! empty($row->attributes->fileName))))
				{
					// Make sure we have an evernote dir.
					if(! is_dir('/tmp/evernote/'))
					{
						mkdir('/tmp/evernote/');
					}
				
					$path = '/tmp/evernote/' . str_ireplace('/', '-', $row->attributes->fileName);
					file_put_contents($path, $row->data->body);
					$data['files'][] = $path;
				}
			}
		}
		
		$data['active'] = $note->active;
		$data['notebookGuid'] = $note->notebookGuid;
		$data['guid'] = $note->guid;
		$data['title'] = $note->title;
		
		if(isset($content[1]))
		{
			$data['content'] = str_ireplace('</en-note>', '', $content[1]);
		} else
		{
			$data['content'] = '';
		}
		
		return $data;
	}
	
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
		if(isset($e->errorCode) && isset(\EDAM\Error\EDAMErrorCode::$__names[$e->errorCode])) 
		{
		  self::$_error_code = (isset($e->errorCode)) ? $e->errorCode : '';
		  self::$_error_parameter = (isset($e->parameter)) ? $e->parameter : '';
		  self::$_error = (isset(\EDAM\Error\EDAMErrorCode::$__names[$e->errorCode])) ? \EDAM\Error\EDAMErrorCode::$__names[$e->errorCode] : '';
		} else 
		{
		  self::$_error_code = $e->getCode();
		  self::$_error = $e->getMessage();;
		}
	}
}

/* End File */