<?php
/**
 * A Yii behavior to make easy to upload videos to Youtube.
 * Needs ['youtube']['user'], ['youtube']['password'] Yii params (set in the end of config/main.php)
 *
 * @uses Zend.Gdata
 * @author igoru
 */
class YouTubeUploader extends CBehavior {
	protected $youtubeLogin;
	protected $youtubePassword;
	protected $youtubeDevKey;

	const CAT_SCHEME = 'http://gdata.youtube.com/schemas/2007/categories.cat';
	const TAG_SCHEME = 'http://gdata.youtube.com/schemas/2007/developertags.cat';

	function __construct() {
		$this->login(
			Yii::app()->params['youtube']['user'],
			Yii::app()->params['youtube']['password'],
			Yii::app()->params['youtube']['devKey']
		);
  	}

	/**
	 * Saves login (email) and password inside the class
	 * @param string $user The Youtube User. IT'S AN EMAIL!!!
	 * @param string $password The password
	 * @param string $devKey The developer key. If you have no idea what I'm talking about, go to {@link http://code.google.com/apis/youtube/dashboard/}
	 */
	protected function login($user, $password, $devKey) {
		$this->youtubeLogin		= $user;
		$this->youtubePassword	= $password;
		$this->youtubeDevKey	= $devKey;
	}

	/**
	 * Prints a JSON with URL for form action and the needed token, and dies.<br />
	 * Used with JSON POST requests.<br />
	 * <p>Asks for the following POST fields:
	 * <ul>
	 *		<li>title</li>
	 *		<li>description</li>
	 *		<li>category (only one)</li>
	 *		<li>keywords (comma-separated)</li>
	 * </ul>
	 * </p>
	 * @return JSON
	 */
	public function actionFormData() {
		Yii::import('system.collections.*');
		Yii::import('system.base.*');
		Yii::import('ext.*');
		Yii::import('ext.Zend.Gdata.*');
		require_once 'Zend/Loader.php';
		Yii::registerAutoloader(array('Zend_Loader','loadClass'));

    	$login = Zend_Gdata_ClientLogin::getHttpClient($this->youtubeLogin, $this->youtubePassword, 'youtube', null, Yii::app()->name);
		$login->setHeaders('X-GData-Key', 'key='.$this->youtubeDevKey);

    	$utub	= new Zend_Gdata_YouTube($login);
		$video	= new Zend_Gdata_YouTube_VideoEntry();
		$media	= $utub->newMediaGroup();
			$media->title		= $utub->newMediaTitle()->setText($_POST['title']);
			$media->description	= $utub->newMediaDescription()->setText($_POST['description']);
			$media->keywords	= $utub->newMediaKeywords()->setText($_POST['keywords']);
			$media->category	= array(
				$utub->newMediaCategory()->setText($_POST['category'])->setScheme(self::CAT_SCHEME),
				$utub->newMediaCategory()->setText(preg_replace('/\s/', '', Yii::app()->name).'Site')->setScheme(self::TAG_SCHEME),
			);
		$video->mediaGroup = $media;

		$data = $utub->getFormUploadToken($video);
		exit(json_encode($data));
  	}

}
?>
