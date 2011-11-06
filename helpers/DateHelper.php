<?php
class DateHelper {

	/**
	 * Turns a DD/MM/AAAA into AAAA-MM-DD.
	 * Here just for retrocompatibility.
	 * @param string $date
	 * @return string
	 */
	public static function br2iso($date) {
		return self::convert($date);
	}

	/**
	 * Recebe uma data e converte de um formato para outro.<br />
	 * Outros formatos diferentes dos listados abaixo serão considerados assim: os placeholders (Ymd) com um separador (.-/) entre cada um.
	 * <ul>
	 * <li>BR: DD/MM/AAAA</li>
	 * <li>ISO: AAAA-MM-DD</li>
	 * <li>US/USA: MM/DD/AAAA</li>
	 * <li>EU: DD.MM.AAAA</li>
	 * <li>UNIX: Unix timestamp (formato somente aceito como $to)</li>
	 * </ul>
	 *
	 * @author Igor Santos
	 *
	 * @param string $originalDate
	 * @param string $from='BR' O formato de input; o padrão é o brasileiro
	 * @param string $to='ISO' O formato de output; o padrão é o formato ISO
	 *
	 * @return string Retorna a data formatada, ou nulo se a data gerada for inválida ou ocorrer algum outro erro.
	 */
	public static function convert($originalDate, $from = 'BR', $to = 'ISO') {
		//monta um array no formato (0 => 'd', 1 => 'm', 2 => 'Y', '/' => 'sep') para depois ter as chaves trocadas com os valores e tudo isso virar variável
       	switch (strtoupper($from)) {
			case 'BR'	: $parts = array('d','m','Y', '/' => 'sep'); break;
			case 'ISO'	: $parts = array('Y','m','d', '-' => 'sep'); break;
			case 'US'	:
			case 'USA'	: $parts = array('m','d','Y', '/' => 'sep'); break;
			case 'EU'	: $parts = array('d','m','Y', '.' => 'sep'); break;
			default:
               	$parts = str_split($from);
				unset($parts[3]); //retirando o segundo separador
				$parts[$parts[1]] = 'sep'; unset($parts[1]); //movendo o separador
               	$parts = array_merge($parts); //reorganizando o array
			break;
		}
		//criando as variáveis $m, $d, $Y, $sep
       	extract(array_flip($parts));

		$date = explode($sep, $originalDate);
       	if (sizeof($date == 3) && @checkdate($date[$m], $date[$d], $date[$Y])) {
			$time = mktime(0, 0, 0, $date[$m], $date[$d], $date[$Y]);
			switch (strtoupper($to)) {
				case 'UNIX' : return $time;
				case 'ISO'	: return date('Y-m-d', $time);
				case 'BR'	: return date('d/m/Y', $time);
				case 'US'	:
				case 'USA'	: return date('m/d/Y', $time);
				case 'EU'	: return date('d.m.Y', $time);
			}
       	}
		else {
			return null;
		}
	}

	/**
	 * Verifica se um horário é válido.
	 * @author Igor Santos
	 * @param string $time HH:MM
	 * @return boolean
	 */
	public static function verifyTime($time) {
		$hora = substr($time, 0, 2);
		$minuto = substr($time, 3, 2);
		return $hora >= 0 && $hora <= 23 && $minuto >= 0 && $minuto <= 59;
	}

	/**
	 * Recebe uma string de datetime e retorna o unix timestamp com o horário zerado
	 * @param string $datetime YYYY-MM-DD HH:MM[:SS] ou date('c') (que usa T para separar data de hora e inclui o fuso também)
	 * @return integer
	 */
    public static function datetime2integer($datetime) {
	    list($data,$bla) = preg_split("/\s|T/",$datetime);
	    list($ano,$mes,$dia) = explode("-",$data);
	    return mktime(0,0,0, $mes,$dia,$ano);
    }

}