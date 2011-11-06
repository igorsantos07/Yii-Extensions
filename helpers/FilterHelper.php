<?php

/**
 * Classe para filtrar os inputs vindos das variáveis superglobais.<br />
 * Mascara e simplifica os filtros padrão do PHP, expandindo para as opções comuns utilizadas no site,
 * como celular, cep, etc.
 *
 * <b>Exemplos de uso:</b>
 * <code>
 * //formato rápido
 * if ($_POST['email']) {
 *		$dado = FilterHelper::POST()->validar_email('email');
 * }
 * //formato longo
 * if ($_POST) {
 *		$filtro = FilterHelper::POST();
 *		$email = $filtro->validar_email('email');
 *		$valor = $filtro->validar_data('nascimento');
 *		$site = $filtro->limpar_url('site');
 * }
 * </code>
 *
 * <b>Modo de debug</b> (exibe as funções de filtro do PHP usadas):
 * <code>FilterHelper::$debug = true;</code>
 *
 * <b>Instruções para adicionar novos filtros:</b><br />
 * 1. criar uma constante TIPO_XX<br />
 * 2. editar os switches dos métodos validar() e limpar() conforme necessário<br />
 * 3. criar métodos validar_XX() e limpar_XX() conforme necessário<br />
 *
 * @author Igor Santos
 */
class FilterHelper {

	/** Filtrar $_POST */		const POST		= INPUT_POST;
	/** Filtrar $_GET */		const GET		= INPUT_GET;
	/** Filtrar $_COOKIE */		const COOKIE	= INPUT_COOKIE;
	/** Filtrar $_SERVER */		const SERVER	= INPUT_SERVER;
	/** Filtrar $_ENV */		const ENV		= INPUT_ENV;
	/** Filtrar variáveis */	const VARIAVEL	= 'var';

	/** Filtrar strings */				const TIPO_STRING	= 's';
	/** Filtrar números inteiros */		const TIPO_INTEIRO	= 'i';
	/** Filtrar números decimais */		const TIPO_DECIMAL	= 'd';
	/** Filtrar a partir de ExpReg */	const TIPO_REGEXP	= 'r';
	/** Filtrar datas (DD/MM/AAAA) */	const TIPO_DATA		= 'D';
	/** Filtrar horários */				const TIPO_HORA		= 'H';
	/** Filtrar data e hora juntos */	const TIPO_DATAHORA	= 'DH';
	/** Filtrar telefone com DDD */		const TIPO_TELEFONE	= 'T';
	/** Filtrar CEPs */					const TIPO_CEP		= 'C';
	/** Filtrar e-mails */				const TIPO_EMAIL	= 'E';
	/** Filtrar URLs */					const TIPO_URL		= 'U';
	/** Filtrar IPs */					const TIPO_IP		= 'I';

	/** Validação de booleanos */	const VALIDA_BOOL	= 'b';

	/** Limpeza de strings encodadas para URL */	const LIMPA_URL_ENCODED		= 'UE';
	/** Limpeza de caracteres especiais */			const LIMPA_CARAC_HTML		= 'CE';
	/** Limpezade caracteres especiais */			const LIMPA_PERSONALIZADO	= 'G';

	/**
	 * Limite inferior para o ano, na validação da data
	 * @var integer */ const ANO_MIN = 1900;

	/**
	 * Qual será o grupo a filtrar (se vai filtrar uma variável diretamente ou um input vindo de uma superglobal).
	 * Aceita somente as constantes da classe.
	 * @var const */ protected $grupo;

	/**
	 * O nome da variável superglobal a ser filtrada.<br />
	 * Se estiver usando o grupo VARIAVEL, isso aqui conterá NULL
	 * @var string */ protected $variavel = null;

	/**
	 * Se true, irá imprimir ao término da classe os filtros que rodaram
	 * @var boolean */ public static $debug = false;

	/**
	 * Contém o output de debug
	 * @var string */ protected $output_debug = '';

/* --------------- construtores e afins genéricos --------------- */

