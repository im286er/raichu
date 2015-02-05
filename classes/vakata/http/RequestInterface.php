<?php
namespace vakata\http;

interface RequestInterface extends MessageInterface
{
	public function getMethod();

	public function getUrl($withQuery = true);
	public function getUrlSegments();
	public function getUrlSegment($i, $stripExtension = false);
	public function getUrlExtension($default = null);
	public function getUrlRequest($ext = true);
	public function getUrlBase();
	public function getUrlServer();
	public function getUrlDomain();

	public function getServer($key = null, $default = null, $mode = null);
	public function getCookie($key = null, $default = null, $mode = null);
	public function getQuery($key = null, $default = null, $mode = null);
	public function getPost($key = null, $default = null, $mode = null);
	public function getRequest($key = null, $default = null, $mode = null);

	public function getFile($key = null);

	public function userAgent();
	public function ipAddress();
	
	public function isAjax();
	public function isCors();
	public function isSecure();
	public function isSelf();

	public function getResponseFormat();
	public function getAuthorization();
}