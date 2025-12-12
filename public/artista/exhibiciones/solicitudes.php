<?php
require_once __DIR__ . '/../../../app/config/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// 1. SEGURIDAD: Solo artistas (Role 3)
if (!isset($_SESSION['ROLE']) || $_SESSION['ROLE'] != 3) {
    header("Location: " . BASE_URL . "public/login.php");
    exit;
}

$id_artista_logueado = $_SESSION['ID_USER'];
$id_exhibicion = isset($_GET['id']) ? $_GET['id'] : null;

if (!$id_exhibicion) {
    header("Location: index.php");
    exit;
}

// 2. VERIFICAR QUE LA EXHIBICIÓN PERTENECE AL ARTISTA
$stmt = $pdo->prepare("SELECT NAME, LOCATION FROM EXHIBITIONS WHERE ID_EXHIBITION = ? AND ID_CREATOR = ?");
$stmt->execute([$id_exhibicion, $id_artista_logueado]);
$exhibicion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exhibicion) {
    // Si no encuentra la exhibición o no es suya, lo devuelve
    $_SESSION['temp_msg'] = "No tienes permiso para gestionar esta exhibición.";
    $_SESSION['temp_msg_type'] = "error";
    header("Location: index.php");
    exit;
}

// ---------------------------------------------------------
// LÓGICA DE ACCIONES (ACEPTAR / RECHAZAR)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    try {
        $id_request = $_POST['id_request'];
        $nuevo_status = ($_POST['action'] == 'accept') ? 'Accepted' : 'Rejected';

        $update = $pdo->prepare("UPDATE EXHIBITION_REQUESTS SET STATUS = ? WHERE ID_REQUEST = ?");
        $update->execute([$nuevo_status, $id_request]);

        $_SESSION['msg'] = "Solicitud " . ($nuevo_status == 'Accepted' ? 'aceptada' : 'rechazada') . " correctamente.";
        $_SESSION['msg_type'] = "success";
        
    } catch (Exception $e) {
        $_SESSION['msg'] = "Error al procesar: " . $e->getMessage();
        $_SESSION['msg_type'] = "error";
    }
    
    // Recargar para evitar reenvío de formulario
    header("Location: solicitudes.php?id=" . $id_exhibicion);
    exit;
}

// ---------------------------------------------------------
// CONSULTA DE SOLICITUDES
// ---------------------------------------------------------
// Obtenemos los datos de la solicitud + datos del usuario (artista solicitante)
$sql = "SELECT R.ID_REQUEST, R.STATUS, R.REQUEST_DATE, A.FULL_NAME, A.EMAIL, A.ID_ARTIST
        FROM EXHIBITION_REQUESTS R
        JOIN ARTISTS A ON R.ID_ARTIST = A.ID_ARTIST
        WHERE R.ID_EXHIBITION = ?
        ORDER BY FIELD(R.STATUS, 'Pending', 'Accepted', 'Rejected'), R.REQUEST_DATE DESC";

$stmt_req = $pdo->prepare($sql);
$stmt_req->execute([$id_exhibicion]);
$solicitudes = $stmt_req->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../../app/views/layout/header.php';
?>

<div class="container my-5">
    
    <div class="d-flex align-items-center mb-4">
        <a href="index.php" class="btn btn-outline-secondary me-3">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
        <div>
            <h6 class="text-muted mb-0">Gestionando solicitudes para:</h6>
            <h2 class="fw-bold mb-0 text-primary"><?= $exhibicion['NAME'] ?></h2>
        </div>
    </div>

    <?php if(isset($_SESSION['msg'])): ?>
        <div class="alert alert-<?= $_SESSION['msg_type'] == 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
            <?= $_SESSION['msg'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['msg']); unset($_SESSION['msg_type']); ?>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <?php if(empty($solicitudes)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox display-4 text-muted"></i>
                    <p class="mt-3 text-muted">Aún no hay solicitudes para unirse a este evento.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Artista</th>
                                <th>Fecha Solicitud</th>
                                <th>Estado</th>
                                <th class="text-end pe-4">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($solicitudes as $req): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle bg-secondary d-flex justify-content-center align-items-center me-3" style="width: 40px; height: 40px;">
                                            <i class="bi bi-person-fill text-white"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold"><?= $req['FULL_NAME'] ?></div>
                                            <div class="small text-muted"><?= $req['EMAIL'] ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-muted small">
                                    <?= date('d/m/Y H:i', strtotime($req['REQUEST_DATE'])) ?>
                                </td>
                                <td>
                                    <?php if($req['STATUS'] == 'Pending'): ?>
                                        <span class="badge bg-warning text-dark">Pendiente</span>
                                    <?php elseif($req['STATUS'] == 'Accepted'): ?>
                                        <span class="badge bg-success">Aceptado</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Rechazado</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if($req['STATUS'] == 'Pending'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="id_request" value="<?= $req['ID_REQUEST'] ?>">
                                            
                                            <button type="submit" name="action" value="accept" class="btn btn-success btn-sm me-1" title="Aceptar">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                            
                                            <button type="submit" name="action" value="reject" class="btn btn-outline-danger btn-sm" title="Rechazar" onclick="return confirm('¿Rechazar a este artista?');">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small fst-italic">Procesado</span>
                                    <?php endif; ?>
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