<?php
/**
 * Project:     PHPPDO
 * File:        phppdo.php
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
 * @version 1.4
 */

if(!class_exists('PDO'))
{
    require_once(dirname(__FILE__) . '/pdoabstract.php');
}

function phppdo_drivers()
{
    return PHPPDO::getAvailableDrivers();
}

class PHPPDO extends PDO
{
    private $path;
    private $driver;
    private $driver_name;
    
    public function __construct($dsn, $username = '', $password = '', $driver_options = array())
    {
        if(!is_array($driver_options)) $driver_options = array();
        $this->setup();
        
        $driver_dsn =& $this->parse_dsn($dsn);
        
        if($this->driver_name == 'uri')
        {
            $driver_dsn = $this->get_uri_dsn(key($driver_dsn));
        }
        
        $this->init_driver($driver_dsn, $username, $password, $driver_options);
    }
    
    public static function getAvailableDrivers()
    {
        if(func_num_args() > 0) return false;
        
        $result = array();
        if($handle = opendir(dirname(__FILE__) . '/drivers'))
        {
            while (false !== ($file = readdir($handle)))
            {
                if($file == '.' || $file == '..') continue;
                $driver = explode('_', $file);
                if(isset($driver[1])) continue;
                $driver = str_replace('.php', '', $driver[0]);
                if($driver == 'base') continue;
                
                $skip = false;
                switch($driver)
                {
                    case 'mysql':
                    case 'mysqli':
                        $driver = 'mysql';
                        $skip = in_array($driver, $result);
                    break;
                    
                    case 'mssql':
                    case 'sybase':
                        if(PHP_OS == 'WINNT')
                        {
                            $driver = 'mssql';
                        }
                        else
                        {
                            $driver = 'dblib';
                        }
                        
                        $skip = in_array($driver, $result);
                    break;
                }
                
                if($skip) continue;
                $result[] = $driver;
            }
            
            closedir($handle);
        }
        
        return $result;
    }
    
    public function beginTransaction()
    {
        return $this->driver->beginTransaction();
    }
    
    public function commit()
    {
        return $this->driver->commit();
    }
    
    public function errorCode()
    {
        if(func_num_args() > 0) return false;
        return $this->driver->errorCode();
    }
    
    public function errorInfo()
    {
        if(func_num_args() > 0) return false;
        return $this->driver->errorInfo();
    }
    
    public function exec($statement)
    {
        if(!$statement || func_num_args() != 1) return false;
        
        $driver = $this->driver;
        $result = $driver->exec($statement);
        
        if($result !== false)
        {
            //$driver->filter_result($result, $driver->driver_options[PDO::ATTR_STRINGIFY_FETCHES], $driver->driver_options[PDO::ATTR_ORACLE_NULLS]);
            $driver->clear_error();
        }
        else
        {
            $driver->set_driver_error(null, PDO::ERRMODE_SILENT, 'exec');
        }
        
        return $result;
    }
    
    public function getAttribute($attribute)
    {
        if(func_num_args() != 1 || !is_int($attribute)) return false;
        return $this->driver->getAttribute($attribute);
    }
    
    public function lastInsertId($name = '')
    {
        if(!is_string($name) || func_num_args() > 1) return false;
        
        $result = $this->driver->lastInsertId($name);
        $driver = $this->driver;
        
        if($result !== false)
        {
            $driver->filter_result($result, $driver->driver_options[PDO::ATTR_STRINGIFY_FETCHES], $driver->driver_options[PDO::ATTR_ORACLE_NULLS]);
        }
        
        return $result;
    }
    
    public function prepare($statement, $driver_options = array())
    {
        return $this->driver->prepare($statement, $driver_options);
    }
    
    public function query($statement, $mode = 0, $param = '', $ctorargs = array())
    {
        $st = $this->prepare($statement);
        if(!$st) return false;
        
        try
        {
            if(!$st->execute())
            {
                $this->driver->set_error_info($st->errorInfo());
                return false;
            }
        }
        catch(PDOException $e)
        {
            $this->driver->set_error_info($st->errorInfo());
            throw $e;
        }
        
        if(!$mode) return $st;
        if(!$st->setFetchMode($mode, $param, $ctorargs)) return false;
        return $st;
    }
    
