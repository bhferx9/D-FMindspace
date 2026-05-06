<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro - D&F Mindspace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #F0FBFB; font-family: 'Quicksand', sans-serif; }
        .card-registro { border-radius: 25px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .btn-principal { background-color: #4DB6AC; color: white; border-radius: 50px; font-weight: bold; border: none; }
        .btn-principal:hover { background-color: #3d9189; color: white; }
        .form-label { font-weight: 600; color: #444; }
        .icon-select { font-size: 1.2rem; margin-right: 10px; color: #4DB6AC; }
        .section-parent { border-left: 5px solid #FF8A65; background: #FFF5F2; padding: 20px; border-radius: 15px; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card card-registro p-4 p-md-5">
                <div class="text-center mb-4">
                    <h2 class="fw-bold" style="color: #4DB6AC;">Únete a Mindspace</h2>
                    <p class="text-muted">Crea tu cuenta para comenzar la aventura educativa</p>
                </div>

                <form action="procesar_registro.php" method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre Completo</label>
                            <input type="text" name="nombre" class="form-control rounded-pill" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Correo Electrónico</label>
                            <input type="email" name="email" class="form-control rounded-pill" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contraseña</label>
                            <input type="password" name="password" class="form-control rounded-pill" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Teléfono</label>
                            <input type="text" name="telefono" class="form-control rounded-pill">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fecha de Nacimiento</label>
                            <input type="date" name="fecha_nac" class="form-control rounded-pill" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">¿Quién eres?</label>
                            <select name="tipo" id="tipo_usuario" class="form-select rounded-pill" onchange="toggleParentFields()" required>
                                <option value="alumno">👦 Alumno</option>
                                <option value="tutor">👨‍🏫 Tutor</option>
                                <option value="padre">👨‍👩‍👦 Padre de Familia</option>
                            </select>
                        </div>
                    </div>

                    <div id="parent_fields" style="display: none;" class="mt-4 section-parent">
                        <h5 class="fw-bold mb-3" style="color: #E67E22;"><i class="fas fa-child me-2"></i>Vincular con tu Hijo</h5>
                        
                        <div class="mb-3">
                            <label class="form-label">¿Cuántos niños tienes en Mindspace?</label>
                            <input type="number" name="cantidad_hijos" class="form-control rounded-pill" min="1" value="1">
                        </div>

                        <div class="row">
                            <p class="small text-muted mb-2">Por seguridad, ingresa las credenciales de uno de tus hijos para vincular la cuenta:</p>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small">Correo del Hijo</label>
                                <input type="email" name="hijo_email" id="hijo_email" class="form-control rounded-pill" placeholder="correo@hijo.com">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small">Contraseña del Hijo</label>
                                <input type="password" name="hijo_password" id="hijo_password" class="form-control rounded-pill">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-principal w-100 py-2 mt-4 shadow-sm">FINALIZAR REGISTRO</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function toggleParentFields() {
    const tipo = document.getElementById("tipo_usuario").value;
    const fields = document.getElementById("parent_fields");
    const hEmail = document.getElementById("hijo_email");
    const hPass = document.getElementById("hijo_password");

    if (tipo === 'padre') {
        fields.style.display = 'block';
        hEmail.required = true;
        hPass.required = true;
    } else {
        fields.style.display = 'none';
        hEmail.required = false;
        hPass.required = false;
    }
}
</script>
</body>
</html>