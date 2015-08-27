<?php
namespace vakata\payment;

abstract class AbstractPayment implements PaymentInterface
{
	public function redirect($url, array $post = null) {
		while (ob_get_level()) { ob_end_clean(); }
		if ($post) {
			echo '<!DOCTYPE html>' . "\n";
			echo '<html>' . "\n";
			echo '<head><title>Пренасочване ...</title></head>' . "\n";
			echo '<body onload="document.getElementById(\'payment\').submit();">' . "\n";
			echo '<form id="payment" action="'.htmlspecialchars($url).'" method="post">' . "\n";
			foreach ($post as $k => $v) {
				echo '<input type="hidden" name="'.htmlspecialchars($k).'" value="'.htmlspecialchars($v).'" />' . "\n";
			}
			echo '<button type="submit">Продължи</button>' . "\n";
			echo '</form>' . "\n";
			echo '<script>document.getElementById("payment").submit();</script>' . "\n";
			echo '</body>' . "\n";
			echo '</html>' . "\n";
		}
		else {
			if (!headers_sent()) {
				header('Location: '.$url);
			}
			else {
				echo '<script>document.location.href="'.$url.'";</script><noscript><meta http-equiv="refresh" content="0; url='.$url.'"/></noscript>';
			}
		}
		die();
	}
	public function post($url, $data = '', $raw = false, $ua = '') {
		$context = array(
			'http' => array(
				'method' => 'POST',
				'user_agent' => $ua
			)
		);
		if (!$raw) {
			if (is_array($data)) {
				$data = http_build_query($data);
			}
			$context['http']['header'] = "Content-type: application/x-www-form-urlencoded\r\n" . "Content-Length: " . strlen($data) . "\r\n";
		}
		$context['http']['content'] = $data;
		$context['http']['ignore_errors'] = true;
		return @file_get_contents($url, false, stream_context_create($context));
	}

	abstract public function pay(array $options = []);
	abstract public function ipn(callable $c = null);
}