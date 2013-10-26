<?php

class file {

	public static function dir($path, $types=false) {
		if (!is_dir($path)) return false;
		if ($types) {
			//todo be able to limit to certain file types
		} else {
			$skip = array('.', '..', '.DS_Store');
			return array_diff(scandir($path), $skip);
		}
	}
}