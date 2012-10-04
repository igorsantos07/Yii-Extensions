<?php

/**
 * An extension to {@link CButtonColumn} that displays text-links instead of images and
 * adds a pipe between each link.
 */
class PipedButtonColumn extends CButtonColumn {

	/**
	 * Renders the data cell content, adding a pipe "|" between each button.
	 * This method renders the view, update and delete buttons in the data cell, text-mode.
	 * @param integer $row the row number (zero-based)
	 * @param mixed $data the data associated with the row
	 */
	protected function renderDataCellContent($row, $data) {
		$tr = array();
		ob_start();
		$total_buttons = 0;
		foreach ($this->buttons as $button) {
			if (!isset($button['visible']) || $this->evaluateExpression($button['visible'],array('row'=>$row,'data'=>$data)))
				++$total_buttons;
		}

		$b = 0;
		foreach ($this->buttons as $id => $button) {
			$button['imageUrl'] = false;
			$this->renderButton($id, $button, $row, $data);
			$content = ob_get_contents();
			$tr['{'.$id.'}'] = $content;
			if (strlen($content) > 0 && ++$b != $total_buttons) $tr['{'.$id.'}'] .= ' |' ;
			ob_clean();
		}
		ob_end_clean();
		echo strtr($this->template, $tr);
	}

}