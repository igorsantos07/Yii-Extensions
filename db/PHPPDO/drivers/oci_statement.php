<?php
/**
 * Project:     PHPPDO
 * File:        oci_statement.php
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

class phppdo_oci_statement extends phppdo_base_statement
{
    private $lobs = array();
    private $tmp_lobs = array();
    private $has_bound_lobs = false;
    
    public function __destruct()
    {
        // remove temp lobs
        foreach($this->tmp_lobs as $file => $fp)
        {
            @fclose($fp);
            @unlink($file);
        }
    }
    
    public function bindColumn($column, &$param, $type = 0, $maxlen = 0, $driver_options = null)
    {
        if(parent::bindColumn($column, $param, $type, $maxlen, $driver_options))
        {
            if($type == PDO::PARAM_LOB)
            {
                $this->has_bound_lobs = true;
            }
            
            return true;
        }
        
        return false;
    }
    
    public function bindParam($parameter, &$variable, $data_type = -1, $length = 0, $driver_options = null)
    {
        if($parameter[0] != ':' && !is_numeric($parameter))
        {
            $parameter = ':' . $parameter;
        }
        
        if(!$length)
        {
            $length = -1;
        }
        
        $params_info =& $this->_params_info;
        
        switch($data_type)
        {
            case PDO::PARAM_INT:
                $type = SQLT_INT;
            break;
            
            case PDO::PARAM_LOB:
                if(isset($params_info[$parameter]))
                {
                    $p = $params_info[$parameter];
                    $lob = oci_new_descriptor($this->_link, OCI_DTYPE_LOB);
                    
                    if(oci_bind_by_name($this->_result, $p, $lob, -1, SQLT_BLOB))
                    {
                        $this->_bound_params[$p] = 1;
                        $this->lobs[$p] = array(&$lob, &$variable);
                        
                        return true;
                    }
                    
                    oci_free_descriptor($lob);
                }
                
                return false;
                
            break;
            
            default:
                $type = SQLT_CHR;
            break;
        }
        
        if(
            isset($params_info[$parameter]) &&
            oci_bind_by_name($this->_result, $params_info[$parameter], $variable, $length, $type)
        ) {
            $this->_bound_params[$params_info[$parameter]] = 1;
            return true;
        }
        
        return false;
    }
    
    public function closeCursor()
    {
        if($this->_result)
        {
            oci_cancel($this->_result);
        }
    }
    
    public function columnCount()
    {
        if($this->_result)
        {
            return oci_num_fields($this->_result);
        }
        
        return 0;
    }
    
    public function getColumnMeta($column)
    {
        if($column >= $this->columnCount()) return false;
        
        $column++;
        $result = array();
        
        $result['native_type']  = oci_field_type($this->_result, $column);
        if(oci_field_is_null($this->_result, $column)) $result['flags'] = 'is_null';
        $result['name']         = oci_field_name($this->_result, $column);
        $result['len']          = oci_field_size($this->_result, $column);
        $result['precision']    = oci_field_precision($this->_result, $column) . '.' . oci_field_scale($this->_result, $column);
        
        $result['pdo_type']     = PDO::PARAM_STR;
        
        return $result;
    }
    
    public function rowCount()
    {
        return oci_num_rows($this->_result);
    }
    
    protected function _execute()
    {
        if(!@oci_execute($this->_result, ($this->_driver->autocommit ? OCI_COMMIT_ON_SUCCESS : OCI_DEFAULT)))
        {
            $this->_set_stmt_error(null, PDO::ERRMODE_SILENT, 'execute');
            return false;
        }
        
        foreach($this->lobs as $k => &$v)
        {
            $lob =& $v[0];
            $data =& $v[1];
            
            if(is_resource($data))
            {
                while(!feof($data))
                {
                    $lob->write(fread($data, 8192));
                }
            }
            else
            {
                $lob->write($data);
            }
        }
        
        if(0 < ($rows = $this->getAttribute(PDO::ATTR_PREFETCH)))
        {
            oci_set_prefetch($this->_result, $rows);
        }
        
        return true;
    }
    
    protected function _fetch_row()
    {
        // XXX: There seems to be a bug with oci_fetch_array($this->_result, (OCI_NUM + OCI_RETURN_LOBS))
        /*if($this->has_bound_lobs)
        {
            return @oci_fetch_row($this->_result);
        }
        
        return @oci_fetch_array($this->_result, (OCI_NUM + OCI_RETURN_LOBS));*/
        
        $row = @oci_fetch_row($this->_result);
        if(!$row) return false;
        
        if(!$this->has_bound_lobs)
        {
            foreach($row as &$v)
            {
                if(is_object($v))
                {
                    $v = $v->load();
                }
            }
        }
        
        return $row;
    }
    
    protected function _fetch_lob(&$p, &$col)
    {
        $tmp_file = tempnam(sys_get_temp_dir(), 'phppdo_');
        if(!$tmp_file) return false;
        
        if(is_object($col))
        {
            $col->export($tmp_file);
        }
        else
        {
            return parent::_fetch_lob($p, $col);
        }
        
        $p = fopen($tmp_file, 'rb');
        if($p) $this->tmp_lobs[$tmp_file] = $p;
    }
    
    protected function _field_name($field)
    {
        return oci_field_name($this->_result, ($field + 1));
    }
    
    protected function _table_name($field)
    {
        return '';
    }
    
    protected function _set_stmt_error($state = null, $mode = PDO::ERRMODE_SILENT, $func = '')
    {
        $error = oci_error($this->_result);
        
        if($state === null) $state = 'HY000';
        $this->_set_error($error['code'], 'OCIStmtExecute: ' . $error['message'], $state, $mode, $func);
    }
}
?>