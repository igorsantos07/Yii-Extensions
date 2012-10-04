<?php

/**
 * Fixes Oracle-related problems when inserting log data into the table
 */
class OciLogRoute extends CDbLogRoute {

	/**
	 * Stores log messages into database.
	 * @param array $logs list of log messages
	 */
	protected function processLogs($logs) {
		$sql = 'INSERT INTO "'.$this->logTableName.'" ("LEVEL", "CATEGORY", "LOGTIME", "MESSAGE") VALUES (?, ?, ?, ?)';
		$command = $this->getDbConnection()->createCommand($sql);
		foreach ($logs as $log)
			$command->bindValues(array(1 => $log[1], $log[2], (int) $log[3], $log[0]))->execute();
	}

}