<?php

/**
 * Extends DbMigration:
 * - add trigger methods ({@see createTrigger} {@see dropTrigger})
 * - add helper to create field summary ({@see ora_field})
 * - extends {@see createTable} to create automatic PK constraint and sequence for the first field
 * - extends {@see dropTable} to drop it's sequence
 */
class OciMigration extends CDbMigration {

	/**
	 * Creates the string needed to insert a field in a Oracle table. Includes a NN_XXXX constraint for NOT NULL fields if needed.
	 * @param string $table the table name. needed to create the constraint name
	 * @param string $field field name. can be empty if you only need the part after the field name (to use with, i.e., addColumn)
	 * @param string $type field type. defaults to NUMBER
	 * @param string/bool $constraint if the constraint need to be generated. If you need a not-default name for this constraint (as in shorter constraint names) you can specify it here.
	 * @param bool $null if the field should be NULL or NOT NULL
	 * @return string
	 */
	static function ora_field($table, $field = null, $type = 'NUMBER', $constraint = true, $null = false) {
		$str = ($field)? "\"$field\" " : '';
		$str .= $type;

		if ($constraint === true) $str .= " CONSTRAINT NN_{$table}_{$field}";
		elseif (is_string($constraint)) $str .= " CONSTRAINT NN_$constraint";

		if (!$null) $str .= ' NOT NULL';

		return $str;
	}

	/**
	 * extends the original createTable function to create PK sequence and constraint.
	 * @param $table string table name
	 * @param $columns array Array of definitions, string keys will be used as column name and values as column type; indexed strings will be used as-is in the SQL
	 * @param $options string optional SQL fragment to be appended in the end of the CREATE TABLE SQL
	 */
	public function createTable($table, $columns, $options = null, $create_trigger = true) {
		$pk = key($columns);
		if (is_numeric($pk))
			$pk = strtok($columns[0], ' ');

		$columns[] = "CONSTRAINT PK_{$table}_ID PRIMARY KEY ($pk)";

		parent::createTable($table, $columns, $options);

		$this->execute("CREATE SEQUENCE SQ_$table START WITH 1 INCREMENT BY 1 MINVALUE 1 NOMAXVALUE nocycle noorder");

		if ($create_trigger)
			$this->createTrigger("TR_{$table}_NUM",
				"before insert
				ON $table
				for each row
				when(new.$pk is null)
				begin
				   select SQ_{$table}.nextval into :new.$pk from dual;
				end;"
			);
	}

	/**
	 * Extends dropTable to drop also the main sequence.
	 * @param string $table table name
	 * @param bool $drop_sequence if there's a sequence to be dropped too. defaults to TRUE.
	 */
	public function dropTable($table, $drop_sequence = true) {
		if ($drop_sequence) $this->execute("DROP SEQUENCE SQ_$table");
		$result = parent::dropTable($table);
		return $result;
	}

	public function createTrigger($name, $code) {
		try {
			$result = $this->execute("CREATE TRIGGER $name $code");
			return $result;
		}
		catch (CDbException $e) {
			$msg = $e->getMessage();
			if (strpos($msg, 'OCI_SUCCESS_WITH_INFO')) {
				echo "SUCCESS_WITH_INFO\n";
				return true;
			}
			else
				throw $e;
		}
	}

	public function dropTrigger($name) {
		return $this->execute("DROP TRIGGER $name");
	}

	public function toggleConstraint($table, $constraint, $disable = true) {
		$status = $disable? 'DISABLE' : 'ENABLE';
		return $this->execute("ALTER TABLE $table $status CONSTRAINT $constraint");
	}

	/**
	 * Truncates a table, sweeping all data from it.
	 * @param type $table table name
	 * @param type $recreate_pk if the PK should be recreated - reseting the sequence value
	 * @param type $real_truncate if we should use a real TRUNCATE command; by default it uses DELETE, that's less performatic but doesn't cause headaches related to FKs
	 */
	public function truncateTable($table, $recreate_pk = true, $real_truncate = false) {
		if ($real_truncate)
			parent::truncateTable($table);
		else
			$this->delete($table);

		if ($recreate_pk) {
			$sequence = 'SQ_'.$table;
			$this->execute("DROP SEQUENCE $sequence");
			$this->execute("CREATE SEQUENCE $sequence START WITH 1 INCREMENT BY 1 MINVALUE 1 NOMAXVALUE nocycle noorder");
		}

	}

}