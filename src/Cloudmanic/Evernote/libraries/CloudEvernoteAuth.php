<?php

require_once("lib-evernote/Thrift.php");
require_once("lib-evernote/transport/TTransport.php");
require_once("lib-evernote/transport/THttpClient.php");
require_once("lib-evernote/protocol/TProtocol.php");
require_once("lib-evernote/protocol/TBinaryProtocol.php");
require_once("lib-evernote/packages/Types/Types_types.php");
require_once("lib-evernote/packages/UserStore/UserStore.php");
require_once("lib-evernote/packages/NoteStore/NoteStore.php");

// Import the classes that we're going to be using
use EDAM\NoteStore\NoteStoreClient;
use EDAM\Error\EDAMSystemException, EDAM\Error\EDAMUserException, EDAM\Error\EDAMErrorCode;

// Verify that you successfully installed the PHP OAuth Extension
if(! class_exists('OAuth')) 
{
  die('The PHP OAuth Extension is not installed');
}

class CloudEvernoteAuth
{
	private $_currentStatus = '';
	private $_lastError = '';
	
	//
	// Construct.
	//
	function __construct()
	{
		$this->load->library('session');
		$this->load->config('cloudevernote');
	}
	
	//
	// Manage the entire login process. Function returns the users data
	// uppon succcess. We also pass in an error url to redirect the user to if we fail.
	//
	function login($success, $fail)
	{
		// Callback from the login at evernote.
		if($this->input->get('oauth_verifier'))
		{
			if($this->cloudevernoteauth->handle_callback())
			{
				redirect($success);
			} else
			{
				redirect($fail);
			}
		}
		
		if(! $this->session->userdata('requestToken'))
		{
			$this->cloudevernoteauth->get_temporary_credentials();
			redirect(current_url());
		} else if($this->session->userdata('requestToken') && (! $this->session->userdata('oauthVerifier')))
		{
			$url = $this->cloudevernoteauth->get_authorization_url();	
			redirect($url);
		} else if($this->session->userdata('oauthVerifier'))
		{
			if($data = $this->cloudevernoteauth->get_token_credentials())
			{
				return $data;
			} else
			{
				redirect($fail);
			}
		}
	}
	
	//
	// The first step of OAuth authentication: the client (this application) 
	// obtains temporary credentials from the server (Evernote). 
	// After successfully completing this step, the client has obtained the
	// temporary credentials identifier, an opaque string that is only meaningful 
	// to the server, and the temporary credentials secret, which is used in 
	// signing the token credentials request in step 3.
	// This step is defined in RFC 5849 section 2.1:
	// http://tools.ietf.org/html/rfc5849#section-2.1
	// @return boolean TRUE on success, FALSE on failure
	//
	function get_temporary_credentials() 
  {
    try {
      $oauth = new OAuth($this->config->item('ev_oauth_consumer_key'), 
      										$this->config->item('ev_oauth_consumer_secret'));
      $requestTokenInfo = $oauth->getRequestToken($this->config->item('ev_request_token_url'), current_url());
      
      if($requestTokenInfo) 
      {
        $this->session->set_userdata('requestToken', $requestTokenInfo['oauth_token']);
        $this->session->set_userdata('requestTokenSecret', $requestTokenInfo['oauth_token_secret']);
        $this->_currentStatus = 'Obtained temporary credentials';
        return TRUE;
      } else 
      {
        $this->_lastError = 'Failed to obtain temporary credentials: ' . $oauth->getLastResponse();
      }
    } catch (OAuthException $e) {
    	//echo '<pre>' . print_r($e, TRUE) . '</pre>';
      $this->_lastError = 'Error obtaining temporary credentials: ' . $e->getMessage();
    }
    
    return FALSE;
  }
  
  //
  // Get the Evernote server URL used to authorize unauthorized temporary credentials.
  //
  function get_authorization_url() 
  {
	  $url = $this->config->item('ev_authorization_url');
	  $url .= '?oauth_token=';
	  $url .= urlencode($this->session->userdata('requestToken'));
	  return $url;
  } 
  
  //
  // The completion of the second step in OAuth authentication: the resource owner 
  // authorizes access to their account and the server (Evernote) redirects them 
  // back to the client (this application).
  // 
  // After successfully completing this step, the client has obtained the
  // verification code that is passed to the server in step 3.
  //
  // This step is defined in RFC 5849 section 2.2:
  // http://tools.ietf.org/html/rfc5849#section-2.2
  //
  // @return boolean TRUE if the user authorized access, FALSE if they declined access.
  //
  function handle_callback() 
  {
    if(isset($_GET['oauth_verifier'])) 
    {
      $this->session->set_userdata('oauthVerifier', $_GET['oauth_verifier']);
      $this->_currentStatus = 'Content owner authorized the temporary credentials';
      return TRUE;
    } else 
    {
      // If the User clicks "decline" instead of "authorize", no verification code is sent
      $this->_lastError = 'Content owner did not authorize the temporary credentials';
      return FALSE;
    }
  }
	
  //
  // The third and final step in OAuth authentication: the client (this application)
  // exchanges the authorized temporary credentials for token credentials.
  //
  // After successfully completing this step, the client has obtained the
  // token credentials that are used to authenticate to the Evernote API.
  // In this sample application, we simply store these credentials in the user's
  // session. A real application would typically persist them.
  //
  // This step is defined in RFC 5849 section 2.3:
  // http://tools.ietf.org/html/rfc5849#section-2.3
  //
  // @return boolean TRUE on success, FALSE on failure
  //
  function get_token_credentials() 
  {    
    if($this->session->userdata('accessToken')) 
    {
      $this->_lastError = 'Temporary credentials may only be exchanged for token credentials once';
      return FALSE;
    }
    
    try {
      $oauth = new OAuth($this->config->item('ev_oauth_consumer_key'), 
      										$this->config->item('ev_oauth_consumer_secret'));
      $oauth->setToken($this->session->userdata('requestToken'), $this->session->userdata('requestTokenSecret'));
      $accessTokenInfo = $oauth->getAccessToken($this->config->item('ev_access_token_url'), null, 
      										$this->session->userdata('oauthVerifier'));
      
      if($accessTokenInfo) 
      {
	      $data = array();
        $data['accessToken'] = $accessTokenInfo['oauth_token'];
        $data['accessTokenSecret'] = $accessTokenInfo['oauth_token_secret'];
        $data['noteStoreUrl'] = $accessTokenInfo['edam_noteStoreUrl'];
        $data['webApiUrlPrefix'] = $accessTokenInfo['edam_webApiUrlPrefix'];
       
        // The expiration date is sent as a Java timestamp - milliseconds since the Unix epoch
        $data['tokenExpires'] = (int)($accessTokenInfo['edam_expires'] / 1000);
        $data['userId'] = $accessTokenInfo['edam_userId'];
        $this->_currentStatus = 'Exchanged the authorized temporary credentials for token credentials';
        
        // We are done. Lets clear session data.
        $this->clear();
        
        return $data;
      } else 
      {
        $this->_lastError = 'Failed to obtain token credentials: ' . $oauth->getLastResponse();
      }
    } catch (OAuthException $e) {
      $this->_lastError = 'Error obtaining token credentials: ' . $e->getMessage();
    }  
    
    return FALSE;
  }
  
  //
  // Clear everything so we can start over.
  //
  function clear()
  {
	  $this->session->unset_userdata('requestToken');
	  $this->session->unset_userdata('requestTokenSecret');
	  $this->session->unset_userdata('oauthVerifier');
  }
	
	//
	// Getter.
	//
	function __get($key)
	{
		$CI =& get_instance();
		return $CI->$key;
	}
}