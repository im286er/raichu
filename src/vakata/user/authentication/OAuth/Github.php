<?php
namespace vakata\user\authentication\OAuth;

class Github extends AbstractOAuth
{
	protected $permissions   = ''; // user:email
	protected $authorize_url = 'https://github.com/login/oauth/authorize?';
	protected $token_url     = 'https://github.com/login/oauth/access_token';
	protected $info_url      = 'https://api.github.com/user?';
}