<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * A column that displays an image that changes according to the value of the field, and has a link to toggle it on of off.
 * The attribute is defaulted to "status"
 *
 * @author igoru
 */
class BooleanButtonColumn extends CLinkColumn {

	public $name = 'status';

	public $type = 'html';

	/**
	 * A simple value; it won't be evaluated. Should be a boolean valid value.
	 * @var boolean
	 */
	public $value;

	/**
	 * The button label. Defaults to {@link name}
	 * @var string
	 */
	public $label;

	/**
	 * HTML options for the button tag
	 * @var array
	 */
	public $options;

	/**
	 * Image for when status is true
	 * @var string
	 */
	public $trueImageUrl;

	/**
	 * Image for when status is false
	 * @var string
	 */
	public $falseImageUrl;

	public $toggleUrl = 'Yii::app()->controller->createUrl("toggle",array("id"=>$data->primaryKey))';

	protected $baseScriptUrl;

	/**
	 * Initializes the column.
	 */
	public function init()
	{
		parent::init();

		if ($this->name === null)
			throw new CException(Yii::t('zii','"name" must be specified for BooleanButtonColumn.'));

		if ($this->label === null)
			$this->label=$this->name;

		if ($this->baseScriptUrl === null)
			$this->baseScriptUrl=Yii::app()->getAssetManager()->publish(Yii::getPathOfAlias('ext.assets.booleanButtonColumn'));

    	if ($this->trueImageUrl === null)	$this->trueImageUrl = $this->baseScriptUrl.'/enabled.png';
		if ($this->falseImageUrl === null)	$this->falseImageUrl = $this->baseScriptUrl.'/disabled.png';
	}

	/**
	 * Renders the data cell content.
	 * This method evaluates {@link value} or {@link name} and renders the result.
	 * @param integer the row number (zero-based)
	 * @param mixed the data associated with the row
	 */
	protected function renderDataCellContent($row,$data)
	{
		if ($this->value !== null)		$value = $this->value;
		elseif ($this->name !== null)	$value = CHtml::value($data,$this->name);
		$image		= ($value)? $this->trueImageUrl : $this->falseImageUrl;
		$otherImage	= ($value)? $this->falseImageUrl : $this->trueImageUrl;
		$url			= (isset($this->toggleUrl))? $this->evaluateExpression($this->toggleUrl, array('data' => $data, 'row' => $row)) : '#';
		$options		= (isset($this->options))? $this->options : array();
		
		if (!isset($options['title'])) $options['title'] = $this->label;
		$imgChange = array(
			 'onmouseover' => "this.src='$otherImage'",
			 'onmouseout' => "this.src='$image'",
		);

		if(isset($this->trueImageUrl) && is_string($this->trueImageUrl))
			echo CHtml::link(CHtml::image($image, $this->label, $imgChange), $url, $options);
		else
			echo CHtml::link($this->label, $url, $options);
//		echo $value;
	}

}
?>
