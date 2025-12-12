<?php
require_once __DIR__ . '/../../../app/config/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// 1. SEGURIDAD
if(!isset($_SESSION['ID_USER'])) {
    header("Location: " . BASE_URL . "public/login.php");
    exit;
}

$current_user = $_SESSION['ID_USER'];
$id_exhibicion = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$url_actual = $_SERVER['PHP_SELF'] . ($id_exhibicion > 0 ? "?id=$id_exhibicion" : "");

// MENSAJES FLASH
$msg = ""; $msgType = "";
if(isset($_SESSION['temp_msg'])) {
    $msg = $_SESSION['temp_msg'];
    $msgType = $_SESSION['temp_msg_type'];
    if($msgType == 'danger') $msgType = 'error'; 
    unset($_SESSION['temp_msg']); unset($_SESSION['temp_msg_type']);
}

// =================================================================================
// MODO 1: BANDEJA DE ENTRADA (Cuando entras desde el Menú sin ID)
// =================================================================================
if($id_exhibicion == 0) {
    require_once __DIR__ . '/../../../app/views/layout/header.php';
    
    // Consultar qué exhibiciones mías tienen solicitudes pendientes
    $sql = "SELECT E.ID_EXHIBITION, E.NAME, COUNT(R.ID_REQUEST) as TOTAL_PENDING
            FROM EXHIBITIONS E
            JOIN EXHIBITION_REQUESTS R ON E.ID_EXHIBITION = R.ID_EXHIBITION
            WHERE E.ID_CREATOR = :me AND R.STATUS = 'Pending'
            GROUP BY E.ID_EXHIBITION";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':me' => $current_user]);
    $bandeja = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <div class="container my-5">
        <h2 class="fw-bold mb-4"><i class="bi bi-inbox"></i> Solicitudes de Exhibición</h2>
        
        <?php if(empty($bandeja)): ?>
            <div class="alert alert-success py-5 text-center shadow-sm">
                <i class="bi bi-check-circle display-1 text-success"></i>
                <h4 class="mt-3">¡Todo limpio!</h4>
                <p>No tienes solicitudes pendientes en tus exhibiciones.</p>
                <a href="index.php" class="btn btn-outline-success mt-2">Ver mis exhibiciones</a>
            </div>
        <?php else: ?>
            <div class="alert alert-warning border-warning">
                <i class="bi bi-bell-fill"></i> Tienes artistas esperando respuesta en las siguientes exhibiciones:
            </div>
            
            <div class="row g-3">
                <?php foreach($bandeja as $row): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card shadow-sm h-100 border-0">
                        <div class="card-body">
                            <h5 class="card-title fw-bold"><?= $row['NAME'] ?></h5>
                            <p class="card-text text-muted">
                                <span class="badge bg-danger rounded-pill"><?= $row['TOTAL_PENDING'] ?></span> solicitud(es) pendiente(s)
                            </p>
                            <a href="?id=<?= $row['ID_EXHIBITION'] ?>" class="btn btn-primary w-100">
                                Gestionar <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <?php
    require_once __DIR__ . '/../../../app/views/layout/footer.php';
    exit; // DETENEMOS AQUÍ SI ESTAMOS EN MODO BANDEJA
}

// =================================================================================
// MODO 2: GESTIÓN ESPECÍFICA (Cuando entras con un ID o seleccionas de la bandeja)
// =================================================================================

// Validar dueño
$stmt = $pdo->prepare("SELECT * FROM EXHIBITIONS WHERE ID_EXHIBITION = :id AND ID_CREATOR = :user");
$stmt->execute([':id' => $id_exhibicion, ':user' => $current_user]);
$exhibicion = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$exhibicion) {
    // Redirigir amablemente si no es suya
    echo "<script>window.location.href='solicitudes.php';</script>"; 
    exit;
}

// Lógica POST (Aceptar/Rechazar)
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $id_request = $_POST['id_request'];
    $accion     = $_POST['action'];

    if($accion == 'accept') {
        $pdo->prepare("UPDATE EXHIBITION_REQUESTS SET STATUS = 'Accepted' WHERE ID_REQUEST = :id")->execute([':id' => $id_request]);
        
        // OPCIONAL: Insertar en ARTWORKS_has_EXHIBITIONS si la lógica lo requiere
        // Por ahora solo marcamos la solicitud como aceptada.
        
        $_SESSION['temp_msg'] = "Artista aceptado.";
        $_SESSION['temp_msg_type'] = "success";

    } elseif($accion == 'reject') {
        $pdo->prepare("UPDATE EXHIBITION_REQUESTS SET STATUS = 'Rejected' WHERE ID_REQUEST = :id")->execute([':id' => $id_request]);
        $_SESSION['temp_msg'] = "Solicitud rechazada.";
        $_SESSION['temp_msg_type'] = "warning";
    }
    header("Location: " . $url_actual);
    exit;
}

