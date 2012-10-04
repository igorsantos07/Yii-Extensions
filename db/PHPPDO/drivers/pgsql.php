<?php
/**
 * Project:     PHPPDO
 * File:        pgsql.php
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

class phppdo_pgsql extends phppdo_base
{
    public $los = array();
    private $autocommit;
    
    public function __construct(&$dsn, &$username, &$password, &$driver_options)
    {
        if(!extension_loaded('pgsql'))
        {
            throw new PDOException('could not find extension');
        }
        
        $this->driver_options[phppdo_base::PGSQL_ATTR_DISABLE_NATIVE_PREPARED_STATEMENT] = false;
        parent::__construct($dsn, $username, $password, $driver_options);
        
        $this->driver_param_type = 2;
        $this->driver_quote_type = 1;
    }
    
    public function beginTransaction()
    {
        parent::beginTransaction();
        
        if(!pg_query($this->link, 'BEGIN'))
        {
            $this->set_driver_error(null, PDO::ERRMODE_EXCEPTION, 'beginTransaction');
        }
        
        return true;
    }
    
    public function commit()
    {
        parent::commit();
        
        if(!pg_query($this->link, 'COMMIT'))
        {
            $this->set_driver_error(null, PDO::ERRMODE_EXCEPTION, 'commit');
        }
        
        $this->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);
        return true;
    }
    
    public function exec(&$statement)
    {
        if($result = @pg_query($this->link, $statement))
        {
            if(is_resource($result))
            {
                pg_free_result($result);
                return 0;
            }
            
            return pg_affected_rows($this->link);
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
                $ver = pg_version($this->link);
                return $ver['client'];
            break;
            
            case PDO::ATTR_CONNECTION_STATUS:
                if(pg_connection_status($this->link) === PGSQL_CONNECTION_OK)
                {
                    return 'Connection OK; waiting to send.';
                }
                else
                {
                    return 'Connection BAD';
                }
            break;
            
            case PDO::ATTR_SERVER_INFO:
                return sprintf('PID: %d; Client Encoding: %s; Is Superuser: %s; Session Authorization: %s; Date Style: %s',
                    pg_get_pid($this->link),
                    pg_client_encoding($this->link),
                    pg_parameter_status($this->link, 'is_superuser'),
                    pg_parameter_status($this->link, 'session_authorization'),
                    pg_parameter_status($this->link, 'DateStyle')
                );
            break;
            
            case PDO::ATTR_SERVER_VERSION:
                return pg_parameter_status($this->link, 'server_version');
            break;
            
            default:
                return parent::getAttribute($attribute, $source, $func, $last_error);
            break;
        }
    }
    
    public function lastInsertId($name = '')
    {
        if(!$name) return false;
        if($result = @pg_query($this->link, 'SELECT currval('.$this->quote($name).')'))
        {
            $row = pg_fetch_row($result);
            return intval($row[0]);
        }
        
        return false;
    }
    
    public function prepare(&$statement, &$options)
    {
        if(!($st = parent::prepare($statement, $options))) return false;
        
        $result_name = uniqid('phpdo_');
        $result = @pg_prepare($this->link, $result_name, $this->prepared);
        
        if(!$result)
        {
            $this->set_driver_error(null, PDO::ERRMODE_SILENT, 'prepare');
            return false;
        }
        
        $st->_set_result($result, $result_name);
        return $st;
    }
    
    public function quote(&$param, $parameter_type = -1)
    {
        switch($parameter_type)
        {
            case PDO::PARAM_BOOL:
                return $param ? 'TRUE' : 'FALSE';
            break;
            
            case PDO::PARAM_NULL:
                return 'NULL';
            break;
            
            case PDO::PARAM_INT:
                return is_null($param) ? 'NULL' : (is_int($param) ? $param : (float)$param);
            break;
            
            default:
                return '\'' . pg_escape_string($this->link, $param) . '\'';
            break;
        }
    }
    
    public function rollBack()
    {
        parent::rollback();
        
        if(!pg_query($this->link, 'ROLLBACK'))
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
        $this->set_error(7, pg_last_error($this->link), $state, $mode, $func);
    }
    
    public function pgsqlLOBCreate()
    {
        if(false === ($oid = pg_lo_create($this->link)))
        {
            $this->set_driver_error(null, PDO::ERRMODE_EXCEPTION, 'pgsqlLOBCreate');
        }
        
        return $oid;
    }
    
    public function pgsqlLOBOpen($oid)
    {
        if(!$stream = tmpfile())
        {
            $this->set_error(7, 'Could not create tem file', 'HY000', PDO::ERRMODE_EXCEPTION, 'pgsqlLOBOpen');
        }
        
        if(!$lo_stream = pg_lo_open($this->link, $oid, 'w'))
        {
            $this->set_error(null, PDO::ERRMODE_EXCEPTION, 'pgsqlLOBOpen');
        }
        
        $this->los[(int)$stream] = array($oid, $lo_stream);
        return $stream;
    }
    
    public function pgsqlLOBUnlink($oid)
    {
        return pg_lo_unlink($this->link, $oid);
    }
    
    protected function connect(&$username, &$password, &$driver_options)
    {
        $dsn = '';
        if(isset($this->dsn['host']))
        {
            $dsn .= 'host=\'' . $this->dsn['host'] . '\'';
        }
        
        if(isset($this->dsn['port']))
        {
            $dsn .= ' port=\'' . $this->dsn['port'] . '\'';
        }
        
        if(isset($this->dsn['dbname']))
        {
            $dsn .= ' dbname=\'' . $this->dsn['dbname'] . '\'';
        }
        
        if($username)
        {
            $dsn .= ' user=\'' . $username . '\'';
        }
        
        if($password)
        {
            $dsn .= ' password=\'' . $password . '\'';
        }
        
        if(isset($driver_options[PDO::ATTR_TIMEOUT]))
        {
            $dsn .= ' connect_timeout=\'' . $driver_options[PDO::ATTR_TIMEOUT] . '\'';
        }
        
        ob_start();
        
        if(isset($driver_options[PDO::ATTR_PERSISTENT]) && $driver_options[PDO::ATTR_PERSISTENT])
        {
            $this->link = pg_pconnect($dsn);
        }
        else
        {
            $this->link = pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
        }
        
        $error = ob_get_contents();
        ob_end_clean();
        
        if(!$this->link)
        {
            $this->set_error(7, $this->clear_warning($error), '08006', PDO::ERRMODE_EXCEPTION, '__construct');
        }
        
        if(isset($this->dsn['charset']))
        {
            // returns -1 on error and 0 on success
            if(pg_set_client_encoding($this->link, $this->dsn['charset']))
            {
                $this->set_driver_error(null, PDO::ERRMODE_EXCEPTION, '__construct');
            }
        }
        
    }
    
    protected function disconnect()
    {
        pg_close($this->link);
    }
    
    private function clear_warning($msg)
    {
        $pos = strpos($msg, 'server: ');
        $pos2 = strrpos($msg, ' in ');
        if($pos !== false && $pos2 !== false)
        {
            $pos += 8;
            return substr($msg, $pos, ($pos2 - $pos));
        }
        
        return $msg;
    }
}
?>