<?php
/**
 * An AR Yii Behavior that implements simple status management,
 * for enabling and disabling a record, and implements "enabled" and "disabled" named scopes
 *
 * @author igoru
 */
class SimpleStatus extends CActiveRecordBehavior {
	
	protected $attr = 'status';

	/**
	 * Named Scope for enabled records
	 * @return CActiveRecord
	 */
	public function enabled() {
		$this->owner->getDbCriteria()->mergeWith(array('condition' => $this->attr.'=1'));
		return $this->owner;
	}


	/**
	 * Named Scope for disabled records
	 * @return CActiveRecord
	 */
	public function disabled() {
		$this->owner->getDbCriteria()->mergeWith(array('condition' => $this->attr.'=0'));
		return $this->owner;
	}
	
	public function enable() {
		if ($this->owner->{$this->attr} == 1) {
			return true;
		}
		else {
			$this->owner->{$this->attr} = 1;
			return $this->owner->save();
		}
	}

	public function disable() {
		if ($this->owner->{$this->attr} == 0) {
			return true;
		}
		else {
			$this->owner->{$this->attr} = 0;
			return $this->owner->save();
		}
	}

	public function toggle() {
		if ($this->owner->{$this->attr})
			return $this->disable();
		else
			return $this->enable();
	}

}
?>
