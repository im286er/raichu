<?php
namespace vakata\user\authentication\OAuth;

class Linkedin extends AbstractOAuth
{
	protected $permissions   = ''; // r_fullprofile%20r_emailaddress // space separated
	protected $authorize_url = 'https://www.linkedin.com/uas/oauth2/authorization?response_type=code&';
	protected $token_url     = 'https://www.linkedin.com/uas/oauth2/accessToken';
	protected $info_url      = 'https://api.linkedin.com/v1/people/~?oauth2_';
}