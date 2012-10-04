<?php
/**
 * Project:     PHPPDO
 * File:        oci.php
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

class phppdo_oci extends phppdo_base
{
    public $autocommit;
    private $temp_result;
    
    public function __construct(&$dsn, &$username, &$password, &$driver_options)
    {
        if(!extension_loaded('oci8'))
        {
            throw new PDOException('could not find extension');
        }
        
        $this->driver_param_type = 1;
        $this->driver_quote_type = 1;
        
        if(!isset($driver_options[PDO::ATTR_PREFETCH]))
        {
            $driver_options[PDO::ATTR_PREFETCH] = @ini_get('oci8.default_prefetch');
        }
        
        parent::__construct($dsn, $username, $password, $driver_options);
    }
    
    public function commit()
    {
        parent::commit();
        
        if(!oci_commit($this->link))
        {
            $this->set_driver_error(null, PDO::ERRMODE_EXCEPTION, 'commit');
        }
        
        $this->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);
        return true;
    }
    
    public function exec(&$statement)
    {
        $result =& $this->temp_result;
        if(
            ($result = oci_parse($this->link, $statement)) &&
            @oci_execute($result, ($this->autocommit ? OCI_COMMIT_ON_SUCCESS : OCI_DEFAULT))
        ) {
            if('SELECT' == oci_statement_type($result))
            {
                oci_free_statement($result);
                $result = null;
                
                return 0;
            }
            
            $rows = oci_num_rows($result);
            $result = null;
            
            return $rows;
        }
        
        return false;
    }
    
    public function getAttribute($attribute, &$source = null, $func = 'PDO::getAttribute', &$last_error = null)
    {
        switch($attribute)
        {
            case PDO::ATTR_AUTOCOMMIT:
                return $this->autocommit;
            break;
            
            case PDO::ATTR_PREFETCH:
                
            break;
            
            case PDO::ATTR_CLIENT_VERSION:
                return oci_server_version($this->link);
            break;
            
            case PDO::ATTR_SERVER_VERSION:
                $ver = oci_server_version($this->link);
                if(preg_match('/([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)/', $ver, $match))
                {
                    return $match[1];
                }
                
                return $ver;
            break;
            
            case PDO::ATTR_SERVER_INFO:
                return oci_server_version($this->link);
            break;
            
            default:
                return parent::getAttribute($attribute, $source, $func, $last_error);
            break;
        }
    }
    
    public function lastInsertId($name = '')
    {
        if(!$name) return false;
        
        if(
            ($result = oci_parse($this->link, 'SELECT '.$name.'.CURRVAL FROM dual')) &&
            @oci_execute($result, ($this->autocommit ? OCI_COMMIT_ON_SUCCESS : OCI_DEFAULT))
        ) {
            $row = oci_fetch_row($result);
            return intval($row[0]);
        }
        
        return false;
    }
    
    public function prepare(&$statement, &$options)
    {
        if(!($st = parent::prepare($statement, $options))) return false;
        
        $result = oci_parse($this->link, $this->prepared);
        
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
                return $param ? '1' : '0';
            break;
            
            case PDO::PARAM_NULL:
                return 'NULL';
            break;
            
            case PDO::PARAM_INT:
                return is_null($param) ? 'NULL' : (is_int($param) ? $param : (float)$param);
            break;
            
            default:
                return '\'' . str_replace('\'', '\'\'', $param) . '\'';
            break;
        }
    }
    
    public function rollBack()
    {
        parent::rollback();
        
        if(!oci_rollback($this->link))
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
                if(!$value && $this->in_transaction)
                {
                    $this->commit();
                }
                
                $this->autocommit = $value ? true : false;
                return true;
            break;
            
            
            default:
                return parent::setAttribute($attribute, $value, $source, $func, $last_error);
            break;
        }
        
        return false;
    }
    
    public function set_driver_error($state = null, $mode = PDO::ERRMODE_SILENT, $func = '')
    {
        if($this->temp_result)
        {
            $error = oci_error($this->temp_result);
            $this->temp_result = null;
        }
        else
        {
            $error = $this->link ? oci_error($this->link) : oci_error();
        }
        
        if($state === null) $state = 'HY000';
        $this->set_error($error['code'], $error['message'], $state, $mode, $func);
    }
    
    protected function connect(&$username, &$password, &$driver_options)
    {
        $dbname     = isset($this->dsn['dbname'])   ? $this->dsn['dbname']  : '';
        $charset    = isset($this->dsn['charset'])  ? $this->dsn['charset'] : (isset($_ENV['NLS_LANG']) ? $_ENV['NLS_LANG'] : 'WE8ISO8859P1');
        
        ob_start();
        
        if(isset($driver_options[PDO::ATTR_PERSISTENT]) && $driver_options[PDO::ATTR_PERSISTENT])
        {
            $this->link = oci_pconnect($username, $password, $dbname, $charset);
        }
        else
        {
            $this->link = oci_new_connect($username, $password, $dbname, $charset);
        }
        
        $error = ob_get_contents();
        ob_end_clean();
        
        if(!$this->link)
        {
            $this->set_driver_error(null, PDO::ERRMODE_EXCEPTION, '__construct');
        }
        else if($error)
        {
            $this->set_error(0, $this->clear_warning($error), 'HY000', PDO::ERRMODE_EXCEPTION, '__construct');
        }
        
    }
    
    protected function disconnect()
    {
        oci_close($this->link);
    }
    
    private function clear_warning($msg)
    {
        $pos = strpos($msg, '): ');
        $pos2 = strrpos($msg, ' in ');
        if($pos !== false && $pos2 !== false)
        {
            $pos += 3;
            return substr($msg, $pos, ($pos2 - $pos));
        }
        
        return $msg;
    }
}
?>