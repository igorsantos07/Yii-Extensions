<?php
class DateHelper {

	public static function br2iso($date) {
		list($d,$m,$y) = explode('/', $date);
		return "$y-$m-$d";
	}

}