	/**
	 * Cria um novo objeto de filtro, de acordo com as constantes da classe
	 * @param string $grupo
	 */
	public function __construct($grupo = self::VARIAVEL) {
		$this->set_grupo($grupo);
	}

	/** @return FilterHelper */	public static function POST()		{ return new self(self::POST);		}
	/** @return FilterHelper */	public static function GET()		{ return new self(self::GET);		}
	/** @return FilterHelper */	public static function COOKIE()		{ return new self(self::COOKIE);	}
	/** @return FilterHelper */	public static function SERVER()		{ return new self(self::SERVER);	}
	/** @return FilterHelper */	public static function ENV()		{ return new self(self::ENV);		}
	/** @return FilterHelper */	public static function VARIAVEL()	{ return new self(self::VARIAVEL);	}

	public function  __destruct() {
		if (self::$debug) echo '<pre>',$this->output_debug,'</pre>';
	}

	/**
	 * Setter para {@link $tipo}. Só aceita como argumento as constantes da classe.
	 * @param const $novo_grupo
	 */
	public function set_grupo($novo_grupo) {
		if (!in_array($novo_grupo, array(self::POST, self::GET, self::COOKIE, self::SERVER, self::ENV, self::VARIAVEL)))
			throw new InvalidFilterException();
		else {
			$this->grupo = $novo_grupo;
			switch ($novo_grupo) {
				case self::POST:		$this->variavel =& $_POST;		break;
				case self::GET:			$this->variavel =& $_GET;		break;
				case self::COOKIE:		$this->variavel =& $_COOKIE;	break;
				case self::SERVER:		$this->variavel =& $_SERVER;	break;
				case self::ENV:			$this->variavel =& $_ENV;		break;
				case self::VARIAVEL:	$this->variavel = array();		break;
			}
		}
	}

	/**
	 * Mascara as funções filter_var() e filter_input() num método só
	 * @param string $var
	 * @param string $filtro
	 * @param array $opcoes
	 */
	protected function filtra_grupo($var, $filtro, array $opcoes = null) {
		if ($this->grupo === self::VARIAVEL) {
			$this->variavel[$var] = $var;
			if (self::$debug) $this->output_debug .= 'filter_var("'.$var.'", '.$filtro.', '.strtr(var_export($opcoes, true), array("\n" => '', "  " => '', '\\\\' => '\\')).")\n";
			return filter_var($var, $filtro, $opcoes);
		}
		else {
			if (self::$debug) $this->output_debug .= 'filter_input('.$this->grupo.', "'.$var.'", '.$filtro.', '.strtr(var_export($opcoes, true), array("\n" => '', "  " => '', '\\\\' => '\\')).")\n";
			return filter_input($this->grupo, $var, $filtro, $opcoes);
		}
	}


/* --------------- validadores --------------- */

