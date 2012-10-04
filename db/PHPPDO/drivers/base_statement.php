<?php
/**
 * Project:     PHPPDO
 * File:        base_statement.php
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

class phppdo_base_statement_iterator implements Iterator
{
    private $stmt;
    private $row;
    private $cnt = -1;
    
    public function __construct(PDOStatement $stmt)
    {
        $this->stmt = $stmt;
    }
    
    public function key()
    {
        return $this->cnt;
    }
    
    public function rewind()
    {
        $this->next();
    }
    
    public function current()
    {
        return $this->row;
    }
    
    public function next()
    {
        $this->row = $this->stmt->fetch();
        $this->cnt++;
    }
    
    public function valid()
    {
        if($this->row === false) return false;
        return true;
    }
}

// PHP 5.2.6 and earlier's fetchObject method signature differes than PHP 5.2.7 and up
$phppdo_error_reporting = error_reporting();
if(version_compare(PHP_VERSION, '5.2.7', '<') && ($phppdo_error_reporting & E_STRICT))
{
    error_reporting($phppdo_error_reporting & ~E_STRICT);
}
else
{
    $phppdo_error_reporting = null;
}

abstract class phppdo_base_statement extends PDOStatement implements IteratorAggregate
{
    protected $_driver;
    protected $_link;
    protected $_result = null;
    protected $_result_name;
    
    protected $_params_info;
    protected $_bound_params        = array();
    
    private $driver_options         = array();
    private $last_error             = array('');
    
    private $prepared;
    private $bound_columns          = array();
    private $columns_meta           = null;
    
    private $fetch_func             = 'fetch';
    private $fetch_mode             = array
    (
        PDO::FETCH_COLUMN       => array(0),
    );
    
    
    // Required by IteratorAggregate
    public function getIterator()
    {
        return new phppdo_base_statement_iterator($this);
    }
    
    
    public function bindColumn($column, &$param, $type = 0, $maxlen = 0, $driver_options = null)
    {
        if($this->_result === null)
        {
            return false;
        }
        else if(is_numeric($column))
        {
            if($column < 1)
            {
                $this->_set_error(0, 'Invalid parameter number: Columns/Parameters are 1-based', 'HY093', PDO::ERRMODE_WARNING, 'bindColumn');
                return false;
            }
            
            $column -= 1;
        }
        
        $this->bound_columns[$column] = array(&$param, $type);
        return true;
    }
    
    public function bindParam($parameter, &$variable, $data_type = -1, $length = 0, $driver_options = null)
    {
        if($parameter[0] != ':' && !is_numeric($parameter))
        {
            $parameter = ':' . $parameter;
        }
        
        if(isset($this->_params_info[$parameter]))
        {
            $this->_bound_params[$this->_params_info[$parameter]] = array(&$variable, $data_type, $length);
            return true;
        }
        
        return false;
    }
    
    public function bindValue($parameter, $value, $data_type = -1)
    {
        return $this->bindParam($parameter, $value, $data_type);
    }
    
    public function errorCode()
    {
        if(func_num_args() > 0) return false;
        return $this->last_error[0];
    }
    
    public function errorInfo()
    {
        if(func_num_args() > 0) return false;
        return $this->last_error;
    }
    
    public function execute($input_parameters = array())
    {
        if(!$this->prepared)
        {
            $this->_set_error(0, 'Invalid parameter number: statement not prepared', 'HY093', PDO::ERRMODE_WARNING, 'execute');
            return false;
        }
        
        if(is_array($input_parameters))
        {
            $status = true;
            foreach($input_parameters as $p => &$v)
            {
                if(is_numeric($p))
                {
                    if($p >= 0)
                    {
                        $status = $this->bindParam(($p + 1), $v);
                    }
                }
                else
                {
                    $status = $this->bindParam($p, $v);
                }
                
                if(!$status)
                {
                    $this->_set_error(0, 'Invalid parameter number: number of bound variables does not match number of tokens', 'HY093', PDO::ERRMODE_WARNING, 'execute');
                    return false;
                }
            }
        }
        
        if($this->_execute())
        {
            $this->_driver->clear_error($this->last_error);
            return true;
        }
        
        return false;
    }
    
    public function fetch($fetch_style = 0, $cursor_orientation = 0, $cursor_offset = 0)
    {
        if($this->_result)
        {
            $fetch_mode =& $this->fetch_mode;
            
            switch($fetch_style)
            {
                case 0:
                    $fetch_style = $this->driver_options[PDO::ATTR_DEFAULT_FETCH_MODE];
                
                    switch($fetch_style)
                    {
                        case PDO::FETCH_CLASS:
                        case PDO::FETCH_CLASS|PDO::FETCH_PROPS_LATE:
                            if(!isset($fetch_mode[PDO::FETCH_CLASS]) || !$fetch_mode[PDO::FETCH_CLASS][0])
                            {
                                $this->_set_error(0, 'General error: No fetch class specified', 'HY000', PDO::ERRMODE_SILENT, $this->fetch_func);
                                $this->_set_error(0, 'General error', 'HY000', PDO::ERRMODE_SILENT, $this->fetch_func);
                                return false;
                            }
                        break;
                    }
                break;
                
                case PDO::FETCH_GROUP:
                    $fetch_style |= $this->driver_options[PDO::ATTR_DEFAULT_FETCH_MODE];
                break;
            }
            
            switch($fetch_style)
            {
                case PDO::FETCH_COLUMN:
                    return $this->fetchColumn($fetch_mode[PDO::FETCH_COLUMN][0]);
                break;
                
                case PDO::FETCH_FUNC:
                    $this->_set_error(0, 'General error: PDO::FETCH_FUNC is only allowed in PDOStatement::fetchAll()', 'HY000', PDO::ERRMODE_WARNING, $this->fetch_func);
                    return false;
                break;
            }
            
            $row = $this->_fetch_row();
            if(!$row) return false;
            
            $stringify = $this->driver_options[PDO::ATTR_STRINGIFY_FETCHES];
            $nulls = $this->driver_options[PDO::ATTR_ORACLE_NULLS];
            
            if($stringify || $nulls != PDO::NULL_NATURAL)
            {
                $driver = $this->_driver;
                $cnt = count($row);
                
                // seems like foreach($row as &$r) modifies $row and makes the value a referece in php 5.2.5
                for($x = 0; $x < $cnt; $x++)
                {
                    $driver->filter_result($row[$x], $stringify, $nulls);
                }
            }
            
            if($this->bound_columns && $this->fetch_func == 'fetch')
            {
                $this->bind_columns($row);
            }
            
            switch($fetch_style)
            {
                case PDO::FETCH_ASSOC:
                    return $this->make_assoc($row);
                break;
                
                case PDO::FETCH_ASSOC|PDO::FETCH_GROUP:
                    $row = $this->make_assoc($row);
                    return array(array_shift($row) => $row);
                break;
                
                case PDO::FETCH_NAMED:
                    return $this->make_named($row);
                break;
                
                case PDO::FETCH_NUM:
                    return $row;
                break;
                
                case PDO::FETCH_NUM|PDO::FETCH_GROUP:
                    return array(array_shift($row) => $row);
                break;
                
                case PDO::FETCH_BOTH:
                    return $this->make_both($row);
                break;
                
                case PDO::FETCH_LAZY:
                case PDO::FETCH_OBJ:
                    return $this->map_obj_props(new stdClass(), $row);
                break;
                
                case PDO::FETCH_CLASS:
                case PDO::FETCH_CLASS|PDO::FETCH_PROPS_LATE:
                    if(isset($fetch_mode[PDO::FETCH_CLASS]) && $fetch_mode[PDO::FETCH_CLASS][0])
                    {
                        $class_name = $fetch_mode[PDO::FETCH_CLASS][0];
                    }
                    else
                    {
                        $class_name = 'stdClass';
                    }
                    
                    if(isset($fetch_mode[PDO::FETCH_CLASS]) && $fetch_mode[PDO::FETCH_CLASS][1])
                    {
                        $class  = new ReflectionClass($class_name);
                        $obj    = $class->newInstanceArgs($fetch_mode[PDO::FETCH_CLASS][1]);
                    }
                    else
                    {
                        $obj = new $class_name();
                    }
                    
                    return $this->map_obj_props($obj, $row);
                break;
                
                case PDO::FETCH_CLASS|PDO::FETCH_CLASSTYPE:
                case PDO::FETCH_CLASS|PDO::FETCH_CLASSTYPE|PDO::FETCH_SERIALIZE:
                    $class = array_shift($row);
                    if(!$class)
                    {
                        if(isset($fetch_mode[PDO::FETCH_CLASS|PDO::FETCH_CLASSTYPE]) && $fetch_mode[PDO::FETCH_CLASS|PDO::FETCH_CLASSTYPE][0])
                        {
                            $class = $fetch_mode[PDO::FETCH_CLASS|PDO::FETCH_CLASSTYPE][0];
                        }
                        else
                        {
                            $class = 'stdClass';
                        }
                    }
                    
                    $obj = new $class();
                    if($fetch_style & PDO::FETCH_SERIALIZE)
                    {
                        if(is_callable(array($obj, 'unserialize')))
                        {
                            $obj->unserialize(array_shift($row));
                        }
                        else
                        {
                            $this->_set_error(0, 'General error: cannot unserialize class', 'HY000', PDO::ERRMODE_SILENT, $this->fetch_func);
                            return false;
                        }
                    }
                    else
                    {
                        $this->map_obj_props($obj, $row, 1);
                    }
                    
                    return $obj;
                break;
                
                case PDO::FETCH_INTO:
                    return $this->map_obj_props($fetch_mode[PDO::FETCH_INTO][0], $row);
                break;
                
                case PDO::FETCH_KEY_PAIR:
                    if(count($row) != 2)
                    {
                        $this->_set_error(0, 'General error: PDO::FETCH_KEY_PAIR fetch mode requires the result set to contain extactly 2 columns.', 'HY000', PDO::ERRMODE_SILENT, $this->fetch_func);
                        $this->_set_error(0, 'General error', 'HY000', PDO::ERRMODE_SILENT, $this->fetch_func);
                        return false;
                    }
                    
                    return array($row[0] => &$row[1]);
                break;
                
                case PDO::FETCH_BOUND:
                    return true;
                break;
            }
            
        }
        
        return false;
    }
    
    public function fetchAll($fetch_style = 0, $column_index = null, $ctor_args = array())
    {
        if(!$this->_result) return false;
        $result = array();
        
        if($fetch_style)
        {
            $style = $fetch_style;
        }
        else
        {
            $style = $this->driver_options[PDO::ATTR_DEFAULT_FETCH_MODE];
        }
        
        $this->fetch_func = 'fetchAll';
        
        switch($style)
        {
            case PDO::FETCH_ASSOC:
            case PDO::FETCH_NAMED:
            case PDO::FETCH_NUM:
            case PDO::FETCH_BOTH:
            case PDO::FETCH_LAZY:
            case PDO::FETCH_OBJ:
            case PDO::FETCH_INTO:
            case PDO::FETCH_BOUND:
            case PDO::FETCH_GROUP:
                while($row = $this->fetch($fetch_style))
                {
                    $result[] = $row;
                }
            break;
            
            case PDO::FETCH_FUNC:
            case PDO::FETCH_FUNC|PDO::FETCH_GROUP:
                if(!$column_index && isset($this->fetch_mode[PDO::FETCH_FUNC]))
                {
                    $column_index = $this->fetch_mode[PDO::FETCH_FUNC][0];
                }
                
                if($column_index)
                {
                    if(is_callable($column_index))
                    {
                        if($style & PDO::FETCH_GROUP)
                        {
                            while($row = $this->fetch(PDO::FETCH_NUM))
                            {
                                $key = array_shift($row);
                                if(isset($result[$key]))
                                {
                                    $result[$key][] = call_user_func_array($column_index, $row);
                                }
                                else
                                {
                                    $result[$key] = array(call_user_func_array($column_index, $row));
                                }
                            }
                        }
                        else
                        {
                            while($row = $this->fetch(PDO::FETCH_NUM))
                            {
                                $result[] = call_user_func_array($column_index, $row);
                            }
                        }
                    }
                    else
                    {
                        $this->_set_error(0, 'General error: user-supplied function must be a valid callback', 'HY000', PDO::ERRMODE_WARNING, $this->fetch_func);
                    }
                }
                else
                {
                    $this->_set_error(0, 'General error: No fetch function specified', 'HY000', PDO::ERRMODE_WARNING, $this->fetch_func);
                    
                    if($style != $fetch_style)
                    {
                        $this->_set_error(0, 'General error', 'HY000', PDO::ERRMODE_SILENT, $this->fetch_func);
                    }
                }
            break;
            
            case PDO::FETCH_CLASS:
            case PDO::FETCH_CLASS|PDO::FETCH_PROPS_LATE:
                if($column_index)
                {
                    $this->setFetchMode(PDO::FETCH_CLASS, $column_index, $ctor_args);
                }
                
                while($row = $this->fetch($fetch_style))
                {
                    $result[] = $row;
                }
            break;
            
            case PDO::FETCH_CLASS|PDO::FETCH_CLASSTYPE:
                $this->setFetchMode(PDO::FETCH_CLASS|PDO::FETCH_CLASSTYPE, $column_index, $ctor_args);
                while($row = $this->fetch(PDO::FETCH_CLASS|PDO::FETCH_CLASSTYPE))
                {
                    $result[] = $row;
                }
            break;
            
            case PDO::FETCH_CLASS|PDO::FETCH_CLASSTYPE|PDO::FETCH_GROUP:
                $this->setFetchMode(PDO::FETCH_CLASS|PDO::FETCH_CLASSTYPE, $column_index, $ctor_args);
                $first_property = null;
                
                while($row = $this->fetch(PDO::FETCH_CLASS|PDO::FETCH_CLASSTYPE))
                {
                    if($first_property === null)
                    {
                        $first_property = key(get_object_vars($row));
                    }
                    
                    $key = $row->$first_property;
                    unset($row->$first_property);
                    
                    if(isset($result[$key]))
                    {
                        $result[$key][] = $row;
                    }
                    else
                    {
                        $result[$key] = array($row);
                    }
                }
            break;
            
            case PDO::FETCH_CLASS|PDO::FETCH_CLASSTYPE|PDO::FETCH_UNIQUE:
                $this->setFetchMode(PDO::FETCH_CLASS|PDO::FETCH_CLASSTYPE, $column_index, $ctor_args);
                $first_property = null;
                
                while($row = $this->fetch(PDO::FETCH_CLASS|PDO::FETCH_CLASSTYPE))
                {
                    if($first_property === null)
                    {
                        $first_property = key(get_object_vars($row));
                    }
                    
                    $key = $row->$first_property;
                    unset($row->$first_property);
                    
                    $result[$key] = $row;
                }
            break;
            
            case PDO::FETCH_CLASS|PDO::FETCH_CLASSTYPE|PDO::FETCH_SERIALIZE:
                $this->setFetchMode(PDO::FETCH_CLASS|PDO::FETCH_CLASSTYPE, $column_index, $ctor_args);
                while($row = $this->fetch(PDO::FETCH_CLASS|PDO::FETCH_CLASSTYPE|PDO::FETCH_SERIALIZE))
                {
                    $result[] = $row;
                }
            break;
            
            case PDO::FETCH_KEY_PAIR:
                while($row = $this->fetch(PDO::FETCH_NUM))
                {
                    $result[$row[0]] = $row[1];
                }
            break;
            
            case PDO::FETCH_COLUMN:
                if($column_index === null) $column_index = $this->fetch_mode[PDO::FETCH_COLUMN][0];
                while(($row = $this->fetchColumn($column_index)) !== false)
                {
                    $result[] = $row;
                }
            break;
            
            case PDO::FETCH_COLUMN|PDO::FETCH_UNIQUE:
                if($column_index === null) $column_index = 1;
                
                while($row = $this->fetch(PDO::FETCH_NUM))
                {
                    if(isset($result[$row[0]])) continue;
                    $result[$row[0]] = $row[$column_index];
                }
            break;
            
            case PDO::FETCH_COLUMN|PDO::FETCH_GROUP:
                if($column_index === null) $column_index = 1;

                while($row = $this->fetch(PDO::FETCH_NUM))
                {
                    $key = $row[0];
                    if(isset($result[$key]))
                    {
                        $result[$key][] = $row[$column_index];
                    }
                    else
                    {
                        $result[$key] = array($row[$column_index]);
                    }
                }
            break;
            
            case PDO::FETCH_UNIQUE|PDO::FETCH_ASSOC:
            case PDO::FETCH_UNIQUE|PDO::FETCH_NUM:
                $s = $style & ~PDO::FETCH_UNIQUE;
                while($row = $this->fetch($s))
                {
                    $result[array_shift($row)] = $row;
                }
            break;
            
            case PDO::FETCH_GROUP|PDO::FETCH_ASSOC:
            case PDO::FETCH_GROUP|PDO::FETCH_NUM:
            case PDO::FETCH_COLUMN|PDO::FETCH_NUM:
                $s = $style & ~PDO::FETCH_GROUP;
                
                if($s == PDO::FETCH_COLUMN)
                {
                    if($column_index === null) $column_index = $fetch_mode[PDO::FETCH_COLUMN][0];
                    
                    while($row = $this->fetch(PDO::FETCH_NUM))
                    {
                        $key = array_shift($row[$column_index]);
                        if(isset($result[$key]))
                        {
                            $result[$key] = array_merge($result[$key], $row);
                        }
                        else
                        {
                            $result[$key] = array($row);
                        }
                    }
                }
                else
                {
                    while($row = $this->fetch($s))
                    {
                        $key = array_shift($row);
                        if(isset($result[$key]))
                        {
                            $result[$key][] = $row;
                        }
                        else
                        {
                            $result[$key] = array($row);
                        }
                    }
                }
            break;
        }
        
        $this->fetch_func = 'fetch';
        return $result;
    }
    
    public function fetchColumn($column_number = 0)
    {
        if($this->_result)
        {
            $row = $this->_fetch_row();
            if($row && array_key_exists($column_number, $row))
            {
                $this->_driver->filter_result($row[$column_number], $this->driver_options[PDO::ATTR_STRINGIFY_FETCHES], $this->driver_options[PDO::ATTR_ORACLE_NULLS]);
                return $row[$column_number];
            }
        }
        
        return false;
    }
    
    public function fetchObject($class_name = '', $ctor_args = array())
    {
        if($class_name)
        {
            $this->setFetchMode(PDO::FETCH_CLASS, $class_name, $ctor_args);
        }
        
        return $this->fetch(PDO::FETCH_CLASS);
    }
    
    public function getAttribute($attribute)
    {
        if(func_num_args() != 1 || !is_int($attribute)) return false;
        return $this->_driver->getAttribute($attribute, $this->driver_options, 'PDOStatement::getAttribute', $this->last_error);
    }
    
    public function nextRowset()
    {
        return false;
    }
    
    public function setAttribute($attribute, $value)
    {
        if(func_num_args() != 2) return false;
        
        switch($attribute)
        {
            case PDO::ATTR_PREFETCH:
                $this->_set_error(0, 'Driver does not support this function: This driver doesn\'t support setting attributes', 'IM001', PDO::ERRMODE_WARNING, 'setAttribute', $this->last_error);
            break;
            
            default:
                return $this->_driver->setAttribute($attribute, $value, $this->driver_options, 'PDOStatement::setAttribute', $this->last_error);
            break;
        }
        
        return false;
    }
    
    public function setFetchMode($mode, $param = '', $ctorargs = array())
    {
        switch($mode)
        {
            case PDO::FETCH_LAZY:
            case PDO::FETCH_ASSOC:
            case PDO::FETCH_NAMED:
            case PDO::FETCH_NUM:
            case PDO::FETCH_BOTH:
            case PDO::FETCH_OBJ:
            case PDO::FETCH_BOUND:
            case PDO::FETCH_COLUMN:
            case PDO::FETCH_FUNC:
            case PDO::FETCH_CLASS:
            case PDO::FETCH_INTO:
            case PDO::FETCH_KEY_PAIR:
            case PDO::FETCH_SERIALIZE:
            case PDO::FETCH_CLASS|PDO::FETCH_CLASSTYPE:
                
                switch($mode)
                {
                    case PDO::FETCH_INTO:
                        if(!is_object($param)) return false;
                    break;
                    
                    case PDO::FETCH_CLASS:
                        if(!class_exists($param)) return false;
                    break;
                    
                    case PDO::FETCH_CLASS|PDO::FETCH_CLASSTYPE:
                        if($param && !class_exists($param)) return false;
                    break;
                    
                    case PDO::FETCH_COLUMN:
                        if(!is_numeric($param) || $param < 0) return false;
                    break;
                }
                
                $this->fetch_mode[$mode] = array(&$param, $ctorargs);
                $this->driver_options[PDO::ATTR_DEFAULT_FETCH_MODE] = $mode;
                
                return true;
            break;
        }
        
        return false;
    }
    
    public static function _new_instance(&$data, &$statement)
    {
        if(isset($data[1]) && count($data[1]))
        {
            $class = new ReflectionClass($data[0]);
            $obj = $class->newInstanceArgs($data[1]);
        }
        else
        {
            $obj = new $data[0];
        }
        
        $obj->queryString =& $statement;
        return $obj;
    }
    
    final public function _setup($link, $driver, &$driver_options, $prepared, $params_info)
    {
        if($this->_link) return false;
        
        $this->_link            = $link;
        $this->_driver          = $driver;
        $this->driver_options   += $driver_options;
        $this->prepared        =& $prepared;
        $this->_params_info      =& $params_info;
    }
    
    final public function _set_result($result, $result_name = '')
    {
        if($this->_result !== null) return false;
        $this->_result      = $result;
        $this->_result_name = $result_name;
    }
    
    protected function _set_error($code, $message, $state = 'HY000', $mode = PDO::ERRMODE_SILENT, $func = '')
    {
        if($func && strpos($func, '::') === false)
        {
            $func = 'PDOStatement::' . $func;
        }
        
        $this->_driver->set_error($code, $message, $state, $mode, $func, $this->last_error);
    }
    
    abstract protected function _set_stmt_error($state = null, $mode = PDO::ERRMODE_SILENT, $func = '');
    abstract protected function _execute();
    abstract protected function _fetch_row();
    abstract protected function _field_name($field);
    abstract protected function _table_name($field);
    
    protected function _build_query()
    {
        $params             =& $this->_bound_params;
        $params_cnt         = count($params);
        $params_info_cnt    = count($this->_params_info);
        
        if($params_info_cnt && !$params_cnt)
        {
            $this->_set_error(0, 'Invalid parameter number: no parameters were bound', 'HY093', PDO::ERRMODE_WARNING, 'execute');
            return false;
        }
        
        if($params_info_cnt != $params_cnt)
        {
            $this->_set_error(0, 'Invalid parameter number: number of bound variables does not match number of tokens', 'HY093', PDO::ERRMODE_WARNING, 'execute');
            return false;
        }
        
        $prepared   =& $this->prepared;
        $driver     =& $this->_driver;
        
        foreach($params as $k => &$v)
        {
            $param =& $v[0];
            $type = $v[1];
            
            if($type == PDO::PARAM_LOB && is_resource($param))
            {
                $buffer = '';
                while(!feof($param))
                {
                    $buffer .= fread($param, 8192);
                }
                
                $prepared[$k] = $driver->quote($buffer, PDO::PARAM_STR);
            }
            else
            {
                // get param type
                if($type == -1)
                {
                    if(is_int($param) || is_float($param))
                    {
                        $type = PDO::PARAM_INT;
                    }
                    else if(is_bool($param))
                    {
                        $type = PDO::PARAM_BOOL;
                    }
                    else if(is_null($param))
                    {
                        $type = PDO::PARAM_NULL;
                    }
                    else
                    {
                        $type = PDO::PARAM_STR;
                    }
                }
                
                $prepared[$k] = $driver->quote($param, $type);
            }
        }
        
        return implode(' ', $prepared);
    }
    
    protected function _fetch_lob(&$p, &$col)
    {
        $p = tmpfile();
        if($p)
        {
            fwrite($p, $col);
            rewind($p);
        }
    }
    
    private function make_assoc(&$row)
    {
        if(!$this->columns_meta)
        {
            $this->fetch_columns_meta();
        }
        
        return array_combine($this->columns_meta[1], $row);
    }
    
    private function make_both(&$row)
    {
        if(!$this->columns_meta)
        {
            $this->fetch_columns_meta();
        }
        
        $fields =& $this->columns_meta[1];
        $result = array();
        
        foreach($row as $k => &$v)
        {
            $result[$k] = $v;
            $result[$fields[$k]] = $v;
        }
        
        return $result;
    }
    
    private function make_named(&$row)
    {
        if(!$this->columns_meta)
        {
            $this->fetch_columns_meta();
        }
        
        $fields =& $this->columns_meta[1];
        
        $result = array();
        foreach($row as $k => &$v)
        {
            $fname =& $fields[$k];
            if(!isset($result[$fname]))
            {
                $result[$fname] = &$v;
            }
            else if(!is_array($result[$fname]))
            {
                $result[$fname] = array($result[$fname], &$v);
            }
            else
            {
                $result[$fname][] = &$v;
            }
        }
        
        return $result;
    }
    
    private function map_obj_props($obj, &$row, $offset = 0)
    {
        if(!$this->columns_meta)
        {
            $this->fetch_columns_meta();
        }
        
        $fields =& $this->columns_meta[0];
        
        foreach($row as $k => &$v)
        {
            // do not assign =& $v
            $obj->$fields[$k + $offset] = $v;
        }
        
        return $obj;
    }
    
    private function bind_columns(&$row)
    {
        if(!$this->columns_meta)
        {
            $this->fetch_columns_meta();
        }
        
        $fields = array_flip($this->columns_meta[0]);
        
        foreach($this->bound_columns as $k => &$v)
        {
            if(isset($row[$k]))
            {
                $col =& $row[$k];
            }
            else if(isset($fields[$k]))
            {
                $col =& $row[$fields[$k]];
            }
            else
            {
                continue;
            }
            
            $p =& $v[0];
            switch($v[1])
            {
                case PDO::PARAM_LOB:
                    $this->_fetch_lob($p, $col);
                break;
                
                default:
                    $p = $col;
                break;
            }
        }
    }
    
    private function fetch_columns_meta()
    {
        $opt            =& $this->driver_options;
        $case           = $opt[PDO::ATTR_CASE];
        $table_names    = $opt[PDO::ATTR_FETCH_TABLE_NAMES];
        $catalog_names  = $opt[PDO::ATTR_FETCH_CATALOG_NAMES];
        
        $x      = 0;
        $count  = $this->columnCount();
        
        $result = array(array(), array());
        $names  =& $result[0];
        $tables =& $result[1];
        
        while($x < $count)
        {
            $name =& $names[$x];
            $table =& $tables[$x];
            
            $name = $this->_field_name($x);
            
            switch($case)
            {
                case PDO::CASE_LOWER:
                    $name = strtolower($name);
                break;
                
                case PDO::CASE_UPPER:
                    $name = strtoupper($name);
                break;
            }
            
            if($table_names && ($table_name = $this->_table_name($x)))
            {
                $table = $table_name . '.' . $name;
            }
            else
            {
                $table = $name;
            }
            
            $x++;
        }
        
        $this->columns_meta =& $result;
    }
    
}

// set the old error reporting
if($phppdo_error_reporting !== null)
{
    error_reporting($phppdo_error_reporting);
}