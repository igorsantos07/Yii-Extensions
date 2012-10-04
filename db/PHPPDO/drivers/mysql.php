<?php
/**
 * Project:     PHPPDO
 * File:        mysql.php
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

class phppdo_mysql extends phppdo_base
{
    private $client_flags = 0;
    private $sql_states = array
    (
        1022 => '23000', 1037 => 'HY001', 1038 => 'HY001', 1040 => '08004', 1042 => '08S01', 1043 => '08S01', 1044 => '42000',
        1045 => '28000', 1046 => '3D000', 1047 => '08S01', 1048 => '23000', 1049 => '42000', 1050 => '42S01', 1051 => '42S02',
        1052 => '23000', 1053 => '08S01', 1054 => '42S22', 1055 => '42000', 1056 => '42000', 1057 => '42000', 1058 => '21S01',
        1059 => '42000', 1060 => '42S21', 1061 => '42000', 1062 => '23000', 1063 => '42000', 1064 => '42000', 1065 => '42000',
        1066 => '42000', 1067 => '42000', 1068 => '42000', 1069 => '42000', 1070 => '42000', 1071 => '42000', 1072 => '42000',
        1073 => '42000', 1074 => '42000', 1075 => '42000', 1080 => '08S01', 1081 => '08S01', 1082 => '42S12', 1083 => '42000',
        1084 => '42000', 1090 => '42000', 1091 => '42000', 1101 => '42000', 1102 => '42000', 1103 => '42000', 1104 => '42000',
        1106 => '42000', 1107 => '42000', 1109 => '42S02', 1110 => '42000', 1112 => '42000', 1113 => '42000', 1115 => '42000',
        1118 => '42000', 1120 => '42000', 1121 => '42000', 1131 => '42000', 1132 => '42000', 1133 => '42000', 1136 => '21S01',
        1138 => '22004', 1139 => '42000', 1140 => '42000', 1141 => '42000', 1142 => '42000', 1143 => '42000', 1144 => '42000',
        1145 => '42000', 1146 => '42S02', 1147 => '42000', 1148 => '42000', 1149 => '42000', 1152 => '08S01', 1153 => '08S01',
        1154 => '08S01', 1155 => '08S01', 1156 => '08S01', 1157 => '08S01', 1158 => '08S01', 1159 => '08S01', 1160 => '08S01',
        1161 => '08S01', 1162 => '42000', 1163 => '42000', 1164 => '42000', 1166 => '42000', 1167 => '42000', 1169 => '23000',
        1170 => '42000', 1171 => '42000', 1172 => '42000', 1173 => '42000', 1177 => '42000', 1178 => '42000', 1179 => '25000',
        1184 => '08S01', 1189 => '08S01', 1190 => '08S01', 1203 => '42000', 1207 => '25000', 1211 => '42000', 1213 => '40001',
        1216 => '23000', 1217 => '23000', 1218 => '08S01', 1222 => '21000', 1226 => '42000', 1227 => '42000', 1230 => '42000',
        1231 => '42000', 1232 => '42000', 1234 => '42000', 1235 => '42000', 1239 => '42000', 1241 => '21000', 1242 => '21000',
        1247 => '42S22', 1248 => '42000', 1249 => '01000', 1250 => '42000', 1251 => '08004', 1252 => '42000', 1253 => '42000',
        1261 => '01000', 1262 => '01000', 1263 => '22004', 1264 => '22003', 1265 => '01000', 1280 => '42000', 1281 => '42000',
        1286 => '42000', 1292 => '22007', 1303 => '2F003', 1304 => '42000', 1305 => '42000', 1308 => '42000', 1309 => '42000',
        1310 => '42000', 1311 => '01000', 1312 => '0A000', 1313 => '42000', 1314 => '0A000', 1315 => '42000', 1316 => '42000',
        1317 => '70100', 1318 => '42000', 1319 => '42000', 1320 => '42000', 1321 => '2F005', 1322 => '42000', 1323 => '42000',
        1324 => '42000', 1325 => '24000', 1326 => '24000', 1327 => '42000', 1329 => '02000', 1330 => '42000', 1331 => '42000',
        1332 => '42000', 1333 => '42000', 1335 => '0A000', 1336 => '0A000', 1337 => '42000', 1338 => '42000', 1339 => '20000',
        1365 => '22012', 1367 => '22007', 1370 => '42000', 1397 => 'XAE04', 1398 => 'XAE05', 1399 => 'XAE07', 1400 => 'XAE09',
        1401 => 'XAE03', 1402 => 'XA100', 1403 => '42000', 1406 => '22001', 1407 => '42000', 1410 => '42000', 1413 => '42000',
        1414 => '42000', 1415 => '0A000', 1416 => '22003', 1425 => '42000', 1426 => '42000', 1427 => '42000', 1437 => '42000',
        1439 => '42000', 1440 => 'XAE08', 1441 => '22008', 1451 => '23000', 1452 => '23000', 1453 => '42000', 1458 => '42000',
        1460 => '42000', 1461 => '42000', 1463 => '42000',
    );
    
    public function __construct(&$dsn, &$username, &$password, &$driver_options)
    {
        if(!extension_loaded('mysql'))
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
    }
    
    public function beginTransaction()
    {
        parent::beginTransaction();
        
        if(!mysql_unbuffered_query('START TRANSACTION', $this->link))
        {
            $this->set_driver_error(null, PDO::ERRMODE_EXCEPTION, 'beginTransaction');
        }
        
        return true;
    }
    
    public function commit()
    {
        parent::commit();
        
        if(!mysql_unbuffered_query('COMMIT', $this->link))
        {
            $this->set_driver_error(null, PDO::ERRMODE_EXCEPTION, 'commit');
        }
        
        $this->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);
        return true;
    }
    
    public function exec(&$statement)
    {
        if($result = mysql_unbuffered_query($statement, $this->link))
        {
            if(is_resource($result))
            {
                mysql_free_result($result);
                return 0;
            }
            
            return mysql_affected_rows($this->link);
        }
        
        return false;
    }
    
    public function getAttribute($attribute, &$source = null, $func = 'PDO::getAttribute', &$last_error = null)
    {
        if($source == null) $source =& $this->driver_options;
        
        switch($attribute)
        {
            case PDO::ATTR_AUTOCOMMIT:
                $result = mysql_unbuffered_query('SELECT @@AUTOCOMMIT', $this->link);
                
                if(!$result)
                {
                    $this->set_driver_error(null, PDO::ERRMODE_EXCEPTION, $func);
                }
                
                $row = mysql_fetch_row($result);
                mysql_free_result($result);
                
                return intval($row[0]);
            break;
            
            case PDO::ATTR_TIMEOUT:
                return intval(ini_get('mysql.connect_timeout'));
            break;
            
            case PDO::ATTR_CLIENT_VERSION:
                return mysql_get_client_info();
            break;
            
            case PDO::ATTR_CONNECTION_STATUS:
                return mysql_get_host_info($this->link);
            break;
            
            case PDO::ATTR_SERVER_INFO:
                return mysql_stat($this->link);
            break;
            
            case PDO::ATTR_SERVER_VERSION:
                return mysql_get_server_info($this->link);
            break;
            
            default:
                return parent::getAttribute($attribute, $source, $func, $last_error);
            break;
        }
    }
    
    public function lastInsertId($name = '')
    {
        return mysql_insert_id($this->link);
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
                return is_null($param) ? 'NULL' : '\'' . mysql_real_escape_string($param, $this->link) . '\'';
            break;
        }
    }
    
    public function rollBack()
    {
        parent::rollback();
        
        if(!mysql_unbuffered_query('ROLLBACK', $this->link))
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
                if(!mysql_unbuffered_query('SET AUTOCOMMIT = ' . $value, $this->link))
                {
                    $this->set_driver_error(null, PDO::ERRMODE_EXCEPTION, $func);
                }
                
                return true;
            break;
            
            case PDO::ATTR_TIMEOUT:
                $value = intval($value);
                if($value > 1 && @ini_set('mysql.connect_timeout', $value))
                {
                    return true;
                }
            break;
            
            case phppdo_base::MYSQL_ATTR_LOCAL_INFILE:
                $value = $value ? true : false;
                $source[phppdo_base::MYSQL_ATTR_LOCAL_INFILE] = $value;
                
                if($value && !($this->client_flags & 128))
                {
                    $this->client_flags |= 128;
                }
                else if(!$value && ($this->client_flags & 128))
                {
                    $this->client_flags &= ~128;
                }
                
                return true;
            break;
            
            case phppdo_base::MYSQL_ATTR_INIT_COMMAND:
                if($value)
                {
                    $source[phppdo_base::MYSQL_ATTR_INIT_COMMAND] = $value;
                    return true;
                }
            break;
            
            /*case phppdo_base::MYSQL_ATTR_READ_DEFAULT_FILE:
                
            break;
            
            case phppdo_base::MYSQL_ATTR_READ_DEFAULT_GROUP:
                
            break;
            
            case phppdo_base::MYSQL_ATTR_MAX_BUFFER_SIZE:
                
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
        $errno = mysql_errno($this->link);
        if($state === null) $state = $this->get_sql_state($errno);
        
        $this->set_error($errno, mysql_error($this->link), $state, $mode, $func);
    }
    
    public function get_sql_state($id)
    {
        if(isset($this->sql_states[$id]))
        {
            return $this->sql_states[$id];
        }
        
        return 'HY000';
    }
    
    protected function connect(&$username, &$password, &$driver_options)
    {
        $this->set_attributes(array
        (
            PDO::ATTR_TIMEOUT,
            phppdo_base::MYSQL_ATTR_LOCAL_INFILE,
            phppdo_base::MYSQL_ATTR_INIT_COMMAND,
            /*phppdo_base::MYSQL_ATTR_READ_DEFAULT_FILE,
            phppdo_base::MYSQL_ATTR_READ_DEFAULT_GROUP*/
        ), $driver_options);
        
        $host       = isset($this->dsn['host'])         ? $this->dsn['host']                : 'localhost';
        $dbname     = isset($this->dsn['dbname'])       ? $this->dsn['dbname']              : '';
        $port       = isset($this->dsn['port'])         ? intval($this->dsn['port'])        : 0;
        $socket     = isset($this->dsn['unix_socket'])  ? intval($this->dsn['unix_socket']) : '';
        
        if($socket)
        {
            $host .= ':' . $socket;
        }
        else if($port)
        {
            $host .= ':' . $port;
        }
        
        if(isset($driver_options[PDO::ATTR_PERSISTENT]) && $driver_options[PDO::ATTR_PERSISTENT])
        {
            $this->link = @mysql_pconnect($host, $username, $password, $this->client_flags);
        }
        else
        {
            $this->link = @mysql_connect($host, $username, $password, true, $this->client_flags);
        }
        
        if(!$this->link)
        {
            $errno = mysql_errno();
            $state = $this->get_sql_state($errno);
            
            $this->set_error($errno, mysql_error(), $state, PDO::ERRMODE_EXCEPTION, '__construct');
        }
        
        if($dbname)
        {
            if(!@mysql_select_db($dbname, $this->link))
            {
                $this->set_driver_error(null, PDO::ERRMODE_EXCEPTION, '__construct');
            }
        }
        
        if(isset($this->dsn['charset']))
        {
            if(!mysql_set_charset($this->dsn['charset'], $this->link))
            {
                $this->set_driver_error(null, PDO::ERRMODE_EXCEPTION, '__construct');
            }
        }
        
        if($this->driver_options[phppdo_base::MYSQL_ATTR_INIT_COMMAND])
        {
            if(!mysql_unbuffered_query($this->driver_options[phppdo_base::MYSQL_ATTR_INIT_COMMAND], $this->link))
            {
                $this->set_driver_error(null, PDO::ERRMODE_EXCEPTION, '__construct');
            }
        }
    }
    
    protected function disconnect()
    {
        mysql_close($this->link);
    }
}

?>