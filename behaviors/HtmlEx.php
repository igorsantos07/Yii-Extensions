<?php

class HtmlEx extends CBehavior {

	/**
	 * Encapsulates {@link CJuiButton} and {@link CHtml::radioButtonList} for an easy way to create input for flag fields.
	 * There are two uses for this method: static and active fields.
	 * Static fields make use of CHtml methods to create the radio buttons, and you'll only need the three first arguments.
	 * For use with {@link CActiveForm} you can skip the $value argument and supply $form and $model too, or use
	 * {@link activeJuiFlag}, that's a shorthand method to simplify this use.
	 *
	 * @param string $name the field name
	 * @param array $options a list of options, where the keys are the field values and
	 *		the values are: a string with the label or an array where the first element
	 *		is the label and the second is the icon class. For adding icons, you need to
	 *		include CSS rules that add an image as background to that class, like:
	 *		<code>.ui-icon-custom-yes { background-image: url(/images/icons/tick.png); }</code>
	 * @param mixed $value [optional] The current value for the field.
	 * @param CActiveForm [optional] $form the form widget being used
	 * @param CModel [optional] $model the model
	 */
	public function juiFlag($name, array $options, $value = null, CActiveForm $form = null, CModel $model = null) {
		$radio_options = $icons = array(); $button_number = 0;

		foreach($options as $value => $data) {
			$radio_options[$value] = ($is_array = is_array($data))? $data[0] : $data;
			if ($is_array && isset($data[1])) $icons[$button_number] = $data[1];
			++$button_number;
		}

		$this->owner->beginWidget('zii.widgets.jui.CJuiButton', array('buttonType' => 'buttonset', 'name' => $name));
			if ($form) {
				echo $form->radioButtonList($model, $name, $radio_options, array('separator' => ''));
				$radio_id_prefix = get_class($model).'_'.$name;
			}
			else {
				echo CHtml::radioButtonList($name, $value, $radio_options, array('separator' => ''));
				$radio_id_prefix = $name;
			}
		$this->owner->endWidget();

		$js = function() use($icons, $radio_id_prefix) {
			$js = '';
			foreach ($icons as $i => $icon) $js .= "$('#{$radio_id_prefix}_{$i}').button('option', 'icons', {primary: '{$icon}'})\n";
			return $js;
		};
		Yii::app()->clientScript->registerScript("{$radio_id_prefix}_juiFlagIcons", $js());
	}

	/**
	 * Encapsulates {@link juiFlag} with use near to the common CActiveForm fields
	 * @param CActiveForm $form the form widget being used
	 * @param CModel $model the model
	 * @param string $attribute the field name from that model
	 * @param array $options a list of options, where the keys are the field values and
	 *		the values are: a string with the label or an array where the first element
	 *		is the label and the second is the icon class. For adding icons, you need to
	 *		include CSS rules that add an image as background to that class, like:
	 *		<code>.ui-icon-custom-yes { background-image: url(/images/icons/tick.png); }</code>
	 */
	public function activeJuiFlag(CActiveForm $form, CModel $model, $attribute, array $options) {
		return $this->juiFlag($attribute, $options, null, $form, $model);
	}

	public function activeJuiAutoComplete(CActiveForm $form, CModel $model, $attribute, $acRoute, $acValue, array $acOptions = array(), array $htmlOptions = array(), $loader = '/images/loader.gif') {
		$defaultAcOptions = array(
			'minLength' => '3',
			'select'	=> "js:{$attribute}_fillHidden",
			'focus'		=> "js:{$attribute}_itemFocus",
			'search'	=> "js:{$attribute}_initSearch",
			'open'		=> "js:{$attribute}_finishedSearch",
		);

		$acName = "{$attribute}_autocomplete";
		$_POST[$acName] = $acValue;

		echo $form->hiddenField($model, $attribute);
		$this->owner->widget('zii.widgets.jui.CJuiAutoComplete', array(
			'name'			=> $acName,
			'value'			=> $acValue,
			'sourceUrl'		=> $acRoute,
			'options'		=> array_merge($defaultAcOptions, $acOptions),
			'htmlOptions'	=> $htmlOptions,
		 ));

		Yii::app()->clientScript->registerScript('jui_extras', "
			function {$attribute}_initSearch(e, ui) {
				$(e.target).after('<img src=\"$loader\" class=\"loader\" alt=\"...\" />')
				return true
			}
			function {$attribute}_finishedSearch(e, ui) {
				$(e.target).siblings('img').remove()
				return true
			}
			function {$attribute}_fillHidden(e, ui) {
				$('#".get_class($model)."_{$attribute}').val(ui.item.value)
				$(e.target).val(ui.item.label)
				return false
			}
			function {$attribute}_itemFocus(e, ui) {
				$(e.target).val(ui.item.label)
				return false
			}
		");
	}

}