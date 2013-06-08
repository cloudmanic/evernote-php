<?php

use Cloudmanic\Evernote\Auth;

class EvernoteController extends BaseController 
{
	//
	// We call this when we are done with our auth.
	//
	public function done()
	{
		// Get session data.
		$auth = Session::get('evernote_access');
		
		// Take the data we received and store it. 
		Settings::update([
			'SettingsEvAccessToken' => $auth['accessToken'],
			'SettingsEvUserId' => $auth['userId'],
			'SettingsEvTokenExpires' => $auth['tokenExpires']
		], Settings::get_settings_id());
		
		// Destory session.
		Session::forget('evernote_access');
		
		return Redirect::to('/?evernote=success');
	}
	
	//
	// We call this when we want to remove an evernote link.
	//
	public function remove()
	{
		// Take the data we received and store it. 
		Settings::update([
			'SettingsEvAccessToken' => '',
			'SettingsEvUserId' => '',
			'SettingsEvTokenExpires' => ''
		], Settings::get_settings_id());
		
		return Redirect::to('/?evernote=removed');
	}

	//
	// Auth to Evernote.
	//
	public function auth()
	{
		// Setup the Evernote Auth.
		Auth::set_config(
			Config::get('site.evernote_key'), 
			Config::get('site.evernote_secret'), 
			URL::to('/evernote/callback'), 
			Config::get('site.evernote_sandbox')
		);
		
		// Get temp credentials
		if(! $tmp = Auth::get_temporary_credentials())
		{
			return Redirect::to('/?evernote=failed');
		} 
		
		// Save details in a session.
		Session::put('oauth_temporary', $tmp);
	
		// Redirect to evernote to authorize.
		$url = Auth::get_authorization_url($tmp);
		return Redirect::to($url);
	}
	
	//
	// Callback from Evernote
	//
	public function callback()
	{
		// At this point we should have an oauth_verifier.
		if(! Input::get('oauth_verifier'))
		{
		  return Redirect::to('/?evernote=failed');
		}
		
		// Setup the Evernote Auth.
		Auth::set_config(
		  Config::get('site.evernote_key'), 
		  Config::get('site.evernote_secret'), 
		  URL::to('/evernote/callback'), 
		  Config::get('site.evernote_sandbox')
		);
		
		// Get temp data.
		$tmp = Session::get('oauth_temporary');
		
		// Get token credentials
		$data = Auth::get_token_credentials(
		  $tmp['oauth_token'],
		  $tmp['oauth_token_secret'],
		  Input::get('oauth_verifier')
		);
		
		// No data? Fail.
		if((! $data) || (! isset($data['accessToken'])) || 
		  (! isset($data['tokenExpires'])) || (! isset($data['userId'])))
		{
		  return 'failed';
		}
		
		// Store data in a session and redirect.
		Session::put('evernote_access', $data);
		
		// Clean up old session
		Session::forget('oauth_temporary');
		
		// Redirect to data manager.
		return Redirect::to('evernote/done');
	}
}

/* End File */