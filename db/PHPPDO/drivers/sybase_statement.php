<?php
/**
 * Project:     PHPPDO
 * File:        sybase_statement.php
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

class phppdo_sybase_statement extends phppdo_base_statement
{
    public function closeCursor()
    {
        if($this->_result)
        {
            sybase_free_result($this->_result);
            $this->_result = false;
        }
    }
    
    public function columnCount()
    {
        if($this->_result)
        {
            return sybase_num_fields($this->_result);
        }
        
        return 0;
    }
    
    public function rowCount()
    {
        return sybase_affected_rows($this->_link);
    }
    
    public function getColumnMeta($column)
    {
        if($column >= $this->columnCount()) return false;
        
        $info                   = sybase_fetch_field($this->_result, $column);
        $result                 = array();
        
        $result['native_type']  = $info->type;
        $result['name']         = $info->name;
        $result['len']          = $info->max_length;
        $result['pdo_type']     = PDO::PARAM_STR;
        
        return $result;
    }
    
    protected function _execute()
    {
        $query = $this->_build_query();
        if(!$query) return false;
        
        $this->_result = @sybase_query($query, $this->_link);
        
        if(!$this->_result)
        {
            $this->_set_stmt_error(null, PDO::ERRMODE_SILENT, 'execute');
            return false;
        }
        
        return true;
    }
    
    protected function _fetch_row()
    {
        return sybase_fetch_row($this->_result);
    }
    
    protected function _field_name($field)
    {
        return sybase_fetch_field($this->_result, $field)->name;
    }
    
    protected function _table_name($field)
    {
        return sybase_fetch_field($this->_result, $field)->column_source;
    }
    
    protected function _set_stmt_error($state = null, $mode = PDO::ERRMODE_SILENT, $func = '')
    {
        if($state === null) $state = 'HY000';
        $this->_set_error(-1, sybase_get_last_message(), $state, $mode, $func);
    }
}
?>