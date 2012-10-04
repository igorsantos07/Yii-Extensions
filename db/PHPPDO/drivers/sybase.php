<?php
/**
 * Project:     PHPPDO
 * File:        sybase.php
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

class phppdo_sybase extends phppdo_base
{
    private $autocommit;
    
    public function __construct(&$dsn, &$username, &$password, &$driver_options)
    {
        if(!extension_loaded('sybase_ct'))
        {
            throw new PDOException('could not find extension');
        }
        
        parent::__construct($dsn, $username, $password, $driver_options);
        $this->driver_quote_type = 1;
    }
    
    public function beginTransaction()
    {
        parent::beginTransaction();
        
        if(!sybase_query('BEGIN TRANSACTION', $this->link))
        {
            $this->set_driver_error(null, PDO::ERRMODE_EXCEPTION, 'beginTransaction');
        }
        
        return true;
    }
    
    public function commit()
    {
        parent::commit();
        
        if(!sybase_query('COMMIT', $this->link))
        {
            $this->set_driver_error(null, PDO::ERRMODE_EXCEPTION, 'commit');
        }
        
        $this->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);
        return true;
    }
    
    public function exec(&$statement)
    {
        if($result = @sybase_query($statement, $this->link))
        {
            if(is_resource($result))
            {
                sybase_free_result($result);
                return 0;
            }
            
            return sybase_affected_rows($this->link);
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
            
            default:
                return parent::getAttribute($attribute, $source, $func, $last_error);
            break;
        }
        
        return false;
    }
    
    public function lastInsertId($name = '')
    {
        if($result = sybase_query('SELECT @@IDENTITY', $this->link))
        {
            $row = sybase_fetch_row($result);
            if($row[0] === null) return -1;
            return $row[0];
        }
        
        return false;
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
                return '\'' . str_replace('\'', '\'\'', $param) . '\'';
            break;
        }
    }
    
    public function rollBack()
    {
        parent::rollback();
        
        if(!sybase_query('ROLLBACK', $this->link))
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
                $this->autocommit = $value ? 1 : 0;
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
        if($state === null) $state = 'HY000';
        $this->set_error(-1, sybase_get_last_message(), $state, $mode, $func);
    }
    
    protected function connect(&$username, &$password, &$driver_options)
    {
        $host       = isset($this->dsn['host'])         ? $this->dsn['host']                : 'SYBASE';
        $dbname     = isset($this->dsn['dbname'])       ? $this->dsn['dbname']              : '';
        $charset    = isset($this->dsn['charset'])      ? intval($this->dsn['charset'])     : '';
        
        if(isset($driver_options[PDO::ATTR_PERSISTENT]) && $driver_options[PDO::ATTR_PERSISTENT])
        {
            $this->link = @sybase_pconnect($host, $username, $password, $charset);
        }
        else
        {
            // hope this opens a new connection every time
            $app_name = uniqid('phppdo_');
            $this->link = @sybase_connect($host, $username, $password, $charset, $app_name);
        }
        
        if(!$this->link)
        {
            $this->set_driver_error('28000', PDO::ERRMODE_EXCEPTION, '__construct');
        }
        
        if($dbname)
        {
            if(!@sybase_select_db($dbname, $this->link))
            {
                $this->set_driver_error(null, PDO::ERRMODE_EXCEPTION, '__construct');
            }
        }
    }
    
    protected function disconnect()
    {
        sybase_close($this->link);
    }
}
?>