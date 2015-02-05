<?php
namespace vakata\payment;

class Paypal extends AbstractPayment
{
	public function __construct(array $options = array()) {
		$options = array_merge(
			array(
				'url'			=> 'https://api-3t.paypal.com/nvp/',
				'test'			=> true,
				'username'		=> 'xxxx',
				'password'		=> '0000',
				'signature'		=> '0000',
				'email'			=> ''
			),
			$options
		);
		if($options['test']) {
			$options['url'] = 'https://api-3t.sandbox.paypal.com/nvp/';
		}
		$this->options = $options;
	}
	public function pay(array $options = array()) {
		$options = array_merge(
			array(
				'amount'		=> 0.00,
				'currency'		=> 'EUR',
				'description'	=> '',
				'invoice'		=> time(),
				'success_url'	=> false,
				'cancel_url'	=> false
			),
			$options
		);
		$data = array(
			'USER'								=> $this->options['username'], 
			'PWD'								=> $this->options['password'],
			'VERSION'							=> '119.0',
			'SIGNATURE'							=> $this->options['signature'],
			'METHOD'							=> 'SetExpressCheckout',
			'PAYMENTREQUEST_0_AMT'				=> $options['amount'],
			'PAYMENTREQUEST_0_INVNUM'			=> $options['invoice'],
			'PAYMENTREQUEST_0_DESC'				=> $options['description'],
			'PAYMENTREQUEST_0_PAYMENTACTION'	=> 'Sale',
			'PAYMENTREQUEST_0_CURRENCYCODE'		=> $options['currency'],
			'RETURNURL'							=> $options['success_url'],
			'CANCELURL'							=> $options['cancel_url'],
			'NOSHIPPING'						=> 1,
			'ALLOWNOTE'							=> 0
		);

		$data = $this->post($this->options['url'], $data);

		if(!$data) {
			$this->redirect($options['cancel_url']);
		}
		parse_str($data, $data);
		if($data['ACK'] != 'Success') {
			$this->redirect($options['cancel_url']);
		}
		else {
			$url = $this->options['test'] ?
				'https://www.sandbox.paypal.com/webscr&cmd=_express-checkout&useraction=commit&token=' : 
				'https://www.paypal.com/webscr&cmd=_express-checkout&useraction=commit&token=';
			$this->redirect($url.urldecode($data["TOKEN"]));
		}
	}
	public function ipn(callable $c = null) {
		while(ob_get_level()) { ob_end_clean(); }
		$url = $this->options['test'] ? 'https://www.sandbox.paypal.com/cgi-bin/webscr' : 'https://www.paypal.com/cgi-bin/webscr';

		if(isset($_POST) && is_array($_POST) && count($_POST)) {
			$res = $this->post($url, 'cmd=_notify-validate&' . file_get_contents('php://input'));
			if($res && strpos($res,'VERIFIED') !== false && $_POST['receiver_email'] === $this->options['email']) {
				$is_test = (isset($_POST['test_ipn']) && $_POST['test_ipn'] == 1);
				if($is_test === $this->options['test']) {
					if(in_array($_POST['payment_status'], array('Completed','Voided','Expired','Reversed'))) {
						if($c) {
							// test for duplicate txn_id
							// $_POST['mc_gross'], $_POST['mc_currency']
							call_user_func($c, $_POST['invoice'], $_POST['payment_status'] === 'Completed');
						}
					}
				}
			}
			die();
		}
	}
}