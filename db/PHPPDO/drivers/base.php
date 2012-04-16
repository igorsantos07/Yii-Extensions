<?php
/**
 * Project:     PHPPDO
 * File:        base.php
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

abstract class phppdo_base
{
    // we have to duplicate these constants here, because
    // they will be missing if the PDO extension is loaded
    // without the mysql driver 
    const MYSQL_ATTR_USE_BUFFERED_QUERY = 1000;
    const MYSQL_ATTR_LOCAL_INFILE       = 1001;
    const MYSQL_ATTR_INIT_COMMAND       = 1002;
    const MYSQL_ATTR_READ_DEFAULT_FILE  = 1003;
    const MYSQL_ATTR_READ_DEFAULT_GROUP = 1004;
    const MYSQL_ATTR_MAX_BUFFER_SIZE    = 1005;
    const MYSQL_ATTR_DIRECT_QUERY       = 1006;
    
    const PGSQL_ATTR_DISABLE_NATIVE_PREPARED_STATEMENT = 1000;
    
    public $driver_options = array
    (
        PDO::ATTR_AUTOCOMMIT                    => 0,
        PDO::ATTR_PREFETCH                      => 0,
        PDO::ATTR_TIMEOUT                       => false,
        PDO::ATTR_ERRMODE                       => PDO::ERRMODE_SILENT,
        PDO::ATTR_CASE                          => PDO::CASE_NATURAL,
        PDO::ATTR_CURSOR_NAME                   => '',
        PDO::ATTR_CURSOR                        => PDO::CURSOR_FWDONLY,
        PDO::ATTR_DRIVER_NAME                   => '',
        PDO::ATTR_ORACLE_NULLS                  => PDO::NULL_NATURAL,
        PDO::ATTR_PERSISTENT                    => false,
        PDO::ATTR_STATEMENT_CLASS               => array(),
        PDO::ATTR_FETCH_CATALOG_NAMES           => false,
        PDO::ATTR_FETCH_TABLE_NAMES             => false,
        PDO::ATTR_STRINGIFY_FETCHES             => false,
        PDO::ATTR_MAX_COLUMN_LEN                => 0,
        PDO::ATTR_DEFAULT_FETCH_MODE            => PDO::FETCH_BOTH,
        PDO::ATTR_EMULATE_PREPARES              => 1,
    );
    
    protected $dsn;
    protected $link;
    protected $in_transaction      = false;
    
    protected $driver_param_type   = -1; // -1: doesn't support placeholders, 0: numeric - ?, 1: named - :param, 2: postgres numeric - $1
    protected $driver_quote_type   = 0; // 0: backslash, 1: single quote
    protected $prepared;
    
    private $last_error = array('');
    
    public function __construct(&$dsn, &$username, &$password, &$driver_options)
    {
        $this->driver_options[PDO::ATTR_DRIVER_NAME] = $driver_options[PDO::ATTR_DRIVER_NAME];
        unset($driver_options[PDO::ATTR_DRIVER_NAME]);
        
        // set errmode here, because we need it to be ready for the connect method
        $this->set_attributes(array(PDO::ATTR_ERRMODE), $driver_options);
        
        $this->dsn = &$dsn;
        $this->connect($username, $password, $driver_options);
        
        // set auto commit to 1
        if(!isset($driver_options[PDO::ATTR_AUTOCOMMIT])) $driver_options[PDO::ATTR_AUTOCOMMIT] = 1;
        
        foreach($driver_options as $attr => &$value)
        {
            $this->setAttribute($attr, $value);
        }
    }
    
    public function __destruct()
    {
        $this->close();
    }
    
    public function beginTransaction()
    {
        if($this->in_transaction)
        {
            throw new PDOException('There is already an active transaction');
        }
        
        // save previous autocommit state
        $this->driver_options[PDO::ATTR_AUTOCOMMIT] = $this->getAttribute(PDO::ATTR_AUTOCOMMIT);
        
        $this->setAttribute(PDO::ATTR_AUTOCOMMIT, 0);
        $this->in_transaction = true;
        return true;
    }
    
    public function commit()
    {
        if(!$this->in_transaction)
        {
            throw new PDOException('There is no active transaction');
        }
        
        $this->in_transaction = false;
        return true;
    }
    
    public function errorCode()
    {
        return $this->last_error[0];
    }
    
    public function errorInfo()
    {
        return $this->last_error;
    }
    
    abstract public function exec(&$statement);
    
    public function getAttribute($attribute, &$source = null, $func = 'PDO::getAttribute', &$last_error = null)
    {
        if($source == null) $source =& $this->driver_options;
        
        if(array_key_exists($attribute, $source))
        {
            return $source[$attribute];
        }
        
        $this->set_error(0, 'Driver does not support this function: driver does not support that attribute', 'IM001', PDO::ERRMODE_WARNING, $func, $last_error);
        
        // set driver specific error code
        if($last_error !== null) $last_error[1] = -1;
        else $this->last_error[1] = -1;
        
        return false;
    }
    
    abstract public function lastInsertId($name = '');
    
    public function prepare(&$statement, &$options)
    {
        if(!$statement || !is_array($options)) return false;
        
        $driver_options = $this->driver_options;
        foreach($options as $k => $v)
        {
            if(!$this->setAttribute($k, $v, $driver_options, 'PDO::prepare'))
            {
                return false;
            }
        }
        
        switch($this->driver_quote_type)
        {
            case 1:
                $params_regex = '/(\'[^\']*(?:\'\'[^\']*)*\')|("[^"\\\\]*(?:\\\\.[^"\\\\]*)*")|([^:])(\\?|:[A-Za-z0-9_\-]+)/';
            break;
            
            case 0:
            default:
                $params_regex = '/(\'[^\'\\\\]*(?:\\\\.[^\'\\\\]*)*\')|("[^"\\\\]*(?:\\\\.[^"\\\\]*)*")|([^:])(\\?|:[A-Za-z0-9_\-]+)/';
            break;
        }
        
        $result             = preg_split($params_regex, $statement, -1, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);
        
        $param_type         = $this->driver_param_type;
        $has_named          = false;
        $has_anon           = false;
        $warn_text          = 'Invalid parameter number: mixed named and positional parameters';
        
        $chunks_num         = 0;
        $param_num          = 0;
        $params_info        = array();
        $named_params       = array();
        
        foreach($result as &$chunk)
        {
            switch($chunk[0])
            {
                case ':':
                
                    if($has_anon)
                    {
                        $this->set_error(0, $warn_text, 'HY093', PDO::ERRMODE_WARNING, 'prepare');
                        return false;
                    }
                    
                    if(isset($named_params[$chunk]))
                    {
                        $named_params[$chunk]++;
                        $chunk .= $named_params[$chunk];
                    }
                    else
                    {
                        $named_params[$chunk] = 1;
                    }
                    
                    $has_named = true;
                    $param_num++;
                    
                    switch($param_type)
                    {
                        case -1:
                            $params_info[$chunk] = $chunks_num;
                        break;
                        
                        case 1:
                            $key = $chunk;
                            $chunk = str_replace('-', '__', $chunk);
                            $params_info[$key] = $chunk;
                        break;
                        
                        case 2:
                            $params_info[$chunk] = $param_num;
                            $chunk = '$' . $param_num;
                        break;
                        
                        case 0:
                            $params_info[$chunk] = $param_num;
                            $chunk = '?';
                        break;
                    }
                    
                break;
                
                case '?':
                
                    if($has_named)
                    {
                        $this->set_error(0, $warn_text, 'HY093', PDO::ERRMODE_WARNING, 'prepare');
                        return false;
                    }
                    
                    $has_anon = true;
                    $param_num++;
                    
                    switch($param_type)
                    {
                        case -1:
                            $params_info[$param_num] = $chunks_num;
                        break;
                        
                        case 1:
                            $params_info[$param_num] = ':p' . $param_num;
                            $chunk = $params_info[$param_num];
                        break;
                        
                        case 2:
                            $params_info[$param_num] = $param_num;
                            $chunk = '$' . $param_num;
                        break;
                        
                        case 0:
                            $params_info[$param_num] = $param_num;
                        break;
                    }
                    
                break;
            }
            
            $chunks_num++;
        }
        
        if($param_type == -1)
        {
            $this->prepared =& $result;
        }
        else
        {
			// Do not explode with a space. A space breaks this in Postgres, because ads a space between 'E' and '\\s+':
			// regexp_split_to_table('some string', E'\\s+')
            $this->prepared = implode('', $result);
        }
        
        $st = phppdo_base_statement::_new_instance($driver_options[PDO::ATTR_STATEMENT_CLASS], $statement);
        $st->_setup($this->link, $this, $driver_options, $this->prepared, $params_info);
        
        return $st;
    }
    
    abstract public function quote(&$string, $parameter_type = -1);
    
    public function rollBack()
    {
        if(!$this->in_transaction)
        {
            throw new PDOException('There is no active transaction');
        }
        
        $this->in_transaction = false;
        return true;
    }
    
    public function nextRowset()
    {
        return false;
    }
    
    public function setAttribute($attribute, $value, &$source = null, $func = 'PDO::setAttribute', &$last_error = null)
    {
        if($source == null) $source =& $this->driver_options;
        
        switch($attribute)
        {
            // read only
            case PDO::ATTR_DRIVER_NAME:
            case PDO::ATTR_CLIENT_VERSION:
            case PDO::ATTR_SERVER_INFO:
            case PDO::ATTR_SERVER_VERSION:
                return false;
            break;
        }
        
        if(isset($source[$attribute]))
        {
            switch($attribute)
            {
                case PDO::ATTR_STATEMENT_CLASS:
                    if($value === null)
                    {
                        $value = array(get_class($this) . '_statement');
                    }
                    else if(!$this->check_attr_statement_class($value, $func))
                    {
                        return false;
                    }
                break;
                
                case PDO::ATTR_CASE:
                    switch($value)
                    {
                        case PDO::CASE_LOWER:
                        case PDO::CASE_NATURAL:
                        case PDO::CASE_UPPER:
                        break;
                        
                        default:
                            return false;
                        break;
                    }
                break;
                
                case PDO::ATTR_ERRMODE:
                    switch($value)
                    {
                        case PDO::ERRMODE_SILENT:
                        case PDO::ERRMODE_WARNING:
                        case PDO::ERRMODE_EXCEPTION:
                        break;
                        
                        default:
                            return false;
                        break;
                    }
                break;
                
                case PDO::ATTR_ORACLE_NULLS:
                    switch($value)
                    {
                        case PDO::NULL_NATURAL:
                        case PDO::NULL_EMPTY_STRING:
                        case PDO::NULL_TO_STRING:
                        break;
                        
                        default:
                            return false;
                        break;
                    }
                break;
                
                case PDO::ATTR_DEFAULT_FETCH_MODE:
                    switch($value)
                    {
                        case PDO::FETCH_LAZY:
                        case PDO::FETCH_ASSOC:
                        case PDO::FETCH_NAMED:
                        case PDO::FETCH_NUM:
                        case PDO::FETCH_BOTH:
                        case PDO::FETCH_OBJ:
                        case PDO::FETCH_BOUND:
                        case PDO::FETCH_COLUMN:
                        case PDO::FETCH_INTO:
                        case PDO::FETCH_FUNC:
                        case PDO::FETCH_GROUP:
                        case PDO::FETCH_UNIQUE:
                        case PDO::FETCH_KEY_PAIR:
                        case PDO::FETCH_CLASS:
                        case PDO::FETCH_CLASSTYPE:
                        case PDO::FETCH_SERIALIZE:
                        break;
                        
                        default:
                            return false;
                        break;
                    }
                break;
            }
            
            $source[$attribute] = $value;
            return true;
        }
        
        $this->set_error(0, 'Driver does not support this function: driver does not support that attribute', 'IM001', PDO::ERRMODE_WARNING, $func, $last_error);
        
        // set driver specific error code
        if($last_error !== null) $last_error[1] = -1;
        else $this->last_error[1] = -1;
        
        return false;
    }
    
    abstract public function set_driver_error($state = null, $mode = PDO::ERRMODE_SILENT, $func = '');
    
    public function set_error($code, $message, $state = 'HY000', $mode = PDO::ERRMODE_SILENT, $func = '', &$last_error = null)
    {
        if($last_error == null) $last_error =& $this->last_error;
        $last_error = array($state, $code, $message);
        $action     = ($mode >= $this->driver_options[PDO::ATTR_ERRMODE]) ? $mode : $this->driver_options[PDO::ATTR_ERRMODE];
        
        switch($action)
        {
            case PDO::ERRMODE_EXCEPTION:
                $e = new PDOException($this->get_error_str($code, $message, $state), $code);
                $e->errorInfo = $last_error;
                throw $e;
            break;
            
            case PDO::ERRMODE_WARNING:
                trigger_error($this->get_error_str($code, $message, $state, $func), E_USER_WARNING);
            break;
            
            case PDO::ERRMODE_SILENT:
            default:
            
            break;
        }
    }
    
    public function set_error_info($info)
    {
        $this->last_error = $info;
    }
    
    public function clear_error(&$last_error = null)
    {
        if($last_error == null) $last_error =& $this->last_error;
        $last_error = array(PDO::ERR_NONE, '', '');
    }
    
    public function filter_result(&$value, $stringify, $nulls)
    {
        if(is_int($value) || is_float($value))
        {
            if($stringify) $value = (string)$value;
        }
        else
        {
            switch($nulls)
            {
                case PDO::NULL_EMPTY_STRING:
                    if($value === '') $value = null;
                break;
                
                case PDO::NULL_TO_STRING:
                    if($value === null) $value = '';
                break;
            }
        }
    }
    
    abstract protected function connect(&$username, &$password, &$driver_options);
    abstract protected function disconnect();
    
    protected function set_attributes($attributes, &$source)
    {
        $s = null;
        foreach($attributes as $key)
        {
            if(isset($source[$key]))
            {
                $this->setAttribute($key, $source[$key], $s, 'PDO::__construct');
                unset($source[$key]);
            }
        }
    }
    
    private function close()
    {
        if($this->link)
        {
            if($this->in_transaction)
            {
                $this->rollback();
            }
            
            if(!$this->driver_options[PDO::ATTR_PERSISTENT])
            {
                $this->disconnect();
            }
        }
        
        $this->link = null;
    }
    
    private function check_attr_statement_class(&$data, &$func)
    {
        if(
            is_array($data) &&
            isset($data[0]) &&
            class_exists($data[0])
        ) {
            if(isset($data[1]) && !is_array($data[1]))
            {
                $this->set_error(0, 'General error: PDO::ATTR_STATEMENT_CLASS requires format array(classname, array(ctor_args)); ctor_args must be an array', 'HY000', PDO::ERRMODE_WARNING, $func);
                return false;
            }
            
            return true;
        }
        
        $this->set_error(0, 'General error: PDO::ATTR_STATEMENT_CLASS requires format array(classname, array(ctor_args)); the classname must be a string specifying an existing class', 'HY000', PDO::ERRMODE_WARNING, $func);
        return false;
    }
    
    private function get_error_str($code, $message, $state, $func = '')
    {
        if($func)
        {
            if(strpos($func, '::') === false)
            {
                $class_name = 'PDO';
            }
            else
            {
                $arr        = explode('::', $func);
                $class_name = $arr[0];
                $func       = $arr[1];
            }
            
            if(isset($_SERVER['GATEWAY_INTERFACE']))
            {
                $prefix = $class_name . '::' . $func . '() [<a href=\'function.' . $class_name . '-' . $func . '\'>function.' . $class_name . '-' . $func . '</a>]: ';
            }
            else
            {
                $prefix = $class_name . '::' . $func . '(): ';
            }
        }
        else
        {
            $prefix = '';
        }
        
        if($code) return $prefix . 'SQLSTATE['.$state.'] ['.$code.'] ' . $message;
        return $prefix . 'SQLSTATE['.$state.']: ' . $message;
    }
}
?>