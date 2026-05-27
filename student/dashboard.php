<?php

session_start();

require_once "../config/database.php";
require_once "../includes/navbar.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "student") {

    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION["user_id"];

$stmtCourses = $pdo->prepare("
    SELECT c.nom, c.description

    FROM courses c

    JOIN enrollments e
    ON c.id = e.course_id

    WHERE e.student_id = ?
");

$stmtCourses->execute([$student_id]);

$my_courses = $stmtCourses->fetchAll();

$stmtGrades = $pdo->prepare("
    SELECT c.nom as course_nom, g.grade

    FROM grades g

    JOIN courses c
    ON g.course_id = c.id

    WHERE g.student_id = ?
");

$stmtGrades->execute([$student_id]);

$my_grades = $stmtGrades->fetchAll();

$stmtSchedules = $pdo->prepare("
    SELECT
        c.nom as course_nom,
        s.jour,
        s.heure_debut,
        s.heure_fin,
        s.salle

    FROM schedules s

    JOIN courses c
    ON s.course_id = c.id

    JOIN enrollments e
    ON c.id = e.course_id

    WHERE e.student_id = ?

    ORDER BY s.jour, s.heure_debut
");

$stmtSchedules->execute([$student_id]);

$my_schedule = $stmtSchedules->fetchAll();

?>

<!DOCTYPE html>
<html lang="fr">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>SmartCampus - Student</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body style="background: #f4f6f9;">

<div class="container mb-5">

    <h1 class="fw-bold mb-4">

        Dashboard Étudiant

    </h1>

    <div class="row mb-4">


        <div class="col-lg-4 mb-4">

            <div class="card shadow border-0 rounded-4 h-100">

                <div class="card-header bg-white py-3 fw-bold">

                    📚 Mes cours

                </div>

                <div class="card-body">

                    <?php if(count($my_courses) > 0): ?>

                        <?php foreach($my_courses as $course): ?>

                            <div class="mb-3 border-bottom pb-2">

                                <h6 class="fw-bold">

                                    <?php echo htmlspecialchars($course['nom']); ?>

                                </h6>

                                <p class="small text-muted mb-0">

                                    <?php echo htmlspecialchars($course['description']); ?>

                                </p>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <p class="text-muted">

                            Aucun cours disponible.

                        </p>

                    <?php endif; ?>

                </div>

            </div>

        </div>

        <div class="col-lg-4 mb-4">

            <div class="card shadow border-0 rounded-4 h-100">

                <div class="card-header bg-white py-3 fw-bold">

                     Mes notes

                </div>

                <div class="card-body">

                    <?php if(count($my_grades) > 0): ?>

                        <?php foreach($my_grades as $grade): ?>

                            <div class="d-flex justify-content-between border-bottom py-2">

                                <span class="fw-bold">

                                    <?php echo htmlspecialchars($grade['course_nom']); ?>

                                </span>

                                <span class="badge bg-primary px-3 py-2">

                                    <?php echo htmlspecialchars($grade['grade']); ?>/20

                                </span>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <p class="text-muted">

                            Aucune note disponible.

                        </p>

                    <?php endif; ?>

                </div>

            </div>

        </div>


        <div class="col-lg-4 mb-4">

            <div class="card shadow border-0 rounded-4 h-100">

                <div class="card-header bg-white py-3 fw-bold">

                    📅 Emploi du temps

                </div>

                <div class="card-body">

                    <?php if(count($my_schedule) > 0): ?>

                        <?php foreach($my_schedule as $schedule): ?>

                            <div class="border-bottom py-2">

                                <h6 class="fw-bold mb-1">

                                    <?php echo htmlspecialchars($schedule['course_nom']); ?>

                                </h6>

                                <p class="small mb-1">

                                    <?php echo htmlspecialchars($schedule['jour']); ?>

                                    •

                                    <?php echo htmlspecialchars($schedule['heure_debut']); ?>

                                    -

                                    <?php echo htmlspecialchars($schedule['heure_fin']); ?>

                                </p>

                                <p class="small text-muted mb-0">

                                    Salle :
                                    <?php echo htmlspecialchars($schedule['salle']); ?>

                                </p>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <p class="text-muted">

                            Aucun planning disponible.

                        </p>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>