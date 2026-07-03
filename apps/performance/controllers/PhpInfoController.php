<?php
// Check access to phpinfo
if (isset($_GET['phpinfo'])) {
    if ($userRole < 2) {
        header('HTTP/1.0 403 Forbidden');
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
    
    ob_start(); 
    phpinfo(); 
    $info = ob_get_clean();
    header('Content-Type: application/json');
    echo json_encode(['phpinfo' => $info]); 
    exit;
}
