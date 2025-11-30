// Clean up duplicate roles
$db->query("DELETE FROM roles WHERE id NOT IN (
    SELECT min_id FROM (
        SELECT MIN(id) as min_id 
        FROM roles 
        GROUP BY name
    ) as temp
)");

// Standardize role names
$roles = [
    ['name' => 'admin', 'description' => 'Yönetici (Tam Yetki)'],
    ['name' => 'manager', 'description' => 'Müdür (Düzenleme Yetkisi)'],
    ['name' => 'accountant', 'description' => 'Muhasebeci (Mali Yetki)'],
    ['name' => 'staff', 'description' => 'Personel (Sınırlı Yetki)'],
    ['name' => 'viewer', 'description' => 'İzleyici (Sadece Görüntüleme)']
];

foreach ($roles as $role) {
    $db->query("INSERT IGNORE INTO roles (name, description) VALUES (:name, :description)");
    $db->bind(':name', $role['name']);
    $db->bind(':description', $role['description']);
    $db->execute();
} 