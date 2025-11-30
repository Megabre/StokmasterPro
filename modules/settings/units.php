// ... existing code ...
// Update stock_movements to use measurement_units
$db->query("ALTER TABLE stock_movements 
    DROP COLUMN unit,
    ADD COLUMN unit_id int(11) DEFAULT NULL AFTER quantity,
    ADD FOREIGN KEY (unit_id) REFERENCES measurement_units(id)");

// Set default unit for existing records
$db->query("UPDATE stock_movements SET unit_id = 1 WHERE unit_id IS NULL");
// ... existing code ... 