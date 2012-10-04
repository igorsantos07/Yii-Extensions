<?php
class VarDumper extends CVarDumper {

	public static function model(CActiveRecord $model) {
		echo get_class($model).': ';
		var_dump($model->attributes);
		foreach($model->relations() as $name => $relation) {
			echo "->$name: ";
			var_dump($model->$name->attributes);
		}
	}

}