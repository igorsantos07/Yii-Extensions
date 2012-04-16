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
	 * Recebe uma data e converte de um formato para outro. Se o parâmetro contiver horário, este será mantido no final da string.<br />
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
	 * @param string $from='BR' O formato de input; o padrão é o brasileiro. Pode receber também um formato personalizado, usando os placeholders Ymd, e separadores .-/
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

		$datetime = explode(' ', trim($originalDate));
		$originalDate = $datetime[0];
		if (sizeof($datetime) > 1) {
			$time = explode(':', $datetime[1]);
			$timeFormat = ' H:i:s';
		}
		else {
			$time = array(0,0,0);
			$timeFormat = '';
		}


		$date = explode($sep, $originalDate);
       	if (sizeof($date == 3) && @checkdate($date[$m], $date[$d], $date[$Y])) {
			$timestamp = mktime($time[0], $time[1], $time[2], $date[$m], $date[$d], $date[$Y]);
			switch (strtoupper($to)) {
				case 'UNIX' : return $timestamp;
				case 'ISO'	: return date('Y-m-d'.$timeFormat, $timestamp);
				case 'BR'	: return date('d/m/Y'.$timeFormat, $timestamp);
				case 'US'	:
				case 'USA'	: return date('m/d/Y'.$timeFormat, $timestamp);
				case 'EU'	: return date('d.m.Y'.$timeFormat, $timestamp);
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

    /**
     * Receives a number of seconds and translates it into an array with total of days, hours, minutes and seconds
     * @param integer $seconds
     * @param boolean $show_days [optional] if days should be separated from hours. Defaults to 'true'
     * @return array('d', 'h', 'm', 's')
     */
	public static function seconds2interval($seconds, $show_days = true, $return_string = false, $show_seconds = false) {
		$negative = $seconds < 0;
		$round = $negative? 'ceil' : 'floor';

		$hour = 60*60;
		$h = $round($seconds / $hour);
		$m = $round(($seconds % $hour) / 60);
		$s = ($seconds % $hour) % 60;

		if ($show_days && $h >= 24) {
			$d = $round($h / 24);
			$h = $h % 24;
		}
		else {
			$d = 0;
		}
		if ($negative) $h = '-'.abs($h);

		if ($return_string) {
			$interval = '';
			if ($show_days && $d > 0) $interval .= $d.'d ';
			$interval .= sprintf('%s:%02d', $h, abs($m));
			if ($show_seconds) $interval .= sprintf(':%02d', abs($s));
			return $interval;
		}
		else
			return compact('d', 'h', 'm', 's');
	}

	/**
	 * Receives a time interval in array or string and returns the number of seconds.
	 * @param mixed $interval an array like ('d', 'h', m', 's') or a string like "20d 23:59:59", where the seconds and the days are optional parts.
	 * @return integer
	 */
	public static function interval2seconds($interval) {
		if (is_string($interval)) {
			$parts = explode(' ', $interval);
			$interval = array();
			if (sizeof($interval) > 1) {
				$interval['d'] = rtrim($parts[0], 'd');
				unset($parts[0]);
			}
			$time = explode(':', current($parts));
			$interval['h'] = $time[0];
			$interval['m'] = $time[1];
			if (isset($time[2])) $interval['s'] = $time[2];
		}

		if (!isset($interval['d'])) $interval['d'] = 0;
		if (!isset($interval['h'])) $interval['h'] = 0;
		if (!isset($interval['m'])) $interval['m'] = 0;
		if (!isset($interval['s'])) $interval['s'] = 0;

		$hour = 60*60;
		$seconds = ($interval['m'] * 60) + ($interval['h'] * $hour);
		if (isset($interval['s'])) $seconds += $interval['s'];
		if (isset($interval['d'])) $seconds += $interval['d'] * (24*$hour);

		return $seconds;
	}

	/**
	 * Returns the week number for weeks starting on Sunday, not on Monday as date('W') expects
	 * @params integer $timestamp an UNIX timestamp, as with date()
	 */
	public static function weekNumber($timestamp) {
		list($weekday, $week) = explode(' ', date('w W', $timestamp));
		if ($weekday == 0)
			return $week+1;
		else
			return $week;
	}

}