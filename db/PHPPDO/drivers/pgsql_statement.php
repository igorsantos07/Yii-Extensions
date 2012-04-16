<?php
/**
 * Project:     PHPPDO
 * File:        pgsql_statement.php
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

class phppdo_pgsql_statement extends phppdo_base_statement
{
    private $tmp_lobs = array();
    
    public function __destruct()
    {
        // remove temp lobs
        foreach($this->tmp_lobs as $file => $fp)
        {
            @fclose($fp);
            @unlink($file);
        }
        
        // dealocate the prepared statement
        if($this->_result_name)
        {
            pg_query($this->_link, 'DEALLOCATE "' . $this->_result_name . '"');
        }
        
    }
    
    public function closeCursor()
    {
        if($this->_result)
        {
            pg_free_result($this->_result);
        }
    }
    
    public function columnCount()
    {
        if($this->_result)
        {
            return pg_num_fields($this->_result);
        }
        
        return 0;
    }
    
    public function getColumnMeta($column)
    {
        if($column >= $this->columnCount()) return false;
        
        $result = array();
        
        
        $result['native_type']  = pg_field_type($this->_result, $column);
        $result['table']        = pg_field_table($this->_result, $column);
        $result['name']         = pg_field_name($this->_result, $column);
        $result['len']          = pg_field_prtlen($this->_result, $column);
        
        $result['pdo_type']     = PDO::PARAM_STR;
        
        return $result;
    }
    
    public function rowCount()
    {
        return pg_affected_rows($this->_result);
    }
    
    protected function _execute()
    {
        $los =& $this->_driver->los;
        $params = array();
        
        if($this->_bound_params)
        {
            foreach($this->_bound_params as $k => &$p)
            {
                $param =& $p[0];
                switch($p[1])
                {
                    case PDO::PARAM_LOB:
                        if(is_resource($param))
                        {
                            if(isset($los[$param]))
                            {
                                $pos = ftell($param);
                                rewind($param);
                                $lo_stream = $los[(int)$param][1];
                                
                                while(!feof($param))
                                {
                                    pg_lo_write($lo_stream, fread($param, 8192));
                                }
                                
                                pg_lo_close($lo_stream);
                                fseek($param, $pos);
                                $params[$k] = $los[(int)$param][0];
                            }
                            else
                            {
                                $buffer =& $params[$k];
                                while(!feof($param))
                                {
                                    $buffer .= fread($param, 8192);
                                }
                            }
                        }
                        else
                        {
                            $params[$k] =& $param;
                        }
                    break;
                    
                    case PDO::PARAM_BOOL:
                        $params[$k] = $param ? 'TRUE' : 'FALSE';
                    break;
                    
                    default:
                        $params[$k] =& $param;
                    break;
                }
            }
        }
        
        if(!$result = @pg_execute($this->_link, $this->_result_name, $params))
        {
            $this->_set_stmt_error(null, PDO::ERRMODE_SILENT, 'execute');
            return false;
        }
        
        $this->_result = $result;
        return true;
    }
    
    protected function _fetch_row()
    {
        return @pg_fetch_row($this->_result);
    }
    
    protected function _fetch_lob(&$p, &$col)
    {
        $tmp_file = tempnam(sys_get_temp_dir(), 'phppdo_');
        if(!$tmp_file) return false;
        
        if(!@pg_lo_export($this->_link, $col, $tmp_file))
        {
            // maybe this is a 'bytea'
            return parent::_fetch_lob($p, $col);
        }
        
        $p = fopen($tmp_file, 'rb');
        if($p) $this->tmp_lobs[$tmp_file] = $p;
    }
    
    protected function _field_name($field)
    {
        return pg_field_name($this->_result, $field);
    }
    
    protected function _table_name($field)
    {
        return pg_field_table($this->_result, $field);
    }
    
    protected function _set_stmt_error($state = null, $mode = PDO::ERRMODE_SILENT, $func = '')
    {
        if($state === null) $state = 'HY000';
        $this->_set_error(7,  pg_last_error($this->_link), $state, $mode, $func);
    }
}
?>