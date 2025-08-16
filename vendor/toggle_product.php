<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('vendor');

$vendor_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id']) && isset($_GET['action'])) {
    $product_id = (int)$_GET['id'];
    $action = $_GET['action'];
    
    try {
        // Check if product belongs to this vendor
        $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND vendor_id = ?");
        if ($stmt->execute([$product_id, $vendor_id])) {
            $product = $stmt->fetch();
            
            if ($product) {
                $new_status = ($action === 'activate') ? 'active' : 'inactive';
                
                $update_stmt = $pdo->prepare("UPDATE products SET status = ? WHERE id = ? AND vendor_id = ?");
                if ($update_stmt->execute([$new_status, $product_id, $vendor_id])) {
                    $status_message = ($action === 'activate') ? 'activé' : 'désactivé';
                    flash_message("Produit $status_message avec succès!", 'success');
                } else {
                    flash_message("Erreur lors de la mise à jour du produit.", 'error');
                }
            } else {
                flash_message("Produit non trouvé ou vous n'avez pas les permissions.", 'error');
            }
        } else {
            flash_message("Erreur lors de la vérification du produit.", 'error');
        }
    } catch (PDOException $e) {
        error_log("Database error in vendor/toggle_product.php: " . $e->getMessage());
        flash_message("Erreur de base de données.", 'error');
    }
}

redirect('products.php');
?>