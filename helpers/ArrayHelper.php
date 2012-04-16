<?php
/**
 * @author Igor Santos
 */
abstract class ArrayHelper {

	/**
	 * Variable used by {@link compare} method. It's the key that's going to be used for comparisons.
	 * @var string
	 * @static
	 */ public static $compare_key = '';

	 const REMOVE_WHITELIST = 'w';
	 const REMOVE_BLACKLIST = 'b';

	/**
	 * Erases all empty() values from the $dirty_array and, optionally, reindexes the keys, and returns the final array.
	 * @param array $dirty_array
	 * @param boolean $reindexar [optional] If it's needed to reindex the keys; defaults to true.
	 * @return array
	 */
	public static function clear(array $dirty_array, $reindexar = true) {
		$remove = array();
		foreach ($dirty_array as $key => $value)
			if (empty($value)) $remove[] = $key;

		foreach($remove as $key)
			unset($dirty_array[$key]);

		if ($reindexar) $dirty_array = array_merge($dirty_array);

		return $dirty_array;
	}

	/**
	 * Erases all the keys from $crowded_array, except the ones that are in the $whitelist, and returns the final array.
	 * @param array $crowded_array
	 * @param mixed $whitelist a string with one key, or an array with many
	 * @return array
	 * @see blacklist
	 */
	public static function whitelist(array $crowded_array, $whitelist) {
        return self::remove($crowded_array, $whitelist, self::REMOVE_WHITELIST);
	}

	/**
	 * Erases all the keys from $crowded_array that are in the $blacklist, and returns the final array.
	 * @param array $messy_array
	 * @param mixed $blacklist a string with one key, or an array with many
	 * @return array
	 * @see whitelist
	 */
	public static function blacklist(array $messy_array, $blacklist) {
        return self::remove($messy_array, $blacklist, self::REMOVE_BLACKLIST);
	}

	/**
	 * Generic method to envelope black and whitelist functionalities. To further documentation about theses, see {@link whitelist} and {@link blacklist}.
	 * @param array $array the array being filtered
	 * @param mixed $list a string (or an array of strings) of keys to be removed/kept
	 * @param const $type a const REMOVE_WHITELIST or REMOVE_BLACKLIST
	 * @return array
	 */
	private static function remove(array $array, $list, $type) {
        if (!is_array($list)) $list = array($list);

		$remove = array();
		switch($type) {
			case self::REMOVE_BLACKLIST:
				foreach ($list as $blacklisted)
					if (array_key_exists ($blacklisted, $array)) $remove[] = $blacklisted;
			break;
			case self::REMOVE_WHITELIST:
				foreach ($array as $prop => $value)
					if (!in_array($prop, $list)) $remove[] = $prop;
			break;
		}

		foreach ($remove as $prop)
			unset($array[$prop]);

		return $array;
	}

	/**
	 * Method to make the use of {@link usort} e {@link uasort} a little easier with arrays of arrays.
	 *
	 * Those functions receive as the second argument a custom ordering function. You should place the
	 * key whose values will be used as order-key in {@link $compare_key} and give this method to usort().
	 *
	 * Example:
	 * <code>
	 * //ordering an array of arrays by the 'name' key
	 * $messy = array(
	 * array('id' => 2, 'name' => 'Zebra'),
	 * array('id' => 3, 'name' => 'Dog'),
	 * array('id' => 4, 'name' => 'Elephant')
	 * );
	 * ArrayHelper::$compare_key = 'nome';
	 * usort($messy, 'ArrayHelper::compare');
	 * </code>
	 *
	 * @param array $a
	 * @param array $b
	 * @return integer
	 */
	public static function compare(array $a, array $b) {
		if (empty(self::$compare_key)) throw new Exception('Necessário colocar a chave que será usada na comparação dos arrays na propriedade ArrayHelper::$compare_key');

		if ($a[self::$compare_key] == $b[self::$compare_key])
			return 0;
		else
			return ($a[self::$compare_key] < $b[self::$compare_key])? -1 : 1;
	}

	/**
	 * Unsets values from the array using the value, instead of the key.
	 * If the given $value appears in the $array more than one time, all of them will be erased.
	 * @param array $array the array that should be searched
	 * @param mixed $value the value that's going to be erased
	 * @param boolean $strict [optional] if the search should be type-strict. Defaults to false.
	 * @return null
	 */
	public static function unsetByValue(array &$array, $value, $strict = false) {
		$keys = array_keys($array, $value, $strict);
		if (is_array($keys))
			foreach($keys as $key) unset($array[$key]);
	}
}
