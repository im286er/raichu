<?php
namespace vakata\payment;

interface PaymentInterface
{
	public function post($url, $data = '', $raw = false, $ua = '');
	public function redirect($url, array $post = null);
	public function pay(array $options = array());
	public function ipn(callable $c = null);
}