    public function quote($string, $parameter_type = -1)
    {
        if(!func_num_args() || is_array($string) || is_object($string)) return false;
        return $this->driver->quote($string, $parameter_type);
    }
    
    public function rollBack()
    {
        return $this->driver->rollback();
    }
    
    public function setAttribute($attribute, $value)
    {
        if(func_num_args() != 2) return false;
        return $this->driver->setAttribute($attribute, $value);
    }
    
    
    // pgsql specific
    public function pgsqlLOBCreate()
    {
        return $this->driver->pgsqlLOBCreate();
    }
    
    public function pgsqlLOBOpen($oid)
    {
        return $this->driver->pgsqlLOBOpen($oid);
    }
    
    public function pgsqlLOBUnlink($oid)
    {
        return $this->driver->pgsqlLOBUnlink($oid);
    }
    
    
    // private
    private function load($file)
    {
        return include_once($this->path . '/' . $file);
    }
    
    private function setup()
    {
        $this->path = dirname(__FILE__);
        
        // load pdo exception and statement
        if(!class_exists('PDOException'))
        {
            $this->load('pdoexception.php');
        }
        
        if(!class_exists('PDOStatement'))
        {
            $this->load('pdostatementabstract.php');
        }
        
        $this->load('drivers/base.php');
        $this->load('drivers/base_statement.php');
    }
    
    private function get_uri_dsn($driver_dsn)
    {
        $uri_data =& $this->parse_uri($driver_dsn);
        switch($uri_data[0])
        {
            case 'file':
                if(false === ($dsn = file_get_contents($uri_data[1])))
                {
                    throw new PDOException('invalid data source name');
                }
                
                return $this->parse_dsn($dsn);
            break;
            
            default:
                throw new PDOException('invalid data source name');
            break;
        }
    }
    
    private function &parse_dsn(&$dsn)
    {
        $pos = strpos($dsn, ':');
        if($pos === false) throw new PDOException('invalid data source name');
        
        $this->driver_name = strtolower(trim(substr($dsn, 0, $pos)));
        if(!$this->driver_name) throw new PDOException('could not find driver');
        
        $driver_dsn = array();
        $d_dsn = trim(substr($dsn, $pos + 1));
        
        if($d_dsn)
        {
            $arr = explode(';', $d_dsn);
            
            foreach($arr as &$pair)
            {
                $kv = explode('=', $pair);
                $driver_dsn[strtolower(trim($kv[0]))] = isset($kv[1]) ? trim($kv[1]) : '';
            }
        }
        
        return $driver_dsn;
    }
    
    private function &parse_uri($dsn)
    {
        $pos = strpos($dsn, ':');
        if($pos === false) throw new PDOException('invalid data source name');
        
        $data = array(strtolower(trim(substr($dsn, 0, $pos))));
        $data[] = trim(substr($dsn, $pos + 1));
        
        return $data;
    }
    
    private function init_driver(&$dsn, &$username, &$password, &$driver_options)
    {
        if(isset($dsn['extension']) && $dsn['extension'])
        {
            $driver = strtolower($dsn['extension']);
        }
        else
        {
            $driver = $this->driver_name;
            switch($driver)
            {
                case 'mysqli':
                    if(extension_loaded('mysqli'))
                        $driver = 'mysqli';
                break;
                case 'mysql':
                    if(extension_loaded('mysql'))
                        $driver = 'mysql';
                break;
                 
                case 'dblib':
                case 'mssql':
                    if(extension_loaded('mssql'))
                        $driver = 'mssql';
                    else
                        $driver = 'sybase';
                break;
            }
        }
        
        if(!@$this->load('drivers/' . $driver . '.php'))
        {
            throw new PDOException('could not find driver');
        }
        
        $driver_options[PDO::ATTR_DRIVER_NAME] = $this->driver_name;
        
        // load statement
        if(!class_exists('phppdo_' . $driver . '_statement'))
        {
            $this->load('drivers/' . $driver . '_statement.php');
        }
        
        if(!isset($driver_options[PDO::ATTR_STATEMENT_CLASS]))
        {
            $driver_options[PDO::ATTR_STATEMENT_CLASS] = array('phppdo_' . $driver . '_statement');
        }
        
        $class = 'phppdo_' . $driver;
        $this->driver = new $class($dsn, $username, $password, $driver_options);
    }
}
?>
