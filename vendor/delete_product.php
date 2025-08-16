<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('vendor');

$vendor_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $product_id = (int)$_GET['id'];
    
    try {
        // Check if product belongs to this vendor
        $stmt = $pdo->prepare("SELECT id, image_url FROM products WHERE id = ? AND vendor_id = ?");
        if ($stmt->execute([$product_id, $vendor_id])) {
            $product = $stmt->fetch();
            
            if ($product) {
                // Delete the product
                $delete_stmt = $pdo->prepare("DELETE FROM products WHERE id = ? AND vendor_id = ?");
                if ($delete_stmt->execute([$product_id, $vendor_id])) {
                    // Delete associated image file if it exists
                    if ($product['image_url'] && file_exists("../uploads/" . $product['image_url'])) {
                        unlink("../uploads/" . $product['image_url']);
                    }
                    
                    flash_message("Produit supprimé avec succès!", 'success');
                } else {
                    flash_message("Erreur lors de la suppression du produit.", 'error');
                }
            } else {
                flash_message("Produit non trouvé ou vous n'avez pas les permissions.", 'error');
            }
        } else {
            flash_message("Erreur lors de la vérification du produit.", 'error');
        }
    } catch (PDOException $e) {
        error_log("Database error in vendor/delete_product.php: " . $e->getMessage());
        flash_message("Erreur de base de données.", 'error');
    }
}

redirect('products.php');
?>