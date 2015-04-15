<?php
namespace vakata\mail\send;

interface SenderInterface
{
	public function send(array $to, array $cc, array $bcc, $from, $subject, $headers, $message);
}