<?php
class TooltipColumn extends CDataColumn {

	/**
	 * The value to be displayed as tooltip. If empty, there'll be no tooltip.
	 * @var string
	 */
	public $tooltip_content = '';

	/**
	 * JS configuration for tooltip call
	 * @var string
	 */
	public $tooltip_config = '';

	/**
	 * Initializes the javascript needed to create the tooltip. The jQuery plugin needs to be included separately.
	 */
	public function init() {
		parent::init();
		Yii::app()->clientScript->registerScript("tooltip_{$this->id}", "$('.grid-tooltip-$this->id').tooltip($this->tooltip_config)", CClientScript::POS_END);
	}


	/**
	 * Renders the data cell content.
	 * This method evaluates {@link value} or {@link name} and renders the result. It also adds a tooltip if the $tooltip_content property is set.
	 * @param integer $row the row number (zero-based)
	 * @param mixed $data the data associated with the row
	 */
	protected function renderDataCellContent($row,$data) {
		if($this->value!==null)
			$value=$this->evaluateExpression($this->value,array('data'=>$data,'row'=>$row));
		else if($this->name!==null)
			$value=CHtml::value($data,$this->name);

		$content = $value===null ? $this->grid->nullDisplay : $this->grid->getFormatter()->format($value,$this->type);

		if ($this->tooltip_content) {
			$tooltip = $this->evaluateExpression($this->tooltip_content, array('data' => $data, 'row' => $row));
			if ($tooltip)
				$content = "<span class=\"grid-tooltip grid-tooltip-$this->id\" title=\"$tooltip\">$content</span>";
		}

		echo $content;
	}

}