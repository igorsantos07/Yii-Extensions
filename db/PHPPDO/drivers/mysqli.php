<?php
/**
 * Project:     PHPPDO
 * File:        mysqli.php
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

class phppdo_mysqli extends phppdo_base
{
    public function __construct(&$dsn, &$username, &$password, &$driver_options)
    {
        if(!extension_loaded('mysqli'))
        {
            throw new PDOException('could not find extension');
        }
        
        // set default values
        $this->driver_options[phppdo_base::MYSQL_ATTR_USE_BUFFERED_QUERY]   = 1;
        $this->driver_options[phppdo_base::MYSQL_ATTR_LOCAL_INFILE]         = false;
        $this->driver_options[phppdo_base::MYSQL_ATTR_INIT_COMMAND]         = '';
        $this->driver_options[phppdo_base::MYSQL_ATTR_READ_DEFAULT_FILE]    = false;
        $this->driver_options[phppdo_base::MYSQL_ATTR_READ_DEFAULT_GROUP]   = false;
        $this->driver_options[phppdo_base::MYSQL_ATTR_MAX_BUFFER_SIZE]      = 1048576;
        $this->driver_options[phppdo_base::MYSQL_ATTR_DIRECT_QUERY]         = 1;
        
        parent::__construct($dsn, $username, $password, $driver_options);
        $this->driver_param_type = 0;
    }
    
    public function commit()
    {
        parent::commit();
        
        if(!mysqli_commit($this->link))
        {
            $this->set_driver_error(null, PDO::ERRMODE_EXCEPTION, 'commit');
        }
        
        $this->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);
        return true;
    }
    
    public function exec(&$statement)
    {
        if($result = mysqli_query($this->link, $statement, MYSQLI_USE_RESULT))
        {
            if(is_object($result))
            {
                mysqli_free_result($result);
                return 0;
            }
            
            return mysqli_affected_rows($this->link);
        }
        
        return false;
    }
    
    public function getAttribute($attribute, &$source = null, $func = 'PDO::getAttribute', &$last_error = null)
    {
        if($source == null) $source =& $this->driver_options;
        
        switch($attribute)
        {
            case PDO::ATTR_AUTOCOMMIT:
                $result = mysqli_query($this->link, 'SELECT @@AUTOCOMMIT', MYSQLI_USE_RESULT);
                
                if(!$result)
                {
                    $this->set_driver_error(null, PDO::ERRMODE_EXCEPTION, $func);
                }
                
                $row = mysqli_fetch_row($result);
                mysqli_free_result($result);
                
                return intval($row[0]);
            break;
            
            case PDO::ATTR_CLIENT_VERSION:
                return mysqli_get_client_info();
            break;
            
            case PDO::ATTR_CONNECTION_STATUS:
                return mysqli_get_host_info($this->link);
            break;
            
            case PDO::ATTR_SERVER_INFO:
                return mysqli_stat($this->link);
            break;
            
            case PDO::ATTR_SERVER_VERSION:
                return mysqli_get_server_info($this->link);
            break;
            
            default:
                return parent::getAttribute($attribute, $source, $func, $last_error);
            break;
        }
    }
    
    public function lastInsertId($name = '')
    {
        return mysqli_insert_id($this->link);
    }
    
    public function prepare(&$statement, &$options)
    {
        if(!($st = parent::prepare($statement, $options))) return false;
        $result = mysqli_prepare($this->link, $this->prepared);
        
        if(!$result)
        {
            $this->set_driver_error(null, PDO::ERRMODE_SILENT, 'prepare');
            return false;
        }
        
        $st->_set_result($result);
        return $st;
    }
    
    public function quote(&$param, $parameter_type = -1)
    {
        switch($parameter_type)
        {
            case PDO::PARAM_BOOL:
                return $param ? 1 : 0;
            break;
            
            case PDO::PARAM_NULL:
                return 'NULL';
            break;
            
            case PDO::PARAM_INT:
                return is_null($param) ? 'NULL' : (is_int($param) ? $param : (float)$param);
            break;
            
            default:
                return '\'' . mysqli_real_escape_string($this->link, $param) . '\'';
            break;
        }
    }
    
    public function rollBack()
    {
        parent::rollback();
        
        if(!mysqli_rollback($this->link))
        {
            $this->set_driver_error(null, PDO::ERRMODE_EXCEPTION, 'rollBack');
        }
        
        $this->setAttribute(PDO::ATTR_AUTOCOMMIT, $this->driver_options[PDO::ATTR_AUTOCOMMIT]);
        return true;
    }
    
    public function setAttribute($attribute, $value, &$source = null, $func = 'PDO::setAttribute', &$last_error = null)
    {
        if($source == null) $source =& $this->driver_options;
        
        switch($attribute)
        {
            case PDO::ATTR_AUTOCOMMIT:
                $value = $value ? 1 : 0;
                if(!mysqli_autocommit($this->link, $value))
                {
                    $this->set_driver_error(null, PDO::ERRMODE_EXCEPTION, $func);
                }
                
                return true;
            break;
            
            case PDO::ATTR_TIMEOUT:
                $value = intval($value);
                if($value > 1 && mysqli_options($this->link, MYSQLI_OPT_CONNECT_TIMEOUT, $value))
                {
                    $source[PDO::ATTR_TIMEOUT] = $value;
                    return true;
                }
            break;
            
            case phppdo_base::MYSQL_ATTR_LOCAL_INFILE:
                $value = $value ? true : false;
                if(mysqli_options($this->link, MYSQLI_OPT_LOCAL_INFILE, $value))
                {
                    $source[phppdo_base::MYSQL_ATTR_LOCAL_INFILE] = $value;
                    return true;
                }
            break;
            
            case phppdo_base::MYSQL_ATTR_INIT_COMMAND:
                if($value && mysqli_options($this->link, MYSQLI_INIT_COMMAND, $value))
                {
                    $source[phppdo_base::MYSQL_ATTR_INIT_COMMAND] = $value;
                    return true;
                }
            break;
            
            case phppdo_base::MYSQL_ATTR_READ_DEFAULT_FILE:
                $value = $value ? true : false;
                if(mysqli_options($this->link, MYSQLI_READ_DEFAULT_FILE, $value))
                {
                    $source[phppdo_base::MYSQL_ATTR_READ_DEFAULT_FILE] = $value;
                    return true;
                }
            break;
            
            case phppdo_base::MYSQL_ATTR_READ_DEFAULT_GROUP:
                $value = $value ? true : false;
                if(mysqli_options($this->link, MYSQLI_READ_DEFAULT_GROUP, $value))
                {
                    $source[phppdo_base::MYSQL_ATTR_READ_DEFAULT_GROUP] = $value;
                    return true;
                }
            break;
            
            /*case phppdo_base::MYSQL_ATTR_MAX_BUFFER_SIZE:
                
            break;
            
            case phppdo_base::MYSQL_ATTR_DIRECT_QUERY:
                
            break;*/
            
            default:
                return parent::setAttribute($attribute, $value, $source, $func, $last_error);
            break;
        }
        
        return false;
    }
    
    public function set_driver_error($state = null, $mode = PDO::ERRMODE_SILENT, $func = '')
    {
        if($state === null) $state = mysqli_sqlstate($this->link);
        $this->set_error(mysqli_errno($this->link), mysqli_error($this->link), $state, $mode, $func);
    }
    
    protected function connect(&$username, &$password, &$driver_options)
    {
        $this->link = mysqli_init();
        
        $this->set_attributes(array
        (
            PDO::ATTR_TIMEOUT,
            phppdo_base::MYSQL_ATTR_LOCAL_INFILE,
            phppdo_base::MYSQL_ATTR_INIT_COMMAND,
            phppdo_base::MYSQL_ATTR_READ_DEFAULT_FILE,
            phppdo_base::MYSQL_ATTR_READ_DEFAULT_GROUP
        ), $driver_options);
        
        $host       = isset($this->dsn['host'])         ? $this->dsn['host']            : 'localhost';
        $dbname     = isset($this->dsn['dbname'])       ? $this->dsn['dbname']          : '';
        $port       = isset($this->dsn['port'])         ? intval($this->dsn['port'])    : 0;
        $socket     = isset($this->dsn['unix_socket'])  ? $this->dsn['unix_socket']     : '';
        
        if(!@mysqli_real_connect($this->link, $host, $username, $password, $dbname, $port, $socket))
        {
            $this->set_error(mysqli_connect_errno(), mysqli_connect_error(), 'HY000', PDO::ERRMODE_EXCEPTION, '__construct');
        }
        
        if(isset($this->dsn['charset']))
        {
            if(!mysqli_set_charset($this->link, $this->dsn['charset']))
            {
                $this->set_driver_error(null, PDO::ERRMODE_EXCEPTION, '__construct');
            }
        }
    }
    
    protected function disconnect()
    {
        mysqli_close($this->link);
    }
}

?>