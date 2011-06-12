<?php
/**
 * Classe de utilidade para arrays
 * @author Igor Santos
 */
abstract class ArrayHelper {

	/**
	 * Variável usada pelo método {@link compare}. É a chave que será usada para fazer as comparações.
	 * @var string
	 * @static
	 */ public static $compare_key = '';

	 const REMOVE_WHITELIST = 'w';
	 const REMOVE_BLACKLIST = 'b';

	/**
	 * Remove todos os valores vazios (empty()) de array e, opcionalmente, reindexa as chaves.
	 * @param array $dirty_array O array em questão
	 * @param boolean $reindexar=true Se vai reindexar as chaves numéricas ou não
	 * @return array O array final, limpo.
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
	 * Remove todas as chaves de $crowded_array, exceto as que estiverem na $whitelist e o retorna.
	 * Não altera o array recebido!
	 * @param array $crowded_array
	 * @param mixed $whitelist uma string que contenha o nome de somente uma chave, ou um array de nomes de chaves
	 * @return array
	 * @see blacklist
	 */
	public static function whitelist(array $crowded_array, $whitelist) {
        return self::remove($crowded_array, $whitelist, self::REMOVE_WHITELIST);
	}

	/**
	 * Remove todas as chaves de $crowded_array que estiverem na $blacklist e o retorna.
	 * Não altera o array recebido!
	 * @param array $messy_array
	 * @param mixed $blacklist uma string que contenha o nome de somente uma chave, ou um array de nomes de chaves
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
	 * Método criado para facilitar comparações com {@link usort} e {@link uasort}.
	 *
	 * Essas funções recebem como segundo argumento o nome de uma função personalizada para efetuar
	 * a ordenação. Esse método necessita de uma string na variável estática {@link $compare_key},
	 * e vai efetuar a comparação baseado no valor dessa chave em cada elemento do array que receber.
	 * Exemplo:
	 * <code>
	 * $zoneado = array(
	 * array('id' => 2, 'nome' => 'Zebra'),
	 * array('id' => 3, 'nome' => 'Cachorro'),
	 * array('id' => 4, 'nome' => 'Elefante')
	 * );
	 * BDM_Util_Array::$compare_key = 'nome';
	 * usort($zoneado, 'BDM_Util_Array::compare');
	 * </code>
	 *
	 * @param array $a
	 * @param array $b
	 * @return integer
	 */
	public static function compare(array $a, array $b) {
		if (empty(self::$compare_key)) throw new Exception('Necessário colocar a chave que será usada na comparação dos arrays na propriedade BDM_Util_Array::$compare_key');

		if ($a[self::$compare_key] == $b[self::$compare_key])
			return 0;
		else
			return ($a[self::$compare_key] < $b[self::$compare_key])? -1 : 1;
	}
}
