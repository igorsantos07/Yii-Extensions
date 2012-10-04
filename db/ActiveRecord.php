<?php
class ActiveRecord extends CActiveRecord {

	public function isEmpty() {
		//testing attributes, the easier place to find something
		foreach ($this->attributes as $attr)
			if (!empty($attr)) return false;

		//if didn't returned, let's try custom properties
		$obj = new ReflectionObject($this);
		foreach($obj->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
			if ($property->getDeclaringClass()->name == $obj->name) {
				$value = $property->getValue($this);
				if (!empty($value)) return false;
			}
		}

		//nothing? so, trying getters
		foreach($obj->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
			if ($method->getDeclaringClass()->name == $obj->name &&
				strpos($method->name, 'get') === 0 &&
				$method->getNumberOfRequiredParameters() == 0) {
					$value = $method->invoke($this);
					if (!empty($value)) return false;
				}
		}

		return true;
	}

	public function __get($name) {
		$upper = strtoupper($name);

		if ($this->hasAttribute($upper) || $this->hasRelated($upper))
			return parent::__get($upper);
		else
			return parent::__get($name);
	}

	public function __set($name, $value) {
		$upper = strtoupper($name);

		if ($this->hasAttribute($upper) || $this->hasRelated($upper))
			return parent::__set($upper, $value);
		else
			return parent::__set($name, $value);
	}

}