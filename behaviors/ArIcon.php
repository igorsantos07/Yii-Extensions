<?php
class ArIcon extends CActiveRecordBehavior {

	/**
	 * The name for the field that holds the image name. Defaults to "icon_set"
	 * @var string
	 */
	public $icon = 'icon';

	/**
	 * The name for the field that holds the this entry's name. Defaults to "name"
	 * @var type
	 */
	public $name = 'name';

	/**
	 * The image path. Replaces {icon} with the image name. Defaults to "/images/icons/{icon}.png".
	 * @var string
	 */
	public $path = "/images/icons/{icon}.png";

	public function getIconImage() {
		if ($this->owner->{$this->icon})
			return strtr($this->path, array('{icon}' => $this->owner->{$this->icon}));
	}

	private function iconText($field) {
		return "<span class=\"icon_text\"><img src=\"{$this->iconImage}\" alt=\"icon\" /> {$this->owner->$field}</span>";
	}

	public function getIconWithName() {
		return $this->iconText($this->name);
	}

	public function getIconWithIconName() {
		if ($this->owner->{$this->icon})
			return $this->iconText($this->icon);
	}

}