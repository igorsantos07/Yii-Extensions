<?php
class TurbineClientScript extends CClientScript {

	/**
	 * The path to the PUBLIC TurbineCSS compiler.
	 */
	protected $compilerFile = '/css/compiler.php';

	/**
	 * Used to exclude files from the main combo. Useful to maintain CSS order or to not break image paths inside default assets.
	 * It's an array of regular expressions
	 * @var array
	 */
	public $excludeFromCompiling = array();
	
	/**
	 * Extension of default {@link CClientScript::renderHead} that creates a big combo of minified CSS and compiled CSSP files.
	 */
	public function renderHead(&$output) {
		$html = '';

		$origCssFiles = $this->cssFiles;
		$files_per_midia = array();
		foreach($origCssFiles as $css => $media) {
			foreach($this->excludeFromCompiling as $regexp)
				if (preg_match($regexp, $css)) continue 2;

			if (!isset($files_per_midia[$media])) $files_per_midia[$media] = array();

			$files_per_midia[$media][] = preg_replace('/^\/assets\//', '../assets/', $css);
			unset($this->cssFiles[$css]);
		}

		foreach($files_per_midia as $media => $files)
			$html .= $this->compile($files, $media)."\n";

		if($html!=='') {
			$count=0;
			$output=preg_replace('/(<title\b[^>]*>|<\\/head\s*>)/is','<###head###>$1',$output,1,$count);
			if($count)
				$output=str_replace('<###head###>',$html,$output);
			else
				$output=$html.$output;
		}

		parent::renderHead($output);
		$this->cssFiles = $origCssFiles;
	}

	/**
	 * Returns a CSS link tag for the TurbineCSS compiler with the given $files
	 * @param mixed $files an array of CSS/CSSP files (based on Turbine's basedir) or semi-colon-separated list of files
	 * @param string $media [optional] the media attribute for the link tag
	 * @return string the <link> tag
	 */
	public function compile($files, $media = null) {
		$files_str = (is_array($files))? implode(';', $files) : $files;
		return CHtml::cssFile($this->compilerFile.'?files='.$files_str, $media);
	}

}