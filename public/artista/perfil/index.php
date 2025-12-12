<?php
require_once __DIR__ . '/../../../app/config/config.php';

// 1. INICIAR SESIÓN
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 2. SEGURIDAD: Verificar Rol de Artista (3)
if(!isset($_SESSION['ROLE']) || $_SESSION['ROLE'] != 3) {
    header("Location: " . BASE_URL . "public/login.php");
    exit;
}

$id_user = $_SESSION['ID_USER']; // ID_ARTIST

// --- 2.1 AJAX PARA VERIFICAR CONTRASEÑA ACTUAL EN TIEMPO REAL ---
if (isset($_POST['ajax_verify_pass'])) {
    header('Content-Type: application/json');
    $passToCheck = $_POST['ajax_verify_pass'];
    
    // Consultamos la tabla ARTISTS
    $stmt = $pdo->prepare("SELECT PASS FROM ARTISTS WHERE ID_ARTIST = :id");
    $stmt->execute([':id' => $id_user]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

    // VERIFICACIÓN (Ajusta '==' o 'password_verify' según tu sistema)
    // Opción B: Texto Plano (Según tu referencia anterior)
    $isValid = ($passToCheck === $currentUser['PASS']);

    echo json_encode(['valid' => $isValid]);
    exit; 
}
// ------------------------------------------------------------------

$msg = ""; $msgType = "";

// 3. PROCESAR FORMULARIO
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['ajax_verify_pass'])) {
    $nombre       = trim($_POST['full_name']);
    $email        = trim($_POST['email']);
    $biografia    = trim($_POST['biography']);      
    $nacionalidad = $_POST['id_nationality'];       
    
    $currentPass = $_POST['current_password'] ?? '';
    $newPass     = $_POST['new_password'] ?? '';

    $valid = true;

    // A. Validar Nombre (REGEX ESTRICTO: Title Case)
    // Permite: "Nau Coronado", "Juan De Dios"
    // Rechaza: "nau coronado", "Nau coronado"
    if (!preg_match("/^([A-ZÁÉÍÓÚÑ][a-záéíóúñ]*)(\s[A-ZÁÉÍÓÚÑ][a-záéíóúñ]*)*$/u", $nombre)) {
        $msg = "El nombre debe iniciar con Mayúscula en cada palabra (Ej: Nau Coronado).";
        $msgType = "error";
        $valid = false;
    }

    // B. Lógica de Cambio de Contraseña
    $sql_pass = "";
    $params = [];

    if ($valid && !empty($newPass)) {
        if (empty($currentPass)) {
            $msg = "Debes ingresar tu contraseña actual para cambiarla.";
            $msgType = "error";
            $valid = false;
        } else {
            // Verificar en BD
            $stmt = $pdo->prepare("SELECT PASS FROM ARTISTS WHERE ID_ARTIST = :id");
            $stmt->execute([':id' => $id_user]);
            $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($currentPass !== $dbUser['PASS']) {
                $msg = "La contraseña actual es incorrecta.";
                $msgType = "error";
                $valid = false;
            }
            // Verificar RegEx Contraseña
            elseif (!preg_match("/^[A-Z](?=.*[\W_]).{7,}$/", $newPass)) {
                $msg = "La nueva contraseña no cumple los requisitos.";
                $msgType = "error";
                $valid = false;
            } else {
                // $newPassHash = password_hash($newPass, PASSWORD_DEFAULT); // Si usas hash
                $sql_pass = ", PASS = :pass";
                $params[':pass'] = $newPass; 
            }
        }
    }

    // C. GUARDAR SI ES VÁLIDO
    if ($valid) {
        try {
            // Subida de imagen
            $sql_img = "";
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                $filename = $_FILES['profile_image']['name'];
                $filetmp  = $_FILES['profile_image']['tmp_name'];
                $ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                if (in_array($ext, $allowed)) {
                    $newName = 'artist_' . $id_user . '_' . time() . '.' . $ext;
                    $targetDir = __DIR__ . '/../../../public/img/profiles/';
                    
                    if (!file_exists($targetDir)) { mkdir($targetDir, 0777, true); }

                    if (move_uploaded_file($filetmp, $targetDir . $newName)) {
                        $sql_img = ", PROFILE_IMAGE = :image";
                        $params[':image'] = $newName; 
                        $_SESSION['USER_IMG'] = BASE_URL . "public/img/profiles/" . $newName; 
                    }
                } else {
                    $msg = "Formato de imagen no válido.";
                    $msgType = "error";
                    $valid = false;
                }
            }

            if ($valid) {
                $sql = "UPDATE ARTISTS SET 
                        FULL_NAME = :name, 
                        EMAIL = :email, 
                        BIOGRAPHY = :bio, 
                        ID_NATIONALITY = :nac 
                        $sql_img 
                        $sql_pass 
                        WHERE ID_ARTIST = :id";
                
                $params[':name']  = $nombre;
                $params[':email'] = $email;
                $params[':bio']   = $biografia;
                $params[':nac']   = $nacionalidad;
                $params[':id']    = $id_user;

                $stmt = $pdo->prepare($sql);
                if ($stmt->execute($params)) {
                    $_SESSION['USER_NAME'] = $nombre;
                    $msg = "Perfil actualizado correctamente.";
                    $msgType = "success";
                } else {
                    $msg = "Error al guardar en la base de datos.";
                    $msgType = "error";
                }
            }

        } catch (Exception $e) {
            $msg = "Error: " . $e->getMessage();
            $msgType = "error";
        }
    }
}

