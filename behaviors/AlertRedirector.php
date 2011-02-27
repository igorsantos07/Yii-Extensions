<?php
class AlertRedirector extends CBehavior {

  	public function alertRedirect($msg, $url) {
		Yii::app()->clientScript->registerScript('alert', "window.alert('$msg'); window.location='$url'", CClientScript::POS_HEAD);
   	}

}
?>