<?php
//
// Company: Cloudmanic Labs, LLC
// By: Spicer Matthews 
// Email: spicer@cloudmanic.com
// Website: http://cloudmanic.com
// Date: 6/7/2013
//

namespace Cloudmanic\Evernote;

use \OAuth;

// Verify that you successfully installed the PHP OAuth Extension
if(! class_exists('OAuth')) 
{
  die('The PHP OAuth Extension is not installed');
}

class Auth
{
	static private $_consumer_key = '';
	static private $_consumer_secret = '';
	static private $_return_url = '';
	static private $_sandbox = false;
	static private $_last_error = '';
	static private $_request_url = '';
	static private $_authorize_url = '';

	//
	// Set config.
	//
	public static function set_config($consumer_key, $consumer_secret, $return, $sandbox = false)
	{
		self::$_consumer_key = $consumer_key;
		self::$_consumer_secret = $consumer_secret;
		self::$_return_url = $return;
		self::$_sandbox = $sandbox;
		self::$_request_url = (self::$_sandbox) ? 'https://sandbox.evernote.com/oauth' : 'https://www.evernote.com/oauth';
		self::$_authorize_url = (self::$_sandbox) ? 'https://sandbox.evernote.com/OAuth.action' : 'https://www.evernote.com/OAuth.action';
	}

  //
  // Get the Evernote server URL used to authorize unauthorized temporary credentials.
  //
  public static function get_authorization_url($data) 
  {
	  $url = self::$_authorize_url;
	  $url .= '?oauth_token=';
	  $url .= urlencode($data['oauth_token']);
	  return $url;
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
	public static function get_temporary_credentials() 
  {  
    try {
      $oauth = new OAuth(self::$_consumer_key, self::$_consumer_secret);
      $requestTokenInfo = $oauth->getRequestToken(self::$_request_url, self::$_return_url);
      
      if($requestTokenInfo) 
      {
				return $requestTokenInfo;
      } else 
      {
        static::$_last_error = 'Failed to obtain temporary credentials: ' . $oauth->getLastResponse();
				return false;
      }
    } catch (OAuthException $e) {
      static::$_last_error = 'Error obtaining temporary credentials: ' . $e->getMessage();
      return false;
    }
    
    return false;
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
  public static function get_token_credentials($requestToken, $requestTokenSecret, $oauthVerifier) 
  {        
    try {
      $oauth = new OAuth(self::$_consumer_key, self::$_consumer_secret);
      $oauth->setToken($requestToken, $requestTokenSecret);
      $accessTokenInfo = $oauth->getAccessToken(self::$_request_url, null, $oauthVerifier);
      
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
        
        return $data;
      } else 
      {
        static::$_last_error = 'Failed to obtain token credentials: ' . $oauth->getLastResponse();
      }
    } catch (OAuthException $e) {
      static::$_last_error = 'Error obtaining token credentials: ' . $e->getMessage();
    }  
    
    return false;
  }
}

/* End File */