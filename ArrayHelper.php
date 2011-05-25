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
	 * Remove todas as chaves de $crowded_array, exceto as que estiverem na $whitelist.
	 * @param array $crowded_array
	 * @param mixed $whitelist uma string que contenha o nome de somente uma chave, ou um array de nomes de chaves
	 * @return array
	 */
	public static function whitelist(array $crowded_array, $whitelist) {
        if (!is_array($whitelist)) $whitelist = array($whitelist);
        
		$remove = array();
		foreach ($crowded_array as $prop => $value)
			if (!in_array($prop, $whitelist)) $remove[] = $prop;

		foreach ($remove as $prop)
			unset($crowded_array[$prop]);

		return $crowded_array;
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
		if (empty(self::$compare_key)) throw new BDM_UtilException ('Necessário colocar a chave que será usada na comparação dos arrays na propriedade BDM_Util_Array::$compare_key');

		if ($a[self::$compare_key] == $b[self::$compare_key])
			return 0;
		else
			return ($a[self::$compare_key] < $b[self::$compare_key])? -1 : 1;
	}
}
