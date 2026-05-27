<?php
session_start();
require_once "../config/database.php";

// Protection de la page - Vérification du rôle Teacher
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "teacher") {
    header("Location: ../login.php");
    exit();
}

$teacher_id = $_SESSION["user_id"];
$message = "";
$status = "success";

// --- GESTION DU CRUD COURS ---

// 1. Ajouter un cours
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_course'])) {
    $nom = trim($_POST['nom_cours']);
    $description = trim($_POST['description']);
    
    if(!empty($nom) && !empty($description)) {
        $stmt = $pdo->prepare("INSERT INTO courses (nom, description, teacher_id) VALUES (?, ?, ?)");
        if ($stmt->execute([$nom, $description, $teacher_id])) {
            $message = "Le cours a été créé avec succès.";
        }
    }
}

// 2. Modifier un cours
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_course'])) {
    $course_id = intval($_POST['course_id']);
    $nom = trim($_POST['nom_cours']);
    $description = trim($_POST['description']);
    
    $stmt = $pdo->prepare("UPDATE courses SET nom = ?, description = ? WHERE id = ? AND teacher_id = ?");
    if ($stmt->execute([$nom, $description, $course_id, $teacher_id])) {
        $message = "Le cours a été modifié avec succès.";
    }
}

// 3. Supprimer un cours
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_course'])) {
    $course_id = intval($_POST['course_id']);
    
    try {
        $pdo->beginTransaction();
        // Nettoyage des clés étrangères rattachées au cours
        $pdo->prepare("DELETE FROM schedules WHERE course_id = ?")->execute([$course_id]);
        $pdo->prepare("DELETE FROM enrollments WHERE course_id = ?")->execute([$course_id]);
        $pdo->prepare("DELETE FROM grades WHERE course_id = ?")->execute([$course_id]);
        
        // Suppression du cours
        $pdo->prepare("DELETE FROM courses WHERE id = ? AND teacher_id = ?")->execute([$course_id, $teacher_id]);
        $pdo->commit();
        $message = "Cours supprimé avec succès.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Erreur lors de la suppression : " . $e->getMessage();
        $status = "danger";
    }
}

// --- GESTION DES NOTES ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_grade'])) {
    $student_id = intval($_POST['student_id']);
    $course_id = intval($_POST['course_id']);
    $grade = floatval($_POST['grade']);
    
    if ($grade >= 0 && $grade <= 20) {
        $stmt = $pdo->prepare("INSERT INTO grades (student_id, course_id, grade) VALUES (?, ?, ?)");
        if($stmt->execute([$student_id, $course_id, $grade])) {
            $message = "Note enregistrée avec succès.";
        }
    } else {
        $message = "La note doit être comprise entre 0 et 20.";
        $status = "danger";
    }
}

// --- GESTION DU PLANNING ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_schedule'])) {
    $course_id = intval($_POST['course_id']);
    $jour = $_POST['jour'];
    $heure_debut = $_POST['heure_debut'];
    $heure_fin = $_POST['heure_fin'];
    $salle = trim($_POST['salle']);
    
    if(!empty($salle)) {
        $stmt = $pdo->prepare("INSERT INTO schedules (course_id, jour, heure_debut, heure_fin, salle) VALUES (?, ?, ?, ?, ?)");
        if($stmt->execute([$course_id, $jour, $heure_debut, $heure_fin, $salle])) {
            $message = "Créneau de cours ajouté à l'emploi du temps.";
        }
    }
}

// Récupération globale des cours du professeur
$stmtCourses = $pdo->prepare("SELECT * FROM courses WHERE teacher_id = ?");
$stmtCourses->execute([$teacher_id]);
$my_courses = $stmtCourses->fetchAll();