	/**
	 * Diz se um valor é válido ou não, de acordo com o tipo.
	 * Valida o formato do valor, e depois chama {@link pos_validacao}, que valida o valor propriamente dito.
	 * Se a variável que tentou-se validar não existe, retorna null.
	 *
	 * @param const $tipo uma constante da classe, TIPO_*
	 * @param string $var o nome do campo/variável a testar
	 * @param array $opcoes=null [opcional] array de opções para o filtro em questão; flags devem estar juntas (flag | flag) no
	 * @return boolean
	 */
	protected function validar($tipo, $var, array $opcoes = null) {
		if (!isset($this->variavel[$var]) && $this->grupo != self::VARIAVEL) return null;
		switch ($tipo) {
			case self::TIPO_STRING	: $filtrado = $this->filtra_grupo($var, FILTER_UNSAFE_RAW);					break;
			case self::TIPO_INTEIRO	: $filtrado = $this->filtra_grupo($var, FILTER_VALIDATE_INT, $opcoes);		break;
			case self::TIPO_DECIMAL	: $filtrado = $this->filtra_grupo($var, FILTER_VALIDATE_FLOAT, $opcoes);	break;
			case self::TIPO_EMAIL	: $filtrado = $this->filtra_grupo($var, FILTER_VALIDATE_EMAIL);				break;
			case self::TIPO_URL		: $filtrado = $this->filtra_grupo($var, FILTER_VALIDATE_URL, $opcoes);		break;
			case self::TIPO_IP		: $filtrado = $this->filtra_grupo($var, FILTER_VALIDATE_IP, $opcoes);		break;
			case self::VALIDA_BOOL	: $filtrado = $this->filtra_grupo($var, FILTER_VALIDATE_BOOLEAN, array('flags' => FILTER_NULL_ON_FAILURE));	break;

			case self::TIPO_DATA	: $filtrado = $this->filtra_grupo($var, FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^\d{2}\/\d{2}\/\d{4}$/', $var)));					break;
			case self::TIPO_HORA	: $filtrado = $this->filtra_grupo($var, FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^\d{2}:\d{2}(\:\d{2})?$/', $var)));					break;
			case self::TIPO_DATAHORA: $filtrado = $this->filtra_grupo($var, FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}(\:\d{2})?$/')));	break;
			case self::TIPO_TELEFONE: $filtrado = $this->filtra_grupo($var, FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^\(\d{2}\)\s\d{4}[-\.\s]{0,1}\d{4}$/')));			break;
			case self::TIPO_CEP		: $filtrado = $this->filtra_grupo($var, FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^\d{5}-\d{3}$/', $var)));							break;
			case self::TIPO_REGEXP	: $filtrado = $this->filtra_grupo($var, FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => $opcoes['regexp'], $var)));							break;

			case self::LIMPA_CARAC_HTML		:
			case self::LIMPA_PERSONALIZADO	:
			case self::LIMPA_URL_ENCODED	: throw new InvalidFilterException('Tipo de operação incorreta! '.$tipo, InvalidFilterException::OPCAO_INVALIDA); break;
		}

		return $this->pos_validacao($tipo, $filtrado, $opcoes, $var);
	}

	/**
	 * Depois de validar o formato (em {@link validar}, esse método é usado para validar o valor, literalmente.
	 *
	 * @param const $tipo uma constante da classe, TIPO_*
	 * @param string $valor_filtrado o valor, depois de passar pelo filtro inicial
	 * @param array $opcoes o array de opções utilizado no filtro
	 * @param string $var a variável a ser filtrada
	 * @return boolean
	 */
	protected function pos_validacao($tipo, $valor_filtrado, $opcoes, $var) {
		switch ($tipo) {
			case self::TIPO_STRING : return (bool)strlen($valor_filtrado); break;

			case self::VALIDA_BOOL :
				if (is_null($valor_filtrado) && strlen($this->variavel[$var]) === 0) return true;
				else return is_bool($valor_filtrado);
			break;

			case self::TIPO_INTEIRO	:
				$octal = $hex = $int = false;
				if ($opcoes['flags'] & FILTER_FLAG_ALLOW_OCTAL)	$octal	= $valor_filtrado === octdec($this->variavel[$var]);
				if ($opcoes['flags'] & FILTER_FLAG_ALLOW_HEX)	$hex	= $valor_filtrado === hexdec($this->variavel[$var]);
				$int = $valor_filtrado === (int)$this->variavel[$var];
				return $octal || $hex || $int;
			break;

			case self::TIPO_DECIMAL	:
					if ($opcoes['flags'] & FILTER_FLAG_ALLOW_THOUSAND) return $valor_filtrado === (float)str_replace(',', '', $this->variavel[$var]);
					else return $valor_filtrado === (float)$this->variavel[$var];
			break;

			case self::TIPO_DATA	: return $this->validacao_numerica_data($valor_filtrado);	break;
			case self::TIPO_HORA	: return $this->validacao_numerica_hora($valor_filtrado);	break;
			case self::TIPO_DATAHORA: return $this->validacao_numerica_data(strtok($valor_filtrado, ' ')) && $this->validacao_numerica_hora(strtok(' ')); break;

			default					: return $valor_filtrado === $this->variavel[$var];			break;
		}
	}

	/**
	 * Recebe uma data no formato DD/MM/AAAA e retorna se o valor é válido ou não.<br />
	 * É diferente da primeira validação porque aqui verificamos se não é um dia ou ano absurdo.
	 * Na primeira é verificado somente o formato.
	 * @param string $data
	 * @return boolean
	 */
	protected function validacao_numerica_data($data) {
		$d = strtok($data, '/'); $m = strtok('/'); $a = strtok('/');
		if ($a < self::ANO_MIN) return false;
		else return checkdate($m, $d, $a);
	}

	/**
	 * Valida um horário no formato HH:MM[:SS], verificando não o formato, mas se é uma hora de verdade.<br />
	 * Aceita intervalos entre 00:00:00 e 23:59:59.
	 * @param string $hora
	 * @return boolean
	 */
	protected function validacao_numerica_hora($hora) {
		$h = strtok($hora, ':'); $m = strtok(':'); $s = strtok(':');
		return (($h >= 0 && $h <= 23) &&
				($m >= 0 && $m <= 59) &&
				(is_null($s) || ($s >= 0 && $s <= 59))
		);
	}

	/* ----- métodos com opções ----- */

	/**
	 * Valida números inteiros.<br />
	 * Atenção: -10 é um inteiro, mas +0 ou -0 só são considerados válidos como decimais.
	 * @param boolean $octal se permite valores octais, que começam com 0 (ex: 0563)
	 * @param boolean $hex se permite valores hexadecimais, que começam com 0x (ex: 0xFA8)
	 * @param boolean $min_range número mínimo, inclusive
	 * @param boolean $max_range número máximo, inclusive
	 * @return boolean
	 */
	public function validar_inteiro($var, $min_range = null, $max_range = null, $octal = false, $hex = false) {
		$opcoes = array('flags' => 0);
		if ($octal)		$opcoes['flags'] = $opcoes['flags'] | FILTER_FLAG_ALLOW_OCTAL;
		if ($hex)		$opcoes['flags'] = $opcoes['flags'] | FILTER_FLAG_ALLOW_HEX;
		if ($min_range)	$opcoes['options']['min_range'] = $min_range;
		if ($max_range)	$opcoes['options']['max_range'] = $max_range;

		return $this->validar(self::TIPO_INTEIRO, $var, $opcoes);
	}

	/**
	 * Valida decimais.
	 * @param boolean $separador_milhar se pode usar uma vírgula para separar os milhares
	 * @return boolean
	 */
	public function validar_decimal($var, $separador_milhar = false) {
		$opcoes = array('flags' => 0);
		if ($separador_milhar) $opcoes['flags'] = $opcoes['flags'] | FILTER_FLAG_ALLOW_THOUSAND;

		return $this->validar(self::TIPO_DECIMAL, $var, $opcoes);
	}

	/**
	 * Valida URLs. Devem, obrigatoriamente, possuir o protocolo.
	 * @param boolean $path se é obrigatório um path após o endereço do site
	 * @param boolean $query se é obrigatório o uso de uma querystring
	 * @return boolean
	 */
	public function validar_url($var, $path = false, $query = false) {
		$opcoes = array('flags' => 0);
		if ($path)	$opcoes['flags'] = $opcoes['flags'] | FILTER_FLAG_PATH_REQUIRED;
		if ($query)	$opcoes['flags'] = $opcoes['flags'] | FILTER_FLAG_QUERY_REQUIRED;

		return $this->validar(self::TIPO_URL, $var, $opcoes);
	}

	/**
	 * Valida um IP.
	 * @param integer $ipv Se validará somente IPV4 [4], IPV6 [6], ou os dois [null]
	 * @param boolean $private_range Se permitirá IPs privados
	 * @param boolean $reserved_range Se permitirá IPs reservados
	 * @return boolean
	 */
	public function validar_ip($var, $ipv = null, $private_range = false, $reserved_range = false) {
		$opcoes = array('flags' => 0);
		if		($ipv == 4)		$opcoes['flags'] = $opcoes['flags'] | FILTER_FLAG_IPV4;
		elseif	($ipv == 6)		$opcoes['flags'] = $opcoes['flags'] | FILTER_FLAG_IPV6;
		elseif	($ipv !== null)	throw new InvalidFilterException('Tentativa de filtrar IPV'.$ipv.'. As opções válidas são IPV4, IPV6, ou NULL (filtra pelos dois).', 1);

		if (!$private_range)	$opcoes['flags'] = $opcoes['flags'] | FILTER_FLAG_NO_PRIV_RANGE;
		if (!$reserved_range)	$opcoes['flags'] = $opcoes['flags'] | FILTER_FLAG_NO_RES_RANGE;

		return $this->validar(self::TIPO_IP, $var, $opcoes);
	}

	public function validar_regexp($var, $regexp) {
		return $this->validar(self::TIPO_REGEXP, $var, array('options' => array('regexp' => $regexp)));
	}

	/* ----- métodos sem opções ----- */
	/**
	 * Valida, basicamente, nada. Verifica se a string tem mais de um caracter.
	 * @return boolean */
	public function validar_string($var) { return $this->validar(self::TIPO_STRING, $var); }

	/**
	 * Valida telefon ou celular.<br /> Aceita o formato (88) 8888-8888
	 * @return boolean */
	public function validar_booleano($var) { return $this->validar(self::VALIDA_BOOL, $var); }

	/**
	 * Aceita um email, de acordo com as regras da RFC.
	 * @return boolean */
	public function validar_email($var) { return $this->validar(self::TIPO_EMAIL, $var); }

	/**
	 * Valida data.<br /> Aceita o formato DD/MM/AAAA
	 * @return boolean */
	public function validar_data($var) { return $this->validar(self::TIPO_DATA, $var); }

	/**
	 * Valida hora.<br /> Aceita o formato HH:MM[:SS]
	 * @return boolean */
	public function validar_hora($var) { return $this->validar(self::TIPO_HORA, $var); }

	/**
	 * Valida data e hora, juntos.<br /> Aceita o formato DD/MM/AAAA HH:MM[:SS]
	 * @return boolean */
	public function validar_data_hora($var) { return $this->validar(self::TIPO_DATAHORA, $var); }

	/**
	 * Valida telefone ou celular.<br /> Aceita o formato (88) 8888-8888
	 * @return boolean */
	public function validar_telefone($var) { return $this->validar(self::TIPO_TELEFONE, $var); }

	/**
	 * Valida CEP.<br /> Aceita o formato 88888-888
	 * @return boolean */
	public function validar_cep($var) { return $this->validar(self::TIPO_CEP, $var); }

	/* ----- aliases ----- */
	/**
	 * Valida decimais. Alias de {@link validar_decimal}.
	 * @param boolean $separador_milhar se pode usar uma vírgula para separar os milhares
	 * @return boolean */
	public function validar_float($var, $separador_milhar = false) { return $this->validar_decimal($var, $separador_milhar); }

	/**
	 * Valida celular e telefone. Alias de {@link validar_telefone}.<br />Aceita o formato (88) 8888-8888
	 * @return boolean */
	public function validar_celular($var) { return $this->validar_telefone($var); }


/* --------------- sanitizadores --------------- */

	/**
	 * Retorna o valor sanitizado da variável
	 * @param const $tipo uma constante da classe, TIPO_*
	 * @param string $var o nome do campo/variável a testar
	 * @return string
	 */
	protected function limpar($tipo, $var, array $opcoes = null) {
		switch ($tipo) {
			case self::TIPO_STRING	: return $this->filtra_grupo($var, FILTER_SANITIZE_STRING, $opcoes);		break;
			case self::TIPO_INTEIRO	: return $this->filtra_grupo($var, FILTER_SANITIZE_NUMBER_INT);				break;
			case self::TIPO_DECIMAL	: return $this->filtra_grupo($var, FILTER_SANITIZE_NUMBER_FLOAT, $opcoes);	break;
			case self::TIPO_EMAIL	: return $this->filtra_grupo($var, FILTER_SANITIZE_EMAIL);					break;
			case self::TIPO_URL		: return $this->filtra_grupo($var, FILTER_SANITIZE_URL);					break;

			case self::LIMPA_URL_ENCODED	: return $this->filtra_grupo($var, FILTER_SANITIZE_ENCODED);				break;
			case self::LIMPA_PERSONALIZADO	: return $this->filtra_grupo($var, FILTER_UNSAFE_RAW, $opcoes);				break;
			case self::LIMPA_CARAC_HTML		: return $this->filtra_grupo($var, FILTER_SANITIZE_SPECIAL_CHARS, $opcoes);	break;


			case self::TIPO_DATA	:
			case self::TIPO_HORA	:
			case self::TIPO_DATAHORA:
			case self::TIPO_TELEFONE:
			case self::TIPO_CEP		: //para habilitar sanitização para essas constantes TIPO_* aqui,
			case self::TIPO_IP		: //é necessário usar algum tipo de substituição por expressões regulares... por enquanto não.
			case self::TIPO_REGEXP	: throw new InvalidFilterException('Tipo de operação incorreta! '.$tipo, InvalidFilterException::NAO_IMPLEMENTADO); break;

			case self::VALIDA_BOOL	: throw new InvalidFilterException('Tipo de operação incorreta! '.$tipo, InvalidFilterException::OPCAO_INVALIDA); break;
		}
	}

	/* ----- métodos com opções ----- */

	/**
	 * Limpa tags HTML. Opcionalmente, também pode encodar aspas.<br />
	 * Para limpar outras famílias de caracteres, use {@link limpar_personalizado}
	 * @param string $var
	 * @param boolean $encoda_aspas se aspas serão encodadas também
	 * @return string
	 */
	public function limpar_string($var, $encoda_aspas = true) {
		$opcoes = array('flags' => 0);
		if (!$encoda_aspas) $opcoes['flags'] = $opcoes['flags'] | FILTER_FLAG_NO_ENCODE_QUOTES;

		return $this->limpar(self::TIPO_STRING, $var, $opcoes);
	}

	/**
	 * Limpa números de ponto flutuante. Permite opcionalmente pontos, vírgulas e sinais para números científicos.
	 * @param string $var
	 * @param boolean $milhar permite vírgulas para separar os milhares
	 * @param boolean $fracional permite ponto para números decimais
	 * @param boolean $cientifico permite "e" ou "E" para indicar números científicos
	 * @return string
	 */
	public function limpar_float($var, $milhar = false, $fracional = false, $cientifico = false) {
		$opcoes = array('flags' => 0);
		if ($milhar)		$opcoes['flags'] = $opcoes['flags'] | FILTER_FLAG_ALLOW_THOUSAND;
		if ($fracional)		$opcoes['flags'] = $opcoes['flags'] | FILTER_FLAG_ALLOW_FRACTION;
		if ($cientifico)	$opcoes['flags'] = $opcoes['flags'] | FILTER_FLAG_ALLOW_SCIENTIFIC;

		return $this->limpar(self::TIPO_DECIMAL, $var, $opcoes);
	}

	/**
	 * Escapa aspas, &lt; &gt;, & e caracteres com valor numérico menor que 32, transformando HTML em texto legível.<br />
	 * Opcionalmente pode retirar os caracteres menores que 32 e maiores que 127, e transformar outros caracteres em entidades HTML.
	 * @param string $var
	 * @param boolean $entidades encoda os caracteres de valor numérico acima de 127 (como caracteres acentuados e adiante)
	 * @param boolean $strip_low retira os caracteres de valor numérico abaixo de 32 (como caracteres de marcação, quebra de linha, etc)
	 * @param boolean $strip_high retira os caracteres de valor numérico acima de 127 (como caracteres acentuados e adiante)
	 * @return string
	 */
	public function limpar_caracteres_html($var, $entidades = true, $strip_low = false, $strip_high = false) {
		$opcoes = array('flags' => 0);
		if ($entidades)		$opcoes['flags'] = $opcoes['flags'] | FILTER_FLAG_ENCODE_HIGH;
		if ($strip_low)		$opcoes['flags'] = $opcoes['flags'] | FILTER_FLAG_STRIP_LOW;
		if ($strip_high)	$opcoes['flags'] = $opcoes['flags'] | FILTER_FLAG_STRIP_HIGH;

		return $this->limpar(self::LIMPA_CARAC_HTML, $var, $opcoes);
	}

	/**
	 * Não limpa nada por si só. Serve para efetuar operações de sanitização de acordo com os argumentos fornecidos.
	 * @param string $var
	 * @param boolean $strip_low retira os caracteres de valor numérico abaixo de 32 (como caracteres de marcação, quebra de linha, etc)
	 * @param boolean $strip_high retira os caracteres de valor numérico acima de 127 (como caracteres acentuados e adiante)
	 * @param boolean $encode_low encoda os caracteres de valor numérico abaixo de 32 (como caracteres de marcação, quebra de linha, etc)
	 * @param boolean $encode_high encoda os caracteres de valor numérico acima de 127 (como caracteres acentuados e adiante)
	 * @param boolean $encode_amp encoda ampersand, vulgo "E comercial" (&)
	 * @return string
	 */
	public function limpar_personalizado($var, $strip_low = false, $strip_high = false, $encode_low = false, $encode_high = false, $encode_amp = false) {
		$opcoes = array('flags' => 0);
		if ($strip_low)		$opcoes['flags'] = $opcoes['flags'] | FILTER_FLAG_STRIP_LOW;
		if ($strip_high)	$opcoes['flags'] = $opcoes['flags'] | FILTER_FLAG_STRIP_HIGH;
		if ($encode_low)	$opcoes['flags'] = $opcoes['flags'] | FILTER_FLAG_ENCODE_LOW;
		if ($encode_high)	$opcoes['flags'] = $opcoes['flags'] | FILTER_FLAG_ENCODE_HIGH;
		if ($encode_amp)	$opcoes['flags'] = $opcoes['flags'] | FILTER_FLAG_ENCODE_AMP;

		return $this->limpar(self::LIMPA_PERSONALIZADO, $var, $opcoes);
	}

	/* ----- métodos sem opções ----- */
	public function limpar_inteiro($var)		{ return $this->limpar(self::TIPO_INTEIRO, $var); }
	public function limpar_email($var)			{ return $this->limpar(self::TIPO_EMAIL, $var); }
	public function limpar_url($var)			{ return $this->limpar(self::TIPO_URL, $var); }
	public function limpar_url_encoded($var)	{ return $this->limpar(self::LIMPA_URL_ENCODED, $var); }

	/* ----- aliases ----- */
	public function limpar_html($var, $encoda_aspas = true) { return $this->limpar_string($var, $encoda_aspas); }
	public function limpar_decimal($var, $milhar = false, $fracional = true, $cientifico = false) { return $this->limpar_float($var, $milhar, $fracional, $cientifico); }
}

/**
 * Possíveis códigos de erro:
 * 0 - erro desconhecido
 * 1 - opção inválida
 * 2 - não implementado
 */
class InvalidFilterException extends Exception {
	const DESCONHECIDO		= 0;
	const OPCAO_INVALIDA	= 1;
	const NAO_IMPLEMENTADO	= 2;

	public function  __construct($message = '', $code = 0, $previous = null) {
		parent::__construct($message, $code, $previous);
	}
}