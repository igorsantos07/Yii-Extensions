<?php
class IntelligentCache extends CBehavior {

	/**
	 * Returns the number of seconds between $timestamp and now. Useful to define {CCache::set} $expire argument using a date instead of number of seconds.
	 * @param mixed $timestamp an integer timestamp or a string in a format that strtotime understands
	 * @return integer
	 */
	public static function expireAt($timestamp) {
		$time = is_numeric($timestamp)? $timestamp : strtotime($timestamp);
		return $time - time();
	}
	
	/**
	 * Works like {CCache::get} and {CCache::set} at the same time, by the use of closures.
	 * This method will get the value from the cache $id if it exists, and if not, it'll call
	 * the specified $value_generator, cache the received value, and then return it.
	 * @param string $id the key identifying the value (to be) cached
	 * @param callback $value_generator something that would generate the value, if it doesn't exist. Can be: a closure; the result of create_funcion(); or an array where the first value is one of the previous and the second is an array of arguments
	 * @param integer $expire [optional] the number of seconds in which the cached value will expire. 0 means never expire.
	 * @param ICacheDependency $dependency dependency of the cached item. If the dependency changes, the item is labeled invalid.
	 * @return mixed the value that's cached - or have been generated and cached. It will log a warning if the caching procedure was unsuccessful.
	 */
	public function getSet($id, $value_generator, $expire = 0, ICacheDependency $dependency = null) {
		$value = $this->owner->get($id);
		if (!$value) {
			$value = is_array($value_generator)?
				call_user_func_array($value_generator[0], $value_generator[1]) :
				$value_generator();
				
			if (!$this->owner->set($id, $value, $expire, $dependency)) {
				Yii::log('warning', 'system.caching.IntelligentCache', "Couldn't cache $id using IntelligentCache::getSet()");
			}
		}

		return $value;
	}

}