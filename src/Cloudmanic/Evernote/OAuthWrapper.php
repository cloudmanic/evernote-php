<?php
//
// Company: Cloudmanic Labs, LLC
// By: Spicer Matthews 
// Email: spicer@cloudmanic.com
// Website: http://cloudmanic.com
// Date: 5/10/2014
// Stolen From: https://github.com/stanlemon/evernote
//

namespace Cloudmanic\Evernote;

use Guzzle\Http\Client;
use Guzzle\Plugin\Oauth\OauthPlugin;

class OAuthWrapper 
{
	protected $baseUrl;
	protected $sandbox;
	protected $consumerKey;
	protected $consumerSecret;
	protected $callbackUrl;
	protected $requestTokenUrl = 'oauth';
	protected $accessTokenUrl = 'oauth';
	protected $authorizationUrl = 'OAuth.action?oauth_token=%s';

	protected $tokens;

	protected $client;
	protected $oauthPlugin;

	//
	// Construct.
	//
	public function __construct($baseUrl) 
	{
		$this->baseUrl = $baseUrl;
	}

	//
	// Request Temp Credentials.
	//
	public function requestTempCredentials() 
	{
		return $this->requestCredentials($this->requestTokenUrl . '?oauth_callback=' . $this->callbackUrl);
	}
	
	public function requestAuthCredentials($token, $tokenSecret, $verifier) {
		return $this->requestCredentials(
			$this->accessTokenUrl . '?oauth_callback=' . $this->callbackUrl . '&oauth_verifier=' . $verifier,
			$token,
			$tokenSecret
		);
	}

	//
	// Request Credentials.
	//
	protected function requestCredentials($url, $token = false, $tokenSecret = false) 
	{
		$client = $this->getClient($token, $tokenSecret);
		$response = $client->post($url)->send();
		return $this->makeTokens($response);
	}

	//
	// Make tokens.
	//
	protected function makeTokens($response) 
	{
		$body = (string) $response->getBody();

		$tokens = array();
		parse_str($body, $tokens);
		
		if(empty($tokens)) 
		{
		  throw new \Exception("An error occurred while requesting oauth token credentials");
		}
		
		$this->tokens = $tokens;
		return $this->tokens;
	}

	//
	// Make Client.
	//
	public function getClient($token = false, $tokenSecret = false) 
	{
		if(! is_null($this->client)) 
		{
		  return $this->client;
		} else 
		{
		  $this->client = new Client($this->baseUrl);
		
		  $this->oauthPlugin = new OauthPlugin(array(
		  	'consumer_key' 		=> $this->consumerKey,
		  	'consumer_secret' 	=> $this->consumerSecret,
		  	'token' 			=> !$token ? $this->tokens['oauth_token'] : $token,
		  	'token_secret' 		=> !$token ? $this->tokens['oauth_token_secret'] : $tokenSecret,
		  ));
		
		  $this->client->addSubscriber($this->oauthPlugin);
		
		  return $this->client;
		}
	}

	public function makeAuthUrl() {
		return $this->baseUrl . sprintf($this->authorizationUrl, urlencode($this->tokens['oauth_token']));
	}

	public function setConsumerKey($consumerKey) {
		$this->consumerKey = $consumerKey;
		return $this;
	}

	public function setConsumerSecret($consumerSecret) {
		$this->consumerSecret = $consumerSecret;
		return $this;
	}

	public function setCallbackUrl($callbackUrl) {
		$this->callbackUrl = $callbackUrl;
		return $this;
	}

	public function setRequestTokenUrl($requestTokenUrl) {
		$this->requestTokenUrl = $requestTokenUrl;
		return $this;
	}

	public function setAccessTokenUrl($accessTokenUrl) {
		$this->accessTokenUrl = $accessTokenUrl;
		return $this;
	}

	public function setAuthorizationUrl($authorizationUrl) {
		$this->authorizationUrl = $authorizationUrl;
		return $this;
	}
	
	public function setPrivateKey($privateKey) {
		$this->privateKey = $privateKey;
		return $this;
	}
}

/* End File */