// 4. OBTENER DATOS ACTUALES
$stmt = $pdo->prepare("SELECT * FROM ARTISTS WHERE ID_ARTIST = :id");
$stmt->execute([':id' => $id_user]);
$artist = $stmt->fetch(PDO::FETCH_ASSOC);

// 5. OBTENER NACIONALIDADES
$stmt_nat = $pdo->query("SELECT * FROM NATIONALITY ORDER BY NAME ASC");
$nacionalidades = $stmt_nat->fetchAll(PDO::FETCH_ASSOC);

// Visualización de foto
$hasImage = false;
$imgUrl = "";
if (!empty($artist['PROFILE_IMAGE'])) {
    $imgName = basename($artist['PROFILE_IMAGE']); 
    $filePath = __DIR__ . '/../../../public/img/profiles/' . $imgName;
    if (file_exists($filePath)) {
        $hasImage = true;
        $imgUrl = BASE_URL . "public/img/profiles/" . $imgName;
    } 
}

require_once __DIR__ . '/../../../app/views/layout/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="bi bi-person-circle"></i> Mi Perfil</h2>
        <a href="<?= BASE_URL ?>public/artista/ventas/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0 text-center p-4 h-100">
                <div class="mb-3 d-flex justify-content-center align-items-center" style="min-height: 160px;">
                    <?php if ($hasImage): ?>
                        <img src="<?= $imgUrl ?>" alt="Foto" class="rounded-circle img-thumbnail shadow-sm" style="width: 150px; height: 150px; object-fit: cover;">
                    <?php else: ?>
                        <i class="bi bi-person-circle text-secondary" style="font-size: 9rem; line-height: 1;"></i>
                    <?php endif; ?>
                </div>
                <h5 class="fw-bold text-dark"><?= htmlspecialchars($artist['FULL_NAME']) ?></h5>
                <p class="badge bg-primary mb-2">Artista Verificado</p>
                <br>
                <small class="text-muted">Miembro desde: <?= date('M Y', strtotime($artist['DATE_CREATED'])) ?></small>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-dark text-white fw-bold">
                    <i class="bi bi-pencil-square"></i> Editar Información
                </div>
                <div class="card-body p-4">
                    <form method="POST" enctype="multipart/form-data" id="profileForm">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Nombre Artístico</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                                    <input type="text" name="full_name" id="inputName" class="form-control" value="<?= htmlspecialchars($artist['FULL_NAME']) ?>" required>
                                </div>
                                <div id="nameFeedback" class="form-text mt-1 small">
                                    <i class="bi bi-info-circle"></i> Solo letras y espacios.
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Correo Electrónico</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-envelope"></i></span>
                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($artist['EMAIL']) ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Nacionalidad</label>
                            <select name="id_nationality" class="form-select">
                                <option value="">Seleccionar...</option>
                                <?php foreach ($nacionalidades as $nat): ?>
                                    <option value="<?= $nat['ID_NATIONALITY'] ?>" 
                                        <?= ($artist['ID_NATIONALITY'] == $nat['ID_NATIONALITY']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($nat['NAME']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Biografía Pública</label>
                            <textarea name="biography" class="form-control" rows="4" placeholder="Cuéntanos sobre tu arte..."><?= htmlspecialchars($artist['BIOGRAPHY']) ?></textarea>
                            <div class="form-text text-muted">Esta información aparecerá junto a tus obras.</div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Cambiar Foto de Perfil</label>
                            <input type="file" name="profile_image" class="form-control" accept="image/*">
                        </div>

                        <hr class="my-4">

                        <h5 class="text-danger fw-bold mb-3"><i class="bi bi-shield-lock"></i> Cambiar Contraseña</h5>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Contraseña Actual</label>
                                <div class="input-group">
                                    <input type="password" name="current_password" id="inputCurrentPass" class="form-control" placeholder="Ingresa tu contraseña actual">
                                    <span class="input-group-text bg-white" id="iconCurrentPass">
                                        <i class="bi bi-question-circle text-muted"></i>
                                    </span>
                                </div>
                                <div class="form-text">Necesaria para autorizar cambios.</div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Nueva Contraseña</label>
                                <input type="password" name="new_password" id="inputNewPass" class="form-control" placeholder="Nueva contraseña">
                                <div id="passFeedback" class="form-text mt-1 small">
                                    <i class="bi bi-info-circle"></i> Inicio Mayúscula, Min 8 chars, 1 Especial.
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mt-3">
                            <button type="submit" id="btnSave" class="btn btn-primary px-4 fw-bold">
                                <i class="bi bi-save"></i> Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const inputName = document.getElementById('inputName');
        const nameFeedback = document.getElementById('nameFeedback');
        
        const inputCurrentPass = document.getElementById('inputCurrentPass');
        const iconCurrentPass = document.getElementById('iconCurrentPass');

        const inputNewPass = document.getElementById('inputNewPass');
        const passFeedback = document.getElementById('passFeedback');

        // REGEX NOMBRE CORREGIDO: "Nau Coronado" (Correcto) vs "Nau coronado" (Incorrecto)
        // Obliga a cada palabra a iniciar con mayúscula
        const regexName = /^([A-ZÁÉÍÓÚÑ][a-záéíóúñ]*)(\s[A-ZÁÉÍÓÚÑ][a-záéíóúñ]*)*$/;
        
        const regexNewPass = /^[A-Z](?=.*[\W_]).{7,}$/; 

        // 1. VALIDAR NOMBRE
        inputName.addEventListener('input', function() {
            const val = this.value;
            // Validamos solo si no está vacío (y hacemos trim para no fallar por espacios finales mientras escribe)
            if (regexName.test(val) && val.trim() !== "") {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
                nameFeedback.className = "form-text text-success fw-bold";
                nameFeedback.innerHTML = '<i class="bi bi-check-circle-fill"></i> Formato correcto.';
            } else {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
                nameFeedback.className = "form-text text-danger fw-bold";
                nameFeedback.innerHTML = '<i class="bi bi-x-circle-fill"></i> Cada palabra debe iniciar con Mayúscula (Ej: Nau Coronado).';
            }
        });

        // 2. VERIFICAR CONTRASEÑA ACTUAL (AJAX)
        inputCurrentPass.addEventListener('keyup', function() {
            const val = this.value;
            
            if (val === "") {
                iconCurrentPass.innerHTML = '<i class="bi bi-question-circle text-muted"></i>';
                this.classList.remove('is-valid', 'is-invalid');
                return;
            }

            const formData = new FormData();
            formData.append('ajax_verify_pass', val);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.valid) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                    iconCurrentPass.innerHTML = '<i class="bi bi-check-circle-fill text-success fs-5"></i>';
                } else {
                    this.classList.remove('is-valid');
                    iconCurrentPass.innerHTML = '<i class="bi bi-x-circle-fill text-danger fs-5"></i>';
                }
            })
            .catch(error => console.error('Error:', error));
        });

        // 3. VALIDAR NUEVA CONTRASEÑA
        inputNewPass.addEventListener('input', function() {
            const val = this.value;

            if (val === "") {
                this.classList.remove('is-valid', 'is-invalid');
                passFeedback.className = "form-text text-muted";
                passFeedback.innerHTML = '<i class="bi bi-info-circle"></i> Inicio Mayúscula, Min 8 chars, 1 Especial.';
                return;
            }

            if (regexNewPass.test(val)) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
                passFeedback.className = "form-text text-success fw-bold";
                passFeedback.innerHTML = '<i class="bi bi-check-circle-fill"></i> Contraseña fuerte.';
            } else {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
                passFeedback.className = "form-text text-danger fw-bold";
                passFeedback.innerHTML = '<i class="bi bi-x-circle-fill"></i> No cumple requisitos.';
            }
        });
    });

    <?php if($msg != ""): ?>
        Swal.fire({
            icon: '<?= $msgType ?>',
            title: '<?= $msg ?>',
            toast: true, position: 'top-end', showConfirmButton: false, timer: 3000
        });
    <?php endif; ?>
</script>

<?php require_once __DIR__ . '/../../../app/views/layout/footer.php'; ?>