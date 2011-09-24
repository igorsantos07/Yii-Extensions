<?php
/**
 * A Yii behavior to make text snippets
 *
 * @author igoru
 */
class Snippetable extends CBehavior {

	const DEFAULT_LENGHT = 100;

	function snippet($attr, $size = self::DEFAULT_LENGHT, $ellipsis = '...') {
     	if (!$size) $size = self::DEFAULT_LENGHT;

		if (is_string($this->owner->$attr)) {
        	if (strlen($this->owner->$attr) <= $size)
				return $this->owner->$attr;
			else {
				if ($ellipsis)
					return substr(strip_tags(html_entity_decode($this->owner->$attr)), 0, $size-(strlen($ellipsis))).$ellipsis;
				else
					return substr(strip_tags(html_entity_decode($this->owner->$attr)), 0, $size);
			}
		}
	}

}
?>
