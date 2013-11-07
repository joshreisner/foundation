<?php
/**
 * 
 * MAIL
 * 
 * Very simple, lightweight email builder, similar to SwiftMailer
 *
 * @package Foundation
 */

class mail {
	
	protected $body = '';
	protected $subject = '';

	function body($content) {
		$this->body = $content;
		return $this;
	}

	static function compose() {
		return new self;
	}

	function send() {
		if (!isset($this->to))		$this->to(config::get('mail.to', true));
		if (!isset($this->replyTo))	$this->replyTo = config::get('mail.from', true);

		foreach ($this->to as $to) {
			$result = mail($to, $this->subject, $this->body, implode(NEWLINE, array(
				'MIME-Version: 1.0',
				'Content-type: text/html; charset=' . config::get('charset'),
				'From: ' . config::get('mail.from', true),
    			'Reply-To: ' . $this->replyTo,
    			'X-Mailer: PHP/' . phpversion(),
    		)));
		}

		return true;
	}

	function replyTo($address) {
		$this->replyTo = $address;
		return $this;
	}

	function subject($subject) {
		$this->subject = $subject;
		return $this;
	}

	function to($addresses) {
		if (!isset($this->to)) $this->to = array(); //todo initialize in compose()

		//todo sanitize addresses
		if (!is_array($addresses)) {
			//plain email address
			$this->to[] = $addresses;
		} else {
			if (a::associative($addresses)) {
				foreach ($addresses as $address=>$name) {
					if (!empty($name)) {
						$this->to[] = $name . ' <' . $address . '>';
					} else {
						$this->to[] = $address;
					}
				}
			} else {
				foreach ($addresses as $address) {
					$this->to[] = $address;
				}
			}
		}
		
		return $this;
	}
}