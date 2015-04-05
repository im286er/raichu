<?php
namespace vakata\user\authentication\OAuth;

class Facebook extends AbstractOAuth
{
	protected $permissions   = ''; // public_profile,email
	protected $authorize_url = 'https://www.facebook.com/v2.2/dialog/oauth?';
	protected $token_url     = 'https://graph.facebook.com/v2.2/oauth/access_token';
	protected $info_url      = 'https://graph.facebook.com/v2.2/me?';
	protected $grant_type    = '';
}