<?php
namespace vakata\user\authentication\OAuth;

class Google extends AbstractOAuth
{
	protected $permissions   = 'https://www.googleapis.com/auth/userinfo.profile';
	protected $authorize_url = 'https://accounts.google.com/o/oauth2/auth?response_type=code&';
	protected $token_url     = 'https://accounts.google.com/o/oauth2/token';
	protected $info_url      = 'https://www.googleapis.com/oauth2/v1/userinfo?';
}