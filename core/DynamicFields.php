<?php
/**
 * Megabre StokMaster Pro
 * Dynamic Fields Class
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

class DynamicFields {
    private $db;
    private $field_types = [
        'text' => 'Metin',
        'number' => 'Sayı',
        'select' => 'Seçim',
        'textarea' => 'Metin Alanı',
        'date' => 'Tarih'
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Get field types
     */
    public function getFieldTypes() {
        return $this->field_types;
    }
    
    /**
     * Get field type label
     */
    public function getFieldTypeLabel($type) {
        return isset($this->field_types[$type]) ? $this->field_types[$type] : $type;
    }
    
    /**
     * Create category field
     */
    public function createCategoryField($category_id, $field_name, $field_type, $field_options = null) {
        $this->db->query("INSERT INTO category_fields (category_id, field_name, field_type, field_options) 
                         VALUES (:category_id, :field_name, :field_type, :field_options)");
        $this->db->bind(':category_id', $category_id);
        $this->db->bind(':field_name', $field_name);
        $this->db->bind(':field_type', $field_type);
        $this->db->bind(':field_options', $field_options);
        
        return $this->db->execute() ? $this->db->lastInsertId() : false;
    }
    
    /**
     * Update category field
     */
    public function updateCategoryField($field_id, $field_name, $field_type, $field_options = null) {
        $this->db->query("UPDATE category_fields SET field_name = :field_name, field_type = :field_type, field_options = :field_options 
                         WHERE id = :id");
        $this->db->bind(':field_name', $field_name);
        $this->db->bind(':field_type', $field_type);
        $this->db->bind(':field_options', $field_options);
        $this->db->bind(':id', $field_id);
        
        return $this->db->execute();
    }
    
    /**
     * Delete category field
     */
    public function deleteCategoryField($field_id) {
        $this->db->query("DELETE FROM category_fields WHERE id = :id");
        $this->db->bind(':id', $field_id);
        
        return $this->db->execute();
    }
    
    /**
     * Get category fields
     */
    public function getCategoryFields($category_id) {
        try {
            // Debug log
            error_log("Getting fields for category ID: " . $category_id);
            
            $this->db->query("SELECT * FROM category_fields WHERE category_id = :category_id ORDER BY id ASC");
            $this->db->bind(':category_id', $category_id);
            $fields = $this->db->resultSet();
            
            // Debug log
            error_log("Fields query result: " . print_r($fields, true));
            
            return $fields;
        } catch (Exception $e) {
            error_log("Error in getCategoryFields: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get category field by ID
     */
    public function getCategoryFieldById($field_id) {
        $this->db->query("SELECT * FROM category_fields WHERE id = :id");
        $this->db->bind(':id', $field_id);
        
        return $this->db->single();
    }
    
    /**
     * Count category fields
     */
    public function countCategoryFields($category_id) {
        $this->db->query("SELECT COUNT(*) as count FROM category_fields WHERE category_id = :category_id");
        $this->db->bind(':category_id', $category_id);
        $result = $this->db->single();
        
        return $result ? $result['count'] : 0;
    }
    
    /**
     * Create product field
     */
    public function createProductField($product_id, $field_name, $field_type, $field_value = null) {
        try {
            // Try to insert into product_fields table (if it has product_id column)
            // Otherwise, try product_field_values table
            try {
                $this->db->query("INSERT INTO product_field_values (product_id, field_name, field_type, field_value) 
                                 VALUES (:product_id, :field_name, :field_type, :field_value)");
                $this->db->bind(':product_id', $product_id);
                $this->db->bind(':field_name', $field_name);
                $this->db->bind(':field_type', $field_type);
                $this->db->bind(':field_value', $field_value);
                
                if ($this->db->execute()) {
                    return $this->db->lastInsertId();
                }
            } catch (PDOException $e) {
                // product_field_values doesn't exist, try product_fields_backup
                try {
                    $this->db->query("INSERT INTO product_fields_backup (product_id, field_name, field_type, field_value) 
                                     VALUES (:product_id, :field_name, :field_type, :field_value)");
                    $this->db->bind(':product_id', $product_id);
                    $this->db->bind(':field_name', $field_name);
                    $this->db->bind(':field_type', $field_type);
                    $this->db->bind(':field_value', $field_value);
                    
                    if ($this->db->execute()) {
                        return $this->db->lastInsertId();
                    }
                } catch (PDOException $e2) {
                    // Table doesn't exist or column doesn't exist, skip silently
                    // This is not a critical error, product can still be created
                    error_log("Product field table not found or column mismatch: " . $e2->getMessage());
                    return false;
                }
            }
            
            return false;
        } catch (Exception $e) {
            // Ignore errors - product fields are optional
            error_log("Error creating product field: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update product field
     */
    public function updateProductField($field_id, $field_value) {
        $this->db->query("UPDATE product_fields SET field_value = :field_value WHERE id = :id");
        $this->db->bind(':field_value', $field_value);
        $this->db->bind(':id', $field_id);
        
        return $this->db->execute();
    }
    
    /**
     * Delete product field
     */
    public function deleteProductField($field_id) {
        $this->db->query("DELETE FROM product_fields WHERE id = :id");
        $this->db->bind(':id', $field_id);
        
        return $this->db->execute();
    }
    
    /**
     * Delete all product fields for a product
     */
    public function deleteProductFields($product_id) {
        try {
            // Try product_field_values first
            try {
                $this->db->query("DELETE FROM product_field_values WHERE product_id = :product_id");
                $this->db->bind(':product_id', $product_id);
                return $this->db->execute();
            } catch (PDOException $e) {
                // Try product_fields_backup
                try {
                    $this->db->query("DELETE FROM product_fields_backup WHERE product_id = :product_id");
                    $this->db->bind(':product_id', $product_id);
                    return $this->db->execute();
                } catch (PDOException $e2) {
                    // Table doesn't exist, skip
                    return true;
                }
            }
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get product fields
     */
    public function getProductFields($product_id) {
        try {
            // Try product_field_values first
            try {
                $this->db->query("SELECT * FROM product_field_values WHERE product_id = :product_id ORDER BY id ASC");
                $this->db->bind(':product_id', $product_id);
                return $this->db->resultSet();
            } catch (PDOException $e) {
                // Try product_fields_backup
                try {
                    $this->db->query("SELECT * FROM product_fields_backup WHERE product_id = :product_id ORDER BY id ASC");
                    $this->db->bind(':product_id', $product_id);
                    return $this->db->resultSet();
                } catch (PDOException $e2) {
                    // Table doesn't exist, return empty array
                    return [];
                }
            }
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Get product field by ID
     */
    public function getProductFieldById($field_id) {
        $this->db->query("SELECT * FROM product_fields WHERE id = :id");
        $this->db->bind(':id', $field_id);
        
        return $this->db->single();
    }
    
    /**
     * Create customer field
     */
    public function createCustomerField($customer_id, $field_name, $field_type, $field_value = null) {
        $this->db->query("INSERT INTO customer_fields (customer_id, field_name, field_type, field_value) 
                         VALUES (:customer_id, :field_name, :field_type, :field_value)");
        $this->db->bind(':customer_id', $customer_id);
        $this->db->bind(':field_name', $field_name);
        $this->db->bind(':field_type', $field_type);
        $this->db->bind(':field_value', $field_value);
        
        return $this->db->execute() ? $this->db->lastInsertId() : false;
    }
    
    /**
     * Update customer field
     */
    public function updateCustomerField($field_id, $field_value) {
        $this->db->query("UPDATE customer_fields SET field_value = :field_value WHERE id = :id");
        $this->db->bind(':field_value', $field_value);
        $this->db->bind(':id', $field_id);
        
        return $this->db->execute();
    }
    
    /**
     * Delete customer field
     */
    public function deleteCustomerField($field_id) {
        $this->db->query("DELETE FROM customer_fields WHERE id = :id");
        $this->db->bind(':id', $field_id);
        
        return $this->db->execute();
    }
    
    /**
     * Delete all customer fields for a customer
     */
    public function deleteCustomerFields($customer_id) {
        $this->db->query("DELETE FROM customer_fields WHERE customer_id = :customer_id");
        $this->db->bind(':customer_id', $customer_id);
        
        return $this->db->execute();
    }
    
    /**
     * Get customer fields
     */
    public function getCustomerFields($customer_id) {
        $this->db->query("SELECT * FROM customer_fields WHERE customer_id = :customer_id ORDER BY id ASC");
        $this->db->bind(':customer_id', $customer_id);
        
        return $this->db->resultSet();
    }
    
    /**
     * Get customer field by ID
     */
    public function getCustomerFieldById($field_id) {
        $this->db->query("SELECT * FROM customer_fields WHERE id = :id");
        $this->db->bind(':id', $field_id);
        
        return $this->db->single();
    }
    
    /**
     * Create stock field
     */
    public function createStockField($stock_id, $field_name, $field_type, $field_value = null) {
        $this->db->query("INSERT INTO stock_fields (stock_id, field_name, field_type, field_value) 
                         VALUES (:stock_id, :field_name, :field_type, :field_value)");
        $this->db->bind(':stock_id', $stock_id);
        $this->db->bind(':field_name', $field_name);
        $this->db->bind(':field_type', $field_type);
        $this->db->bind(':field_value', $field_value);
        
        return $this->db->execute() ? $this->db->lastInsertId() : false;
    }
    
    /**
     * Update stock field
     */
    public function updateStockField($field_id, $field_value) {
        $this->db->query("UPDATE stock_fields SET field_value = :field_value WHERE id = :id");
        $this->db->bind(':field_value', $field_value);
        $this->db->bind(':id', $field_id);
        
        return $this->db->execute();
    }
    
    /**
     * Delete stock field
     */
    public function deleteStockField($field_id) {
        $this->db->query("DELETE FROM stock_fields WHERE id = :id");
        $this->db->bind(':id', $field_id);
        
        return $this->db->execute();
    }
    
    /**
     * Delete all stock fields for a stock
     */
    public function deleteStockFields($stock_id) {
        $this->db->query("DELETE FROM stock_fields WHERE stock_id = :stock_id");
        $this->db->bind(':stock_id', $stock_id);
        
        return $this->db->execute();
    }
    
    /**
     * Get stock fields
     */
    public function getStockFields($stock_id) {
        $this->db->query("SELECT * FROM stock_fields WHERE stock_id = :stock_id ORDER BY id ASC");
        $this->db->bind(':stock_id', $stock_id);
        
        return $this->db->resultSet();
    }
    
    /**
     * Get stock field by ID
     */
    public function getStockFieldById($field_id) {
        $this->db->query("SELECT * FROM stock_fields WHERE id = :id");
        $this->db->bind(':id', $field_id);
        
        return $this->db->single();
    }
    
    /**
     * Generate field HTML
     */
    public function generateFieldHtml($field, $value = null, $id_prefix = '', $required = false) {
        $field_id = $id_prefix . 'field_' . $field['id'];
        $field_name = $id_prefix . 'field[' . $field['id'] . ']';
        $field_value = $value !== null ? $value : (isset($field['field_value']) ? $field['field_value'] : '');
        $required_attr = $required ? 'required' : '';
        $html = '';
        
        switch ($field['field_type']) {
            case 'text':
                $html = '<div class="mb-3">
                            <label for="' . $field_id . '" class="form-label">' . e($field['field_name']) . '</label>
                            <input type="text" class="form-control" id="' . $field_id . '" name="' . $field_name . '" value="' . e($field_value) . '" ' . $required_attr . '>
                        </div>';
                break;
                
            case 'number':
                $html = '<div class="mb-3">
                            <label for="' . $field_id . '" class="form-label">' . e($field['field_name']) . '</label>
                            <input type="number" class="form-control" id="' . $field_id . '" name="' . $field_name . '" value="' . e($field_value) . '" step="any" ' . $required_attr . '>
                        </div>';
                break;
                
            case 'select':
                $options = '';
                $field_options = isset($field['field_options']) ? json_decode($field['field_options'], true) : [];
                
                if (!empty($field_options)) {
                    foreach ($field_options as $option) {
                        $selected = $field_value == $option ? 'selected' : '';
                        $options .= '<option value="' . e($option) . '" ' . $selected . '>' . e($option) . '</option>';
                    }
                }
                
                $html = '<div class="mb-3">
                            <label for="' . $field_id . '" class="form-label">' . e($field['field_name']) . '</label>
                            <select class="form-select" id="' . $field_id . '" name="' . $field_name . '" ' . $required_attr . '>
                                <option value="">Seçiniz</option>
                                ' . $options . '
                            </select>
                        </div>';
                break;
                
            case 'textarea':
                $html = '<div class="mb-3">
                            <label for="' . $field_id . '" class="form-label">' . e($field['field_name']) . '</label>
                            <textarea class="form-control" id="' . $field_id . '" name="' . $field_name . '" rows="3" ' . $required_attr . '>' . e($field_value) . '</textarea>
                        </div>';
                break;
                
            case 'date':
                $html = '<div class="mb-3">
                            <label for="' . $field_id . '" class="form-label">' . e($field['field_name']) . '</label>
                            <input type="date" class="form-control" id="' . $field_id . '" name="' . $field_name . '" value="' . e($field_value) . '" ' . $required_attr . '>
                        </div>';
                break;
        }
        
        return $html;
    }
    
    /**
     * Get field value for display
     */
    public function getDisplayValue($field) {
        $value = isset($field['field_value']) ? $field['field_value'] : '';
        
        switch ($field['field_type']) {
            case 'date':
                return !empty($value) ? formatDate($value) : '';
                
            case 'number':
                return !empty($value) ? formatPrice($value, 2) : '0';
                
            default:
                return $value;
        }
    }
    
    /**
     * Copy category fields to another category
     */
    public function copyCategoryFields($source_category_id, $target_category_id) {
        $fields = $this->getCategoryFields($source_category_id);
        
        if (empty($fields)) {
            return false;
        }
        
        $this->db->beginTransaction();
        
        try {
            foreach ($fields as $field) {
                $this->db->query("INSERT INTO category_fields (category_id, field_name, field_type, field_options) 
                                 VALUES (:category_id, :field_name, :field_type, :field_options)");
                $this->db->bind(':category_id', $target_category_id);
                $this->db->bind(':field_name', $field['field_name']);
                $this->db->bind(':field_type', $field['field_type']);
                $this->db->bind(':field_options', $field['field_options']);
                $this->db->execute();
            }
            
            $this->db->endTransaction();
            return true;
        } catch (PDOException $e) {
            $this->db->cancelTransaction();
            return false;
        }
    }
    
    /**
     * Parse field options from string
     */
    public function parseFieldOptions($options_string) {
        if (empty($options_string)) {
            return json_encode([]);
        }
        
        $options = array_map('trim', explode(',', $options_string));
        return json_encode($options);
    }
    
    /**
     * Format field options for display
     */
    public function formatFieldOptions($options_json) {
        if (empty($options_json)) {
            return '';
        }
        
        $options = json_decode($options_json, true);
        
        if (!is_array($options)) {
            return '';
        }
        
        return implode(', ', $options);
    }
}