<?php
namespace vakata\payment;

class Epay extends AbstractPayment
{
	protected function hash($data) {
		$passwd = $this->options['secret'];
		if (strlen($passwd) > 64) {
			$passwd = pack('H40', sha1($passwd));
		}
		if (strlen($passwd)<64) {
			$passwd = str_pad($passwd, 64, chr(0));
		}
		$ipad = substr($passwd, 0, 64) ^ str_repeat(chr(0x36), 64);
		$opad = substr($passwd, 0, 64) ^ str_repeat(chr(0x5C), 64);

		return sha1($opad . pack('H40', sha1($ipad . $data)));
	}

	public function __construct(array $options = []) {
		$options = array_merge(
			array(
				'url'			=> 'https://www.epay.bg/',
				'test'			=> true,
				'secret'		=> 'xxxx',
				'mid'			=> '0000'
			),
			$options
		);
		if ($options['test']) {
			$options['url'] = 'https://devep2.datamax.bg/ep2/epay2_demo/';
		}
		$this->options = $options;
	}
	public function pay(array $options = []) {
		$options = array_merge(
			array(
				'amount'		=> 0.00,
				'currency'		=> 'BGN',
				'description'	=> '',
				'exp_date'		=> date('d.m.Y', strtotime('+1 year')),
				'invoice'		=> time(),
				'success_url'	=> false,
				'cancel_url'	=> false
			),
			$options
		);
		$data  = '';
		$data .= 'MIN=' . $this->options['mid'] . "\n";
		$data .= 'INVOICE=' . sprintf('%1d', $options['invoice']) . "\n";
		$data .= 'AMOUNT=' . sprintf('%01.2f', $options['amount']) . "\n";
		$data .= 'EXP_TIME=' . $options['exp_date'] . "\n";
		$data .= 'DESCR=' . $options['description'] . "\n";
		$data .= 'ENCODING=utf-8'."\n";
		$data  = base64_encode($data);
		$result = array(
			'PAGE'		=> 'paylogin',
			'ENCODED'	=> $data,
			'CHECKSUM'	=> $this->hash($data)
		);
		if (isset($options['success_url']) && $options['success_url']) {
			$result['URL_OK'] = $options['success_url'];
		}
		if (isset($options['cancel_url']) && $options['cancel_url']) {
			$result['URL_CANCEL'] = $options['cancel_url'];
		}
		return $this->redirect($this->options['url'], $result);
	}
	public function ipn(callable $c = null) {
		while (ob_get_level()) {
			ob_end_clean();
		}
		if (isset($_POST) && is_array($_POST) && count($_POST)) {
			@header('Content-Type: text/plain; charset=utf-8');
			if ($_POST['checksum'] == $this->hash($_POST['encoded'])) {
				$data = base64_decode($_POST['encoded']);
				$data = explode("\n", $data);
				$info_data = '';
				
				foreach ($data as $line) {
					if (preg_match("/^INVOICE=(\d+):STATUS=(PAID|DENIED|EXPIRED)(:PAY_TIME=(\d+):STAN=(\d+):BCODE=([0-9a-zA-Z]+))?$/", $line, $regs)) {
						$invoice	= $regs[1];
						$status		= $regs[2];
						$pay_date	= $regs[4];
						$stan		= $regs[5];
						$bcode		= $regs[6];

						if ($c) {
							if (call_user_func($c, $invoice, strtolower($status) === 'paid') === true) {
								$info_data .= "INVOICE=$invoice:STATUS=OK\n";
							}
							else {
								$info_data .= "INVOICE=$invoice:STATUS=NO\n";
							}
						}
						else {
							$info_data .= "INVOICE=$invoice:STATUS=OK\n";
						}
						//$info_data .= "INVOICE=$invoice:STATUS=OK\n";
						//$info_data .= "INVOICE=$invoice:STATUS=ERR\n";
						//$info_data .= "INVOICE=$invoice:STATUS=NO\n";
					}
				}
				echo $info_data . "\n";
			}
			else {
				echo "ERR=Not valid CHECKSUM\n";
			}
			die();
		}
	}
}

/*
$pp = new \vakata\payment_paypal(array(
	'test'		=> true,
	'username'	=> 'rsell_1362036653_biz_api1.vakata.com',
	'password'	=> '1362036685',
	'signature'	=> 'AjloJoLd7OSLHwgGAl1XGpNQR4KQAM3-3by9DhEvbYbmhS0fmM5yfw2C',
	'email'		=> 'rsell_1362036653_biz@vakata.com'
));
$pp->ipn();
if (!count($_GET) && !count($_POST)) {
	$pp->pay(array(
		'amount'		=> 12.00,
		'currency'		=> 'USD',
		'description'	=> 'asdfa sdf asdf ',
		'success_url'	=> 'http://vakata.com/paypal.php?success',
		'cancel_url'	=> 'http://vakata.com/paypal.php?fail'
	));
}
*/
/*
$pp = new \vakata\payment_epay(array(
	'test'			=> true,
	'secret'		=> '9YU5H03R5XKTAMW2ITF8DRLL28BA9SCTRJNN7QP5B3544TP4YTBIOG0YYOFSCDF7',
	'mid'			=> 'D570050645'
));
$pp->ipn();
if (!count($_GET) && !count($_POST)) {
	$pp->pay(array(
		'amount'		=> 12.00,
		'currency'		=> 'BGN',
		'description'	=> 'asdfa sdf asdf ',
		'success_url'	=> 'http://127.0.0.1/?success',
		'cancel_url'	=> 'http://127.0.0.1/?fail'
	));
}
*/
