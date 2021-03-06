## Overview

This is a wrapper library for making work with the Evernote API easier with PHP.

## Requirements

1. PHP 5.3+
2. CURL

## Access Tokens

You can get an access token either via oAuth or using one of these links.

* (sandbox) https://sandbox.evernote.com/api/DeveloperToken.action
* (production) https://www.evernote.com/api/DeveloperToken.action

## Supported Functions.

* Cloudmanic\Evernote\Api::set_access_token($token, false)
* $user = Cloudmanic\Evernote\Api::get_user()
* $notebooks = Cloudmanic\Evernote\Apie::get_notebooks()
* $guid = Cloudmanic\Evernote\Api::new_notebook('My First Notebook 3')
* Cloudmanic\Evernote\Api::add_file('test.jpg')
* $guid = Cloudmanic\Evernote\Api::new_note($title, $body, $notebook_guid)
* $tags = Cloudmanic\Evernote\Api::get_tags()
* $guid = Cloudmanic\Evernote\Api::new_tag('My First Tag')

## Error Functions

* Cloudmanic\Evernote\Api::get_error_string()
* Cloudmanic\Evernote\Api::get_error()
* Cloudmanic\Evernote\Api::get_error_code()
* Cloudmanic\Evernote\Api::get_error_parameter()

## Author(s) 

* Company: Cloudmanic Labs, [http://cloudmanic.com](http://cloudmanic.com)

* By: Spicer Matthews [http://spicermatthews.com](http://spicermatthews.com)
