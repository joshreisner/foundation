<?php

class mail {
	
	function body($content) {
		$this->body = $content;
		return $this;
	}

	static function compose() {
		return new self;
	}

	function send() {
		if (!isset($this->to))		$this->to(config::get('mail.to', true));
		if (!isset($this->subject)) $this->subject = '';
		if (!isset($this->body))	$this->body = '';

		foreach ($this->to as $to) {
			$result = mail($to, $this->subject, $this->body, 
				'From: ' . config::get('mail.from', true) . NEWLINE .
    			'Reply-To: ' . config::get('mail.from', true) . NEWLINE .
    			'X-Mailer: PHP/' . phpversion()
    		);
		}
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