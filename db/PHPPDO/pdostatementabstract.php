<?php
/**
 * Project:     PHPPDO
 * File:        pdostatementabstract.php
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 * For questions, help, comments, discussion, etc.,
 * visit <http://devuni.com>
 *
 * @link http://devuni.com/
 * @Copyright 2007, 2008, 2009 Nikolay Ananiev.
 * @author Nikolay Ananiev <admin at devuni dot com>
 */

abstract class PDOStatement
{
    abstract public function bindColumn($column, &$param, $type = 0, $maxlen = 0, $driver_options = null);
    abstract public function bindParam($parameter, &$variable, $data_type = 0, $length = 0, $driver_options = null);
    abstract public function bindValue($parameter, $value, $data_type = 0);
    abstract public function closeCursor();
    abstract public function columnCount();
    abstract public function errorCode();
    abstract public function errorInfo();
    abstract public function execute($input_parameters = array());
    abstract public function fetch($fetch_style = 0, $cursor_orientation = 0, $cursor_offset = 0);
    abstract public function fetchAll($fetch_style = 0, $column_index = 0, $ctor_args = array());
    abstract public function fetchColumn($column_number = 0);
    abstract public function fetchObject($class_name = '', $ctor_args = array());
    abstract public function getAttribute($attribute);
    abstract public function getColumnMeta($column);
    abstract public function nextRowset();
    abstract public function rowCount();
    abstract public function setAttribute($attribute, $value);
    abstract public function setFetchMode($mode, $param = '', $ctorargs = array());
}
?>