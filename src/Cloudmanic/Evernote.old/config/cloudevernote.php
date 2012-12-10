<?php if(! defined('BASEPATH')) exit('No direct script access allowed');

$config['ev_oauth_consumer_key'] = 'cloudmanic';
$config['ev_oauth_consumer_secret'] = 'e63036125d7038ce';
$config['ev_evernote_server'] = 'https://sandbox.evernote.com';
$config['ev_notestore_host'] = 'sandbox.evernote.com';
$config['ev_notestore_port'] = '443';
$config['ev_notestore_protocal'] = 'https'; 
$config['ev_request_token_url'] = $config['ev_evernote_server'] . '/oauth';
$config['ev_access_token_url'] = $config['ev_evernote_server'] . '/oauth';
$config['ev_authorization_url'] = $config['ev_evernote_server'] . '/OAuth.action';

/* End File */