<?php
require_once __DIR__ . '/../../../app/config/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// 1. SEGURIDAD: Verificar que sea Artista (Role 3)
if (!isset($_SESSION['ROLE']) || $_SESSION['ROLE'] != 3) {
    header("Location: " . BASE_URL . "public/login.php");
    exit;
}

$id_artista = $_SESSION['ID_USER'];

// 2. CONSULTA SQL - VERIFICADA CON TU BD
$sql = "SELECT 
            o.ORDER_DATE,
            a.TITLE,
            a.IMAGE_COVER,
            u.FULL_NAME AS BUYER,
            u.EMAIL AS BUYER_EMAIL,
            o.SHIPPING_ADDRESS,  /* CORRECTO: Viene de la tabla ORDERS (o) */
            o.CITY,              /* CORRECTO: Viene de la tabla ORDERS (o) */
            oi.PRICE,
            s.NAME AS STATUS
        FROM ORDER_ITEMS oi
        JOIN ARTWORKS a ON oi.ID_ARTWORK = a.ID_ARTWORK
        JOIN ORDERS o ON oi.ID_ORDER = o.ID_ORDER
        JOIN USERS u ON o.ID_USER = u.ID_USER
        JOIN STATUS_ORDERS s ON o.ID_STATUS = s.ID_STATUS
        WHERE a.ID_ARTIST = ?
        ORDER BY o.ORDER_DATE DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_artista]);
    $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error en la base de datos: " . $e->getMessage());
}

// 3. CALCULAR TOTAL GANADO
$total_ganancias = 0;
if ($ventas) {
    foreach ($ventas as $v) {
        $total_ganancias += $v['PRICE'];
    }
}

require_once __DIR__ . '/../../../app/views/layout/header.php';
?>

<div class="container my-5">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold">Mis Ventas</h2>
        <div class="bg-success text-white px-4 py-2 rounded shadow-sm">
            <small>Ganancias Totales</small>
            <div class="h4 mb-0">$<?= number_format($total_ganancias, 2) ?></div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <?php if (empty($ventas)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-currency-dollar display-4 text-muted"></i>
                    <p class="mt-3 text-muted">Aún no tienes ventas registradas.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Fecha</th>
                                <th>Obra Vendida</th>
                                <th>Detalles del Comprador</th>
                                <th>Precio Venta</th>
                                <th class="pe-4">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ventas as $venta): ?>
                                <tr>
                                    <td class="ps-4 text-muted">
                                        <?= date('d/m/Y', strtotime($venta['ORDER_DATE'])) ?>
                                    </td>
                                    
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php $img = !empty($venta['IMAGE_COVER']) ? $venta['IMAGE_COVER'] : 'https://via.placeholder.com/50'; ?>
                                            <img src="<?= htmlspecialchars($img) ?>" 
                                                 alt="Art" 
                                                 class="rounded me-3 shadow-sm" 
                                                 style="width: 50px; height: 50px; object-fit: cover;">
                                            <span class="fw-bold text-dark"><?= htmlspecialchars($venta['TITLE']) ?></span>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($venta['BUYER']) ?></div>
                                        <div class="small text-muted mb-1"><?= htmlspecialchars($venta['BUYER_EMAIL']) ?></div>
                                        
                                        <div class="p-2 bg-light rounded border d-inline-block small">
                                            <i class="bi bi-truck text-primary me-1"></i>
                                            <strong>Enviar a:</strong> 
                                            <?= htmlspecialchars($venta['SHIPPING_ADDRESS']) ?>, 
                                            <?= htmlspecialchars($venta['CITY']) ?>
                                        </div>
                                    </td>

                                    <td>
                                        <h5 class="mb-0 text-success fw-bold">$<?= number_format($venta['PRICE'], 2) ?></h5>
                                    </td>

                                    <td class="pe-4">
                                        <span class="badge bg-secondary px-3 py-2 rounded-pill">
                                            <?= htmlspecialchars($venta['STATUS']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../../app/views/layout/footer.php'; ?>