// Récupération de la liste des étudiants inscrits (enrollments) aux cours de ce professeur pour le formulaire des notes
$stmtStudents = $pdo->prepare("
    SELECT u.id as student_id, u.nom, u.prenom, c.id as course_id, c.nom as course_nom 
    FROM users u 
    JOIN enrollments e ON u.id = e.student_id 
    JOIN courses c ON e.course_id = c.id 
    WHERE c.teacher_id = ? 
    ORDER BY c.nom, u.nom
");
$stmtStudents->execute([$teacher_id]);
$enrolled_students = $stmtStudents->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartCampus - Espace Enseignant</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#">SmartCampus Enseignant</a>
        <div class="navbar-text text-white ms-auto d-flex align-items-center">
            <span class="me-3">Professeur : <strong><?php echo htmlspecialchars($_SESSION["user_name"]); ?></strong></span>
            <a href="../logout.php" class="btn btn-outline-light btn-sm fw-bold">Déconnexion</a>
        </div>
    </div>
</nav>

<div class="container mb-5">
    <?php if ($message != ""): ?>
        <div class="alert alert-<?php echo $status; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 fw-bold">Créer un nouveau cours</div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Nom de la matière</label>
                            <input type="text" name="nom_cours" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description du programme</label>
                            <textarea name="description" class="form-control" rows="3" required></textarea>
                        </div>
                        <button type="submit" name="add_course" class="btn btn-primary w-100 fw-bold">Publier le cours</button>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 fw-bold">Attribuer une note</div>
                <div class="card-body">
                    <?php if(count($enrolled_students) > 0): ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Élève & Cours associé</label>
                            <select name="student_id_course" class="form-select" onchange="
                                let values = this.value.split('|');
                                document.getElementById('student_id_input').value = values[0];
                                document.getElementById('course_id_input').value = values[1];
                            " required>
                                <option value="">-- Sélectionner un élève --</option>
                                <?php foreach($enrolled_students as $est): ?>
                                    <option value="<?php echo $est['student_id'] . '|' . $est['course_id']; ?>">
                                        <?php echo htmlspecialchars($est['nom'] . ' ' . $est['prenom'] . ' (' . $est['course_nom'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="student_id" id="student_id_input">
                            <input type="hidden" name="course_id" id="course_id_input">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Note (sur 20)</label>
                            <input type="number" step="0.25" min="0" max="20" name="grade" class="form-control" placeholder="Ex: 14.5" required>
                        </div>
                        <button type="submit" name="add_grade" class="btn btn-success w-100 fw-bold">Valider la note</button>
                    </form>
                    <?php else: ?>
                        <p class="text-muted mb-0 small">Aucun étudiant n'est actuellement inscrit à vos cours.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 fw-bold">Ajouter un créneau horaire</div>
                <div class="card-body">
                    <?php if(count($my_courses) > 0): ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Matière concernée</label>
                            <select name="course_id" class="form-select" required>
                                <?php foreach($my_courses as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['nom']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Jour de la semaine</label>
                            <select name="jour" class="form-select" required>
                                <option value="Lundi">Lundi</option>
                                <option value="Mardi">Mardi</option>
                                <option value="Mercredi">Mercredi</option>
                                <option value="Jeudi">Jeudi</option>
                                <option value="Vendredi">Vendredi</option>
                                <option value="Samedi">Samedi</option>
                            </select>
                        </div>
                        <div class="row mb-3">
                            <div class="col">
                                <label class="form-label">Début</label>
                                <input type="time" name="heure_debut" class="form-control" required>
                            </div>
                            <div class="col">
                                <label class="form-label">Fin</label>
                                <input type="time" name="heure_fin" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Salle de cours</label>
                            <input type="text" name="salle" class="form-control" placeholder="Ex: Salle A201" required>
                        </div>
                        <button type="submit" name="add_schedule" class="btn btn-warning w-100 fw-bold">Ajouter au planning</button>
                    </form>
                    <?php else: ?>
                        <p class="text-muted mb-0 small">Créez d'abord un cours pour pouvoir lui définir un planning.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h3 class="card-title fw-bold mb-0">Mes Enseignements (CRUD)</h3>
                </div>
                <div class="card-body">
                    <?php if (count($my_courses) > 0): ?>
                        <?php foreach ($my_courses as $course): ?>
                            <div class="border rounded p-3 mb-3 bg-white shadow-xs">
                                <form method="POST">
                                    <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                    <div class="mb-2">
                                        <label class="small text-muted fw-bold">Nom du cours (ID: <?php echo $course['id']; ?>)</label>
                                        <input type="text" name="nom_cours" value="<?php echo htmlspecialchars($course['nom']); ?>" class="form-control fw-bold" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="small text-muted fw-bold">Description</label>
                                        <textarea name="description" class="form-control small" rows="2" required><?php echo htmlspecialchars($course['description']); ?></textarea>
                                    </div>
                                    <div class="d-flex justify-content-end gap-2">
                                        <button type="submit" name="edit_course" class="btn btn-sm btn-outline-secondary px-3">Mettre à jour</button>
                                        <button type="submit" name="delete_course" class="btn btn-sm btn-danger px-3" onclick="return confirm('Attention ! Supprimer ce cours effacera également les notes et plannings associés.');">Supprimer</button>
                                    </div>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted">
                            <p class="mb-0">Vous n'avez pas encore publié de cours. Utilisez le volet latéral gauche pour commencer.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>