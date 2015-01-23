<?php
namespace vakata\user\authentication\OAuth;

class Microsoft extends AbstractOAuth
{
	protected $permissions   = 'wl.signin,wl.basic';
	protected $authorize_url = 'https://login.live.com/oauth20_authorize.srf?response_type=code&';
	protected $token_url     = 'https://login.live.com/oauth20_token.srf';
	protected $info_url      = 'https://apis.live.net/v5.0/me?';
}