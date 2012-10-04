<?php
/**
 * Project:     PHPPDO
 * File:        sqlite2.php
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

class phppdo_sqlite2 extends phppdo_base
{
    private $autocommit;
    
    public function __construct(&$dsn, &$username, &$password, &$driver_options)
    {
        if(!extension_loaded('sqlite'))
        {
            throw new PDOException('could not find extension');
        }
        
        parent::__construct($dsn, $username, $password, $driver_options);
        $this->driver_quote_type = 1;
    }
    
    public function beginTransaction()
    {
        parent::beginTransaction();
        
        if(!sqlite_exec($this->link, 'BEGIN'))
        {
            $this->set_driver_error(null, PDO::ERRMODE_EXCEPTION, 'beginTransaction');
        }
        
        return true;
    }
    
    public function commit()
    {
        parent::commit();
        
        if(!sqlite_exec($this->link, 'COMMIT'))
        {
            $this->set_driver_error(null, PDO::ERRMODE_EXCEPTION, 'commit');
        }
        
        $this->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);
        return true;
    }
    
    public function exec(&$statement)
    {
        if(@sqlite_exec($this->link, $statement))
        {
            return sqlite_changes($this->link);
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
            
            case PDO::ATTR_CLIENT_VERSION:
                return sqlite_libversion();
            break;
            
            case PDO::ATTR_SERVER_VERSION:
                return sqlite_libversion();
            break;
            
            default:
                return parent::getAttribute($attribute, $source, $func, $last_error);
            break;
        }
    }
    
    public function lastInsertId($name = '')
    {
        return sqlite_last_insert_rowid($this->link);
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
                return '\'' . sqlite_escape_string($param) . '\'';
            break;
        }
    }
    
    public function rollBack()
    {
        parent::rollback();
        
        if(!sqlite_exec($this->link, 'ROLLBACK'))
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
        $errno = sqlite_last_error($this->link);
        if($state === null) $state = 'HY000';
        
        $this->set_error($errno, sqlite_error_string($errno), $state, $mode, $func);
    }
    
    protected function connect(&$username, &$password, &$driver_options)
    {
        $database = key($this->dsn);
        $error = '';
        
        if(isset($driver_options[PDO::ATTR_PERSISTENT]) && $driver_options[PDO::ATTR_PERSISTENT])
        {
            $this->link = @sqlite_popen($database, 0666, $error);
        }
        else
        {
            $this->link = @sqlite_open($database, 0666, $error);
        }
        
        if(!$this->link)
        {
            $this->set_error(0, $error, 'HY000', PDO::ERRMODE_EXCEPTION, '__construct');
        }
    }
    
    protected function disconnect()
    {
        sqlite_close($this->link);
    }
}
?>