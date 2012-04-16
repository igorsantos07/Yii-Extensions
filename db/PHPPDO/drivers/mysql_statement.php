<?php
/**
 * Project:     PHPPDO
 * File:        mysql_statement.php
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

class phppdo_mysql_statement extends phppdo_base_statement
{
    public function closeCursor()
    {
        if($this->_result)
        {
            mysql_free_result($this->_result);
            $this->_result = false;
        }
    }
    
    public function columnCount()
    {
        if($this->_result)
        {
            return mysql_num_fields($this->_result);
        }
        
        return 0;
    }
    
    public function rowCount()
    {
        return mysql_affected_rows($this->_link);
    }
    
    public function getColumnMeta($column)
    {
        if($column >= $this->columnCount()) return false;
        
        $info   = mysql_fetch_field($this->_result, $column);
        $result = array();
        
        if($info->def)
        {
            $result['mysql:def'] = $info->def;
        }
        
        $result['native_type']  = $info->type;
        $result['flags']        = explode(' ', mysql_field_flags($this->_result, $column));
        $result['table']        = $info->table;
        $result['name']         = $info->name;
        $result['len']          = mysql_field_len($this->_result, $column);
        $result['precision']    = 0;
        
        switch($result['native_type'])
        {
            // seems like pdo_mysql treats everything as a string
            /*
            case 'int':
            case 'real':
                $pdo_type = PDO::PARAM_INT;
            break;
            
            case 'blob':
                $pdo_type = PDO::PARAM_LOB;
            break;
            
            case 'null':
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
    
    protected function _execute()
    {
        $query = $this->_build_query();
        if(!$query) return false;
        
        if($this->getAttribute(phppdo_base::MYSQL_ATTR_USE_BUFFERED_QUERY))
        {
            $this->_result = mysql_query($query, $this->_link);
        }
        else
        {
            $this->_result = mysql_unbuffered_query($query, $this->_link);
        }
        
        if(!$this->_result)
        {
            $this->_set_stmt_error(null, PDO::ERRMODE_SILENT, 'execute');
            return false;
        }
        
        return true;
    }
    
    protected function _fetch_row()
    {
        return mysql_fetch_row($this->_result);
    }
    
    protected function _field_name($field)
    {
        return mysql_field_name($this->_result, $field);
    }
    
    protected function _table_name($field)
    {
        return mysql_field_table($this->_result, $field);
    }
    
    protected function _set_stmt_error($state = null, $mode = PDO::ERRMODE_SILENT, $func = '')
    {
        $errno = mysql_errno($this->_link);
        if($state === null) $state = $this->_driver->get_sql_state($errno);
        
        $this->_set_error($errno, mysql_error($this->_link), $state, $mode, $func);
    }
    
}
?>