// Consultar Solicitudes de ESTA exhibición
$sql = "SELECT R.*, A.FULL_NAME, N.NAME as NACIONALIDAD
        FROM EXHIBITION_REQUESTS R
        JOIN ARTISTS A ON R.ID_ARTIST = A.ID_ARTIST
        LEFT JOIN NATIONALITY N ON A.ID_NATIONALITY = N.ID_NATIONALITY
        WHERE R.ID_EXHIBITION = :id_ex AND R.STATUS = 'Pending'
        ORDER BY R.REQUEST_DATE ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id_ex' => $id_exhibicion]);
$solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Consultar Aceptados
$sql_aceptados = "SELECT R.*, A.FULL_NAME FROM EXHIBITION_REQUESTS R 
                  JOIN ARTISTS A ON R.ID_ARTIST = A.ID_ARTIST 
                  WHERE R.ID_EXHIBITION = :id_ex AND R.STATUS = 'Accepted'";
$stmt_ok = $pdo->prepare($sql_aceptados);
$stmt_ok->execute([':id_ex' => $id_exhibicion]);
$aceptados = $stmt_ok->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../../../app/views/layout/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="<?= BASE_URL ?>public/admin/exhibiciones/index.php" class="btn btn-outline-secondary btn-sm mb-2"><i class="bi bi-arrow-left"></i> Volver a todas</a>
            <h2 class="fw-bold">Gestión: <?= htmlspecialchars($exhibicion['NAME']) ?></h2>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-5">
        <div class="card-header bg-warning text-dark fw-bold">
            <i class="bi bi-person-plus-fill"></i> Solicitudes Pendientes
        </div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0 align-middle">
                <tbody>
                    <?php if(empty($solicitudes)): ?>
                        <tr><td class="p-4 text-center text-muted">No hay solicitudes nuevas para esta exhibición.</td></tr>
                    <?php else: ?>
                        <?php foreach($solicitudes as $row): ?>
                        <tr>
                            <td class="ps-4" style="width: 50px;">
                                <div class="rounded-circle bg-secondary d-flex justify-content-center align-items-center text-white fw-bold" style="width: 40px; height: 40px;">
                                    <?= substr($row['FULL_NAME'], 0, 1) ?>
                                </div>
                            </td>
                            <td>
                                <div class="fw-bold"><?= $row['FULL_NAME'] ?></div>
                                <small class="text-muted"><?= $row['NACIONALIDAD'] ?? 'Nacionalidad N/A' ?> • <?= date('d/m/Y', strtotime($row['REQUEST_DATE'])) ?></small>
                            </td>
                            <td class="text-end pe-4">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="id_request" value="<?= $row['ID_REQUEST'] ?>">
                                    <button type="submit" name="action" value="accept" class="btn btn-success btn-sm me-1"><i class="bi bi-check-lg"></i></button>
                                    <button type="submit" name="action" value="reject" class="btn btn-outline-danger btn-sm"><i class="bi bi-x-lg"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <h5 class="fw-bold text-muted"><i class="bi bi-people"></i> Artistas Participantes</h5>
    <ul class="list-group">
        <?php foreach($aceptados as $row): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <?= $row['FULL_NAME'] ?>
                <span class="badge bg-success rounded-pill">Confirmado</span>
            </li>
        <?php endforeach; ?>
        <?php if(empty($aceptados)) echo '<li class="list-group-item text-muted">Aún no hay participantes aceptados.</li>'; ?>
    </ul>
</div>

<script>
    <?php if($msg != ""): ?>
        Swal.fire({
            icon: '<?= $msgType ?>',
            title: '<?= $msg ?>',
            toast: true, position: 'top-end', showConfirmButton: false, timer: 3000
        });
    <?php endif; ?>
</script>

<?php require_once __DIR__ . '/../../../app/views/layout/footer.php'; ?>