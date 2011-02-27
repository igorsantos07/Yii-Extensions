<?php

/**
 * A Yii Model behavior to add YouTube functionalities to a video model.
 * Needs ['youtube']['user'], ['youtube']['password'] Yii params (set in the end of config/main.php)
 *
 * @uses Zend.Gdata
 * @author igoru
 */
class YouTubeVideo extends CActiveRecordBehavior {
	
	/** @var string */	public $title;
	/** @var string */	public $description;
	/** @var string */	public $file;
	/** @var integer */	public $length;
	/** @var array */	public $thumbnail;
	/** @var array */	public $thumbnails;
	/** @var string */	public $flashPlayerUrl;
	/** @var integer */	public $viewCount;

	public function tableName() { return $this->owner->tableName(); }

  	public function getPlayer() {
		return <<<EOT
			<object width="425" height="350">
				<param name="movie" value="$this->flashPlayerUrl&amp;hl=pt_BR&amp;fs=1?rel=0&amp;color1=0x2b405b&amp;color2=0x6b8ab6"></param>
				<embed src="$this->flashPlayerUrl&amp;hl=pt_BR&amp;fs=1?rel=0&amp;color1=0x2b405b&amp;color2=0x6b8ab6" type="application/x-shockwave-flash" allowscriptaccess="always" allowfullscreen="true" width="318" height="258"></embed>
			</object>
EOT;
	}

	/**
	 * Includes required Zend files and returns a YouTube object
	 * @return Zend_Gdata_YouTube
	 */
	protected function getYouTube($auth = false) {
		Yii::import('ext.*');
		require_once 'Zend/Loader.php';
		Zend_Loader::loadClass('Zend_Gdata_YouTube');
		Zend_Loader::loadClass('Zend_Uri_Http');
		Zend_Loader::loadClass('Zend_Http_Client_Adapter_Socket');
		Zend_Loader::loadClass('Zend_Gdata_YouTube_Extension_Statistics');

		if ($auth) {
			Zend_Loader::loadClass('Zend_Gdata_ClientLogin');
			$login = Zend_Gdata_ClientLogin::getHttpClient(Yii::app()->params['youtube']['user'], Yii::app()->params['youtube']['password'], 'youtube', null, Yii::app()->name);
			$login->setHeaders('X-GData-Key', 'key='.Yii::app()->params['youtube']['devKey']);
		}
		else { $login = null; }

		return new Zend_Gdata_YouTube($login);
	}

	/**
	 * Returns an array with youtube object and video object. Should be used with list($utub, $video)
	 * 
	 * @return array (youtube, video)
	 */
	protected function getVideo() {
		$utub = $this->getYouTube(true);
		$video = $utub->getFullVideoEntry($this->owner->youtubeId);

		return array($utub, $video);
   	}

	public function beforeSave($event) {
		parent::beforeSave($event);

		if (!$this->owner->isNewRecord) {
			list($utub, $video) = $this->getVideo();
			$video->setVideoDescription($this->owner->description);
			$video->setVideoTitle($this->owner->title);
           	$utub->updateEntry($video, $video->getEditLink()->getHref());
       	}
	}

	public function afterSave($event) {
        parent::afterSave($event);

		if (!$this->owner->isNewRecord) {
			$videoCache = Yii::app()->cache->get('Video::'.$this->owner->youtubeId);
				$videoCache['description'] = $this->owner->description;
				$videoCache['title'] = $this->owner->title;
			Yii::app()->cache->set('Video::'.$this->owner->youtubeId, $videoCache);
		}
    }

	public function afterFind($event) {
		parent::afterFind($event);

       	$cacheKey = 'Video::'.$this->owner->youtubeId;
		$data = Yii::app()->cache->get($cacheKey);
		if ($data === false || !$data['length']) { //empty length means "video not processed yet"
			list($utub, $video) = $this->getVideo();

			$data = array(
				'flashPlayerUrl'	=> $video->getFlashPlayerUrl(),
				'description'		=> $video->getVideoDescription(),
				'length'			=> $video->getVideoDuration(),
				'thumbnails'		=> $video->getVideoThumbnails(),
				'title'				=> $video->getVideoTitle(),
				'viewCount'			=> (int)$video->getVideoViewCount(),
			);

			Yii::app()->cache->set('Video::'.$this->owner->youtubeId, $data);
		}

		foreach($data as $attr => $value) $this->$attr = $value;
	}

	public function beforeDelete($event) {
		parent::beforeDelete($event);

		//erasing from youtube...
		$utub = $this->getYouTube(true);
		$utub->delete($utub->getFullVideoEntry($this->owner->youtubeId));

		//from our cache...
		Yii::app()->cache->delete('Video::'.$this->owner->youtubeId);

		//and now we will let the method continue on deleting the database entry
		return true;
	}
}