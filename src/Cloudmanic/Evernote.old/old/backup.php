<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

include_once('libraries/Evernote.php');

$profile = $argv[1];
$file = $argv[2];

$backup = new Backup();
$notebook = $backup->add_profile($profile);
$backup->backup_file($notebook, $profile, $file);

//
// Class for setting up our backup system.
//
class Backup
{
	private $_evernote;
	private $_profiles = array();
	private $_stack_name = '';
	
	//
	// Construct.
	//
	function __construct()
	{
		$access_token = 'S=s1:U=1c900:E=13837101358:C=13831e9b758:P=185:A=cloudmanic:H=d22166db58ae8eea4bbb144406ba82f1';
		$evernoteHost = "sandbox.evernote.com";
		$this->_stack_name = 'Backups';
		$this->_evernote = new Evernote($access_token, $evernoteHost, '80', 'http');
	}

	//
	// Add a profile. 
	//
	function add_profile($name)
	{
		return $this->_evernote->new_notebook(trim($name), $this->_stack_name);
	}

	//
	// Backup a file. If the file is too big we 
	// split it into smaller files and send them.
	//
	function backup_file($notebook, $profile, $file)
	{
		$hash = md5(time() . rand());
		$title = $profile . ' - ' . date('n/j/Y g:i:s a') . ' - ' . $hash;
		$data = array('profile' => $profile, 'total-parts' => '4', 'part' => '1', 'hash' => $hash, 'checksum' => md5_file($file));
		
		$content = json_encode($data);
		$this->_evernote->add_file($file);
		$guid = $this->_evernote->new_note($title, $content, $notebook);
	}
}