<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "teacher") {
    header("Location: ../login.php");
    exit();
}

$teacher_id = $_SESSION["user_id"];
$message = "";
$status = "success";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_course'])) {
    $nom = trim($_POST['nom_cours']);
    $description = trim($_POST['description']);

    if (!empty($nom) && !empty($description)) {
        $stmt = $pdo->prepare("
            INSERT INTO courses (nom, description, teacher_id)
            VALUES (?, ?, ?)
        ");
        if ($stmt->execute([$nom, $description, $teacher_id])) {
            $message = "Cours ajouté avec succès.";
        }
    } else {
        $message = "Veuillez remplir tous les champs.";
        $status = "danger";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_course'])) {
    $course_id = intval($_POST['course_id']);
    $nom = trim($_POST['nom_cours']);
    $description = trim($_POST['description']);

    if (!empty($nom) && !empty($description)) {
        $stmt = $pdo->prepare("
            UPDATE courses
            SET nom = ?, description = ?
            WHERE id = ? AND teacher_id = ?
        ");
        if ($stmt->execute([$nom, $description, $course_id, $teacher_id])) {
            $message = "Cours modifié avec succès.";
        }
    } else {
        $message = "Veuillez remplir tous les champs.";
        $status = "danger";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_course'])) {
    $course_id = intval($_POST['course_id']);
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM schedules WHERE course_id = ?")->execute([$course_id]);
        $pdo->prepare("DELETE FROM enrollments WHERE course_id = ?")->execute([$course_id]);
        $pdo->prepare("DELETE FROM grades WHERE course_id = ?")->execute([$course_id]);
        $pdo->prepare("
            DELETE FROM courses
            WHERE id = ? AND teacher_id = ?
        ")->execute([$course_id, $teacher_id]);
        $pdo->commit();
        $message = "Cours supprimé avec succès.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Erreur : " . $e->getMessage();
        $status = "danger";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_grade'])) {
    $student_id = intval($_POST['student_id']);
    $course_id = intval($_POST['course_id']);
    $grade = floatval($_POST['grade']);

    if ($grade >= 0 && $grade <= 20) {
        $stmtCheckLock = $pdo->prepare("SELECT is_locked FROM grades WHERE student_id = ? AND course_id = ?");
        $stmtCheckLock->execute([$student_id, $course_id]);
        $lockStatus = $stmtCheckLock->fetch();

        if ($lockStatus && $lockStatus['is_locked'] == 1) {
            $message = "Opération impossible : Les notes de ce cours ont été verrouillées définitivement.";
            $status = "danger";
        } else {
            $stmtCheckExist = $pdo->prepare("SELECT id FROM grades WHERE student_id = ? AND course_id = ?");
            $stmtCheckExist->execute([$student_id, $course_id]);

            if ($stmtCheckExist->rowCount() > 0) {
                $stmt = $pdo->prepare("UPDATE grades SET grade = ? WHERE student_id = ? AND course_id = ?");
                $executed = $stmt->execute([$grade, $student_id, $course_id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO grades (student_id, course_id, grade, is_locked) VALUES (?, ?, ?, 0)");
                $executed = $stmt->execute([$student_id, $course_id, $grade]);
            }

            if ($executed) {
                $message = "Note enregistrée avec succès.";
            } else {
                $message = "Erreur lors de l'enregistrement de la note.";
                $status = "danger";
            }
        }
    } else {
        $message = "La note doit être comprise entre 0 et 20.";
        $status = "danger";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['lock_grades'])) {
    $course_id = intval($_POST['course_id']);
    
    $stmtLock = $pdo->prepare("UPDATE grades SET is_locked = 1 WHERE course_id = ?");
    if ($stmtLock->execute([$course_id])) {
        $message = "Les notes de ce cours ont été verrouillées avec succès.";
        $status = "success";
    } else {
        $message = "Erreur lors du verrouillage des notes.";
        $status = "danger";
    }
}

$stmtCourses = $pdo->prepare("
    SELECT *
    FROM courses
    WHERE teacher_id = ?
");
$stmtCourses->execute([$teacher_id]);
$my_courses = $stmtCourses->fetchAll();

$stmtStudents = $pdo->prepare("
    SELECT
        u.id as student_id,
        u.nom,
        u.prenom,
        c.id as course_id,
        c.nom as course_nom,
        g.grade,
        g.is_locked
    FROM users u
    JOIN enrollments e ON u.id = e.student_id
    JOIN courses c ON e.course_id = c.id
    LEFT JOIN grades g ON (u.id = g.student_id AND c.id = g.course_id)
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
    <title>SmartCampus - Teacher</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background: #f4f6f9;">

<?php require_once "../includes/navbar.php"; ?>

<div class="container mt-4 mb-5">
    <h1 class="fw-bold mb-4">Dashboard Enseignant</h1>

    <?php if ($message != ""): ?>
        <div class="alert alert-<?php echo $status; ?> alert-dismissible fade show">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-12 col-lg-4 mb-4">
            
            <div class="card shadow border-0 rounded-4 mb-4">
                <div class="card-header bg-white fw-bold py-3">Ajouter un cours</div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Nom du cours</label>
                            <input type="text" name="nom_cours" class="form-control" maxlength="100" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3" required></textarea>
                        </div>
                        <button type="submit" name="add_course" class="btn btn-primary w-100 fw-bold">Ajouter</button>
                    </form>
                </div>
            </div>

            <div class="card shadow border-0 rounded-4 mb-4">
                <div class="card-header bg-white fw-bold py-3">Ajouter / Modifier une note</div>
                <div class="card-body">
                    <?php if(count($enrolled_students) > 0): ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Étudiant</label>
                            <select class="form-select" onchange="
                                let values = this.value.split('|');
                                document.getElementById('student_id_input').value = values[0];
                                document.getElementById('course_id_input').value = values[1];
                            " required>
                                <option value="">Sélectionner</option>
                                <?php foreach($enrolled_students as $est): ?>
                                    <?php if ($est['is_locked'] != 1): ?>
                                        <option value="<?php echo $est['student_id'] . '|' . $est['course_id']; ?>">
                                            <?php echo htmlspecialchars($est['nom'] . ' ' . $est['prenom'] . ' (' . $est['course_nom'] . ')'); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="student_id" id="student_id_input">
                            <input type="hidden" name="course_id" id="course_id_input">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Note</label>
                            <input type="number" step="0.25" min="0" max="20" name="grade" class="form-control" required>
                        </div>
                        <button type="submit" name="add_grade" class="btn btn-success w-100 fw-bold">Enregistrer la note</button>
                    </form>
                    <?php else: ?>
                        <p class="text-muted small mb-0">Aucun étudiant inscrit.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow border-0 rounded-4 mb-4">
                <div class="card-header bg-white fw-bold text-danger py-3">Verrouiller un cours</div>
                <div class="card-body">
                    <?php if(count($my_courses) > 0): ?>
                    <form method="POST" onsubmit="return confirm('ATTENTION : Le verrouillage est définitif. Vous ne pourrez plus modifier les notes de ce cours.');">
                        <div class="mb-3">
                            <label class="form-label">Cours à clôturer</label>
                            <select name="course_id" class="form-select" required>
                                <option value="">Sélectionner</option>
                                <?php foreach($my_courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>">
                                        <?php echo htmlspecialchars($course['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="lock_grades" class="btn btn-danger w-100 fw-bold">Verrouiller définitivement</button>
                    </form>
                    <?php else: ?>
                        <p class="text-muted small mb-0">Aucun cours disponible.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow border-0 rounded-4">
                <div class="card-header bg-white fw-bold py-3">Ajouter planning</div>
                <div class="card-body">
                    <?php if(count($my_courses) > 0): ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Cours</label>
                            <select name="course_id" class="form-select" required>
                                <?php foreach($my_courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>">
                                        <?php echo htmlspecialchars($course['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Jour</label>
                            <select name="jour" class="form-select" required>
                                <option>Lundi</option>
                                <option>Mardi</option>
                                <option>Mercredi</option>
                                <option>Jeudi</option>
                                <option>Vendredi</option>
                            </select>
                        </div>
                        <div class="row mb-3">
                            <div class="col">
                                <input type="time" name="heure_debut" class="form-control" required>
                            </div>
                            <div class="col">
                                <input type="time" name="heure_fin" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <input type="text" name="salle" class="form-control" placeholder="Salle" maxlength="100" required>
                        </div>
                        <button type="submit" name="add_schedule" class="btn btn-warning w-100 fw-bold">Ajouter planning</button>
                    </form>
                    <?php else: ?>
                        <p class="text-muted small mb-0">Aucun cours créé.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-8">
            <div class="card shadow border-0 rounded-4 mb-4">
                <div class="card-header bg-white py-3">
                    <h3 class="fw-bold mb-0">Suivi des notes par étudiant</h3>
                </div>
                <div class="card-body p-0">
                    <?php if(count($enrolled_students) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">Étudiant</th>
                                        <th>Cours</th>
                                        <th>Note</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($enrolled_students as $est): ?>
                                        <tr>
                                            <td class="ps-4 fw-bold"><?php echo htmlspecialchars($est['prenom'] . ' ' . $est['nom']); ?></td>
                                            <td><?php echo htmlspecialchars($est['course_nom']); ?></td>
                                            <td class="fw-bold">
                                                <?php echo ($est['grade'] !== null) ? htmlspecialchars($est['grade']) . ' / 20' : '<span class="text-muted small fw-normal">Non noté</span>'; ?>
                                            </td>
                                            <td>
                                                <?php if($est['is_locked'] == 1): ?>
                                                    <span class="badge bg-danger">Verrouillé</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Modifiable</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted">Aucun étudiant inscrit.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow border-0 rounded-4">
                <div class="card-header bg-white py-3">
                    <h3 class="fw-bold mb-0">Mes cours</h3>
                </div>
                <div class="card-body">
                    <?php if(count($my_courses) > 0): ?>
                        <?php foreach($my_courses as $course): ?>
                            <div class="border rounded-4 p-4 mb-3 bg-white shadow-sm">
                                <form method="POST">
                                    <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                    <div class="mb-3">
                                        <label class="small fw-bold text-muted">Nom du cours</label>
                                        <input type="text" name="nom_cours" value="<?php echo htmlspecialchars($course['nom']); ?>" class="form-control fw-bold" maxlength="100" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="small fw-bold text-muted">Description</label>
                                        <textarea name="description" class="form-control" rows="3" required><?php echo htmlspecialchars($course['description']); ?></textarea>
                                    </div>
                                    <div class="d-flex justify-content-end gap-2">
                                        <button type="submit" name="edit_course" class="btn btn-outline-secondary btn-sm px-3 fw-bold">Modifier</button>
                                        <button type="submit" name="delete_course" class="btn btn-outline-danger btn-sm px-3 fw-bold" onclick="return confirm('Supprimer ce cours ?');">Supprimer</button>
                                    </div>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">Aucun cours publié.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>