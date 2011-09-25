<?php
/**
 * A ActiveRecordBehavior that resizes and deletes automatically images from a AR record.
 * Requires ImageCropper: http://www.yiiframework.com/extension/imagecropper/
 * Requires also the param "imgPath", that is relative to the base folder of your project (protected/../), and "imgUrl", relative to your's site root URL
 *
 * @author igoru
 */
class HasImage extends CActiveRecordBehavior {

	/**
	 * Which attributes from {@link owner} are image fields
	 * @var array
	 */
	public $fields			= array();

	/**
	 * If the image should be kept in the original size or should be resized
	 * @var boolean
	 */
	public $resize			= true;
	/**
	 * Dimensions of the final image, if resized, for each field. array(array(width, height))
	 * @var array
	 */
	public $resizeTo		= array(array(300,200));
	/**
	 * How the thumbnail file should be named. Words between asterisks (*)
	 * will be replaced by its corresponding attribute. Case-sensitive way.
	 * @var string
	 */
	public $nameMask		= '*primaryKey*';
	/**
	 * If the filename will be prepended with the field name. Useful when you have more than one image field for the model.
	 */
	public $prependFileName	= true;
	/**
	 * Generated JPEG quality.
	 * @var integer
	 */
	public $fileQuality		= 90;

	/**
	 * If a thumbnail should be generated
	 * @var boolean
	 */
	public $hasThumb		= false;
	/**
	 * Thumbnail dimensions, if exists, for each field. array(array(width, height))
	 * @var array
	 */
	public $thumbSize		= array(96,96);
	/**
	 * Generated thumbnail JPEG quality.
	 * @var integer
	 */
	public $thumbQuality	= 90;
	/**
	 * Name of the folder where images are going to be saved. Defaults to the table name.
	 * @var string
	 */
	public $folderName		= null;

	public function __construct() {
      Yii::import('ext.ImageCropper');
   }

	public function beforeValidate($event) {
      parent::beforeValidate($event);

       	foreach ($this->fields as $field) {
           	if (!is_a($this->owner->$field, 'CUploadedFile'))
					$this->owner->$field = CUploadedFile::getInstance($this->owner->model(), $field);
		}

		return true;
   }

	public function beforeSave($event) {
      parent::beforeSave($event);

       	if (!$this->owner->isNewRecord) {
			foreach ($this->fields as $field) {
				if (!$this->owner->$field)
					$this->owner->$field = $this->owner->model()->findByPk($this->owner->primaryKey)->$field;
			}
       	}

      	return true;
   }

 	public function afterSave($event) {
		parent::afterSave($event);

		$newNames = array();

       	$i = 0;
       	foreach ($this->fields as $field) {
           	if ($this->owner->$field instanceof CUploadedFile) {
               	//generating the new filename and subtituting it in the entry
				$fileName = $newNames[$field] = preg_replace('/\*([a-zA-Z_]+[a-zA-Z0-9_]*)\*/e', '\$this->owner->$1', $this->nameMask).'.jpg';
				if ($this->prependFileName) $fileName = $field.'_'.$fileName;
				$cropper = new ImageCropper();

               	//creating the original image
				if ($this->resize)
					$cropper->resize_and_crop($this->owner->$field->tempName, self::getImagePath($fileName), $this->resizeTo[$i][0], $this->resizeTo[$i][1], $this->fileQuality, true);
				else
					$this->owner->$field->saveAs(self::getImagePath($fileName));

				//and now, the thumbnail
				if ($this->hasThumb) {
					$thumbName = $this->generateThumbName($fileName);
					$cropper->resize_and_crop(self::getImagePath($fileName), self::getImagePath($thumbName), $this->thumbSize[$i][0], $this->thumbSize[$i][1], $this->thumbQuality, true);
				}
			}
           	++$i;
		}


      	//now we need to update the entry with the new filename
		if (sizeof($newNames) > 0) {
			$reg = $this->owner->findByPk($this->owner->primaryKey);
			foreach ($newNames as $field => $newName) $reg->$field = $newName;
			$reg->save(false);
		}
   }

	public function afterDelete($event) {
      parent::afterDelete($event);

		foreach ($this->fields as $field) {
			if (isset($this->owner->$field)) {
				$img = $this->getImagePath($this->owner->$field);
				$thumb = $this->getImagePath($this->owner->$field, true);
           	if(file_exists($img)) unlink($img);
           	if(file_exists($thumb)) unlink($thumb);
			}
		}

		return true;
   }

	/**
	 * Updates the provisory, original filename from the uploaded picture to the permanent one (ID.EXT)
	 */
	private function updateImageName() {
		$img = self::getImagePath((string)$this->owner->$field);
		if (file_exists($img)) {
			$pk = is_array($this->owner->primaryKey)? implode('-', $this->owner->primaryKey): $this->owner->primaryKey;
			$imageNewName = "$pk.jpg";

			if ($reg->owner->$field != $imageNewName) {
				rename(self::getImagePath($this->owner->$field), self::getImagePath($imageNewName));

				$reg->owner->$field = $this->owner->$field = $imageNewName;
				$reg->save(false);
			}
		}
  	}

	/**
	 * Returns the image system path, at this format:
	 * /var/www/your_site/img_folder/table_name/file.jpg
	 * Requires the app param "imgPath", that should contain the system path to the image folder.
	 * @param string $filename
	 * @return string
	 */
	private function getImagePath($filename, $thumb = false) {
       	if ($thumb) $filename = $this->generateThumbName($filename);
		return Yii::app()->getBasePath(true).'/../'.Yii::app()->params['imgPath'].'/'.$this->getFolderName().'/'.$filename;
	}

	/**
	 * Returns the image URL, in this format:
	 * http://www.yoursite.com/img_folder/table_name/file.jpg
	 * Requires the app params "imgUrl", that should contain relative paths
	 * from the admin folder and root folder to the image folder.
	 * @param string $root=false
	 * @param string $thumb=false
	 * @return string
	 */
	public function getImageUrl($field, $thumb = false) {
     	$filename = ($thumb)? $this->generateThumbName($this->owner->$field) : $this->owner->$field;
     	return Yii::app()->getBaseUrl(true).'/'.($root? Yii::app()->params['imgRootUrl'] : Yii::app()->params['imgUrl']).'/'.$this->getFolderName().'/'.$filename;
	}

	private function generateThumbName($originalName) {
     	if (!$this->hasThumb) return $originalName;
     	return strtr($originalName, array('.jpg' => '.thumb.jpg'));
	}

	private function getFolderName() {
		if ($this->folderName) return $this->folderName;
		else return $this->owner->model()->tableName();
	}

}
?>