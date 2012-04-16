<?php
/**
 * Project:     PHPPDO
 * File:        mysqli_statement.php
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

class phppdo_mysqli_statement extends phppdo_base_statement
{
    private $bind_params_changed    = false;
    private $fetch_out              = null;
    private $fetch_fields           = array();
    private $lobs                   = array();
    private $field_types            = array
    (
        MYSQLI_TYPE_DECIMAL => 'DECIMAL', MYSQLI_TYPE_NEWDECIMAL => 'NEWDECIMAL', MYSQLI_TYPE_BIT => 'BIT', MYSQLI_TYPE_TINY => 'TINY', MYSQLI_TYPE_SHORT => 'SHORT', MYSQLI_TYPE_LONG => 'LONG', MYSQLI_TYPE_FLOAT => 'FLOAT',
        MYSQLI_TYPE_DOUBLE => 'DOUBLE', MYSQLI_TYPE_NULL => 'NULL', MYSQLI_TYPE_TIMESTAMP => 'TIMESTAMP', MYSQLI_TYPE_LONGLONG => 'LONGLONG', MYSQLI_TYPE_INT24 => 'INT24', MYSQLI_TYPE_DATE => 'DATE', MYSQLI_TYPE_TIME => 'TIME',
        MYSQLI_TYPE_DATETIME => 'DATETIME', MYSQLI_TYPE_YEAR => 'YEAR', MYSQLI_TYPE_NEWDATE => 'NEWDATE', MYSQLI_TYPE_ENUM => 'ENUM', MYSQLI_TYPE_SET => 'SET', MYSQLI_TYPE_TINY_BLOB => 'TINY_BLOB',
        MYSQLI_TYPE_MEDIUM_BLOB => 'MEDIUM_BLOB', MYSQLI_TYPE_LONG_BLOB => 'LONG_BLOB', MYSQLI_TYPE_BLOB => 'BLOB', MYSQLI_TYPE_VAR_STRING => 'VAR_STRING', MYSQLI_TYPE_STRING => 'STRING', MYSQLI_TYPE_GEOMETRY => 'GEOMETRY',
    );
    
    
    public function bindParam($parameter, &$variable, $data_type = -1, $length = 0, $driver_options = null)
    {
        if(parent::bindParam($parameter, $variable, $data_type, $length, $driver_options))
        {
            $this->bind_params_changed = true;
            return true;
        }
        
        return false;
    }
    
    public function closeCursor()
    {
        if($this->_result)
        {
            $this->fetch_fields = array();
            $this->fetch_out    = null;
            
            mysqli_stmt_free_result($this->_result);
        }
    }
    
    public function columnCount()
    {
        if($this->_result)
        {
            return mysqli_stmt_field_count($this->_result);
        }
        
        return 0;
    }
    
    public function getColumnMeta($column)
    {
        if($column >= count($this->fetch_fields)) return false;
        
        $info   = $this->fetch_fields[$column];
        $result = array();
        $flags  = array();
        
        if($info->def)
        {
            $result['mysql:def'] = $info->def;
        }
        
        if($info->flags & MYSQLI_NOT_NULL_FLAG)
        {
            $flags[] = 'not_null';
        }
        
        if($info->flags & MYSQLI_PRI_KEY_FLAG)
        {
            $flags[] = 'primary_key';
        }
        
        if($info->flags & MYSQLI_MULTIPLE_KEY_FLAG)
        {
            $flags[] = 'multiple_key';
        }
        
        if($info->flags & MYSQLI_UNIQUE_KEY_FLAG)
        {
            $flags[] = 'unique_key';
        }
        
        if($info->flags & MYSQLI_BLOB_FLAG)
        {
            $flags[] = 'blob';
        }
        
        $result['native_type']  = $this->field_types[$info->type];
        $result['flags']        = $flags;
        $result['table']        = $info->table;
        $result['name']         = $info->name;
        $result['len']          = $info->length;
        $result['precision']    = $info->decimals;
        
        switch($info->type)
        {
            // seems like pdo_mysql treats everything as a string
            /*
            case MYSQLI_TYPE_DECIMAL:
            case MYSQLI_TYPE_NEWDECIMAL:
            case MYSQLI_TYPE_TINY:
            case MYSQLI_TYPE_SHORT:
            case MYSQLI_TYPE_LONG:
            case MYSQLI_TYPE_FLOAT:
            case MYSQLI_TYPE_DOUBLE:
            case MYSQLI_TYPE_LONGLONG:
            case MYSQLI_TYPE_INT24;
                $pdo_type = PDO::PARAM_INT;
            break;
            
            case MYSQLI_TYPE_TINY_BLOB:
            case MYSQLI_TYPE_MEDIUM_BLOB:
            case MYSQLI_TYPE_LONG_BLOB:
            case MYSQLI_TYPE_BLOB:
                $pdo_type = PDO::PARAM_LOB;
            break;
            
            case MYSQLI_TYPE_NULL:
                $pdo_type = PDO::PARAM_NULL;
            break;
            */
            
            default:
                $pdo_type = PDO::PARAM_STR;
            break;
        }
        
        $result['pdo_type'] = $pdo_type;
        
        return $result;
    }
    
    public function rowCount()
    {
        return mysqli_stmt_affected_rows($this->_result);
    }
    
    protected function _execute()
    {
        if($this->bind_params_changed)
        {
            if(!$this->bind_params())
            {
                $this->_set_stmt_error(null, PDO::ERRMODE_SILENT, 'execute');
                return false;
            }
            
            $this->bind_params_changed = false;
        }
        
        if(!$this->send_lobs() || !mysqli_stmt_execute($this->_result))
        {
            $this->_set_stmt_error(null, PDO::ERRMODE_SILENT, 'execute');
            return false;
        }
        
        // store the result
        if($data = mysqli_stmt_result_metadata($this->_result))
        {
            if(!$this->bind_result($data))
            {
                $this->_set_stmt_error(null, PDO::ERRMODE_SILENT, 'execute');
                return false;
            }
            
            if($this->getAttribute(phppdo_base::MYSQL_ATTR_USE_BUFFERED_QUERY))
            {
                if(!mysqli_stmt_store_result($this->_result))
                {
                    $this->_set_stmt_error(null, PDO::ERRMODE_SILENT, 'execute');
                    return false;
                }
            }
        }
        
        return true;
    }
    
    protected function _fetch_row()
    {
        if(!mysqli_stmt_fetch($this->_result)) return false;
        
        $row = array();
        foreach($this->fetch_out as $k => &$v)
        {
            $row[$k] = $v;
            $v = null;
        }
        
        return $row;
    }
    
    protected function _field_name($field)
    {
        return $this->fetch_fields[$field]->name;
    }
    
    protected function _table_name($field)
    {
        return $this->fetch_fields[$field]->table;
    }
    
    protected function _set_stmt_error($state = null, $mode = PDO::ERRMODE_SILENT, $func = '')
    {
        if($state === null) $state = mysqli_stmt_sqlstate($this->_result);
        $this->_set_error(mysqli_stmt_errno($this->_result), mysqli_stmt_error($this->_result), $state, $mode, $func);
    }
    
    private function bind_params()
    {
        $bound_params   =& $this->_bound_params;
        $cnt            = count($bound_params) + 1;
        $this->lobs     = array();
        
        if($cnt > 1)
        {
            $types  = '';
            $params = array($this->_result, &$types);
            
            for($x = 1; $x < $cnt; $x++)
            {
                $value =& $bound_params[$x][0];
                $data_type = $bound_params[$x][1];
                
                switch($bound_params[$x][1])
                {
                    case PDO::PARAM_INT:
                        if(is_float($value))
                        {
                            $types .= 'd';
                            $value = (float)$value;
                        }
                        else
                        {
                            $types .= 'i';
                            $value = (int)$value;
                        }
                        $params[] =& $value;
                    break;
                    
                    case PDO::PARAM_STR:
                        $types .= 's';
                        $params[] =& $value;
                    break;
                    
                    case PDO::PARAM_LOB:
                        $types .= 'b';
                        $this->lobs[] =& $value;
                        $params[] = null;
                    break;
                    
                    default:
                        if(is_int($value))
                        {
                            $types .= 'i';
                        }
                        else if(is_float($value))
                        {
                            $types .= 'd';
                        }
                        else
                        {
                            $types .= 's';
                        }
                        
                        $params[] =& $value;
                    break;
                }
            }
            
            return call_user_func_array('mysqli_stmt_bind_param', $params);
        }
        
        return true;
    }
    
    private function bind_result($data)
    {
        $fetch_out      =& $this->fetch_out;
        $fetch_fields   =& $this->fetch_fields;
        
        $cnt        = 0;
        $fetch_out  = array();
        $bound      = array($this->_result);
        
        while($field = mysqli_fetch_field($data))
        {
            $fetch_fields[$cnt]     = $field;
            $bound[]                =& $fetch_out[$cnt];
            $cnt++;
        }
        
        return call_user_func_array('mysqli_stmt_bind_result', $bound);
    }
    
    private function send_lobs()
    {
        foreach($this->lobs as $k => &$v)
        {
            if(is_resource($v))
            {
                while(!feof($v))
                {
                    if(!mysqli_stmt_send_long_data($this->_result, $k, fread($v, 8192)))
                    {
                        return false;
                    }
                }
            }
            else
            {
                if(!mysqli_stmt_send_long_data($this->_result, $k, $v))
                {
                    return false;
                }
            }
        }
        
        return true;
    }
}
?>