<?php
class Turbine {

	public static function compile($files) {
		$files_str = (is_array($files))? implode(';', $files) : $files;
		Yii::app()->clientScript->registerCssFile('/css/compiler.php?files='.$files_str);
	}

}