<?php
// routes/routes.php

require_once __DIR__ . '/../app/controllers/AuthController.php';
require_once __DIR__ . '/../app/controllers/StudentController.php';
require_once __DIR__ . '/../app/controllers/TeacherController.php';
require_once __DIR__ . '/../app/controllers/HODController.php';
require_once __DIR__ . '/../app/controllers/HeadTeacherController.php';
require_once __DIR__ . '/../app/controllers/ExamController.php';
require_once __DIR__ . '/../app/controllers/DisciplinaryController.php';
require_once __DIR__ . '/../app/controllers/DepartmentController.php';
require_once __DIR__ . '/../app/controllers/AttendanceController.php';

// Route map: [METHOD][PATH] = [ControllerClass, method]
$ROUTES = [
    'GET' => [
        // Root health-check
       '/' => [AuthController::class, 'me'],


        // ---- Auth ----                            
        '/app/auth/me' => [AuthController::class, 'me'],

        // ---- Students ----
        '/app/students' => [StudentController::class, 'index'],
        '/app/students/show' => [StudentController::class, 'show'],
        '/app/students/attendance' => [StudentController::class, 'attendance'],
        '/app/students/grades' => [StudentController::class, 'grades'],

        // ---- HOD ----
        '/app/hod/dashboard' => [HODController::class, 'dashboard'],
        '/app/hod/teachers' => [HODController::class, 'teachers'],
        '/app/hod/teacher-classes' => [HODController::class, 'teacherClasses'],
        '/app/hod/teacher-subjects' => [HODController::class, 'teacherSubjects'],

        // ---- Head Teacher ----
        '/app/head/dashboard' => [HeadTeacherController::class, 'dashboard'],
        '/app/head/teachers' => [HeadTeacherController::class, 'teachers'],
        '/app/head/forms' => [HeadTeacherController::class, 'forms'],
        '/app/head/classes' => [HeadTeacherController::class, 'classes'],
        '/app/head/subjects' => [HeadTeacherController::class, 'subjects'],
        '/app/head/exams' => [HeadTeacherController::class, 'exams'],
        '/app/head/exams/subjects' => [HeadTeacherController::class, 'examSubjects'],

        // ---- Exams (general) ----
        '/app/exams' => [ExamController::class, 'index'],
        '/app/exams/show' => [ExamController::class, 'show'],
        '/app/exams/subjects' => [ExamController::class, 'subjects'],
        '/app/exams/grades' => [ExamController::class, 'grades'],

        // ---- Discipline ----
        '/app/discipline/types' => [DisciplinaryController::class, 'types'],
        '/app/discipline/actions' => [DisciplinaryController::class, 'actions'],
        '/app/discipline/student' => [DisciplinaryController::class, 'byStudent'],
        '/app/discipline/list' => [DisciplinaryController::class, 'list'],

        // ---- Departments ----
        '/app/departments' => [DepartmentController::class, 'index'],
        '/app/departments/show' => [DepartmentController::class, 'show'],
        '/app/departments/teachers' => [DepartmentController::class, 'teachers'],

        // ---- Attendance ----
        '/app/attendance/class' => [AttendanceController::class, 'classByDate'],
        '/app/attendance/student' => [AttendanceController::class, 'byStudent'],
        '/app/attendance/summary' => [AttendanceController::class, 'summary'],
    ],

    'POST' => [
        // ---- Auth ----
        '/app/auth/login' => [AuthController::class, 'login'],
        '/app/auth/logout' => [AuthController::class, 'logout'],
        '/app/auth/set-context' => [AuthController::class, 'setContext'],

        // ---- Students (Head Teacher only) ----
        '/app/students/create' => [StudentController::class, 'create'],
        '/app/students/update' => [StudentController::class, 'update'],
        '/app/students/delete' => [StudentController::class, 'delete'],

        // ---- HOD Assignments ----
        '/app/hod/assign-class' => [HODController::class, 'assignClass'],
        '/app/hod/assign-subject' => [HODController::class, 'assignSubject'],

        // ---- Head Teacher Teachers/Assignments ----
        '/app/head/teachers/create' => [HeadTeacherController::class, 'createTeacher'],
        '/app/head/teachers/update' => [HeadTeacherController::class, 'updateTeacher'],
        '/app/head/teachers/delete' => [HeadTeacherController::class, 'deleteTeacher'],
        '/app/head/assign-class' => [HeadTeacherController::class, 'assignClass'],
        '/app/head/assign-subject' => [HeadTeacherController::class, 'assignSubject'],
        '/app/head/classes/create' => [HeadTeacherController::class, 'createClass'],
        '/app/head/exams/create' => [HeadTeacherController::class, 'createExam'],

        // ---- Exams ----
        '/app/exams/create' => [ExamController::class, 'create'],
        '/app/exams/delete' => [ExamController::class, 'delete'],
        '/app/exams/enter-grade' => [ExamController::class, 'enterGrade'],

        // ---- Discipline ----
        '/app/discipline/create' => [DisciplinaryController::class, 'create'],
        '/app/discipline/update' => [DisciplinaryController::class, 'update'],
        '/app/discipline/delete' => [DisciplinaryController::class, 'delete'],

        // ---- Departments ----
        '/app/departments/create' => [DepartmentController::class, 'create'],
        '/app/departments/update' => [DepartmentController::class, 'update'],
        '/app/departments/delete' => [DepartmentController::class, 'delete'],
        '/app/departments/set-hod' => [DepartmentController::class, 'setHod'],

        // ---- Attendance ----
        '/app/attendance/mark' => [AttendanceController::class, 'mark'],
    ],
];

// Dispatcher
function dispatch(array $ROUTES): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

    // Your app is accessed as: http://localhost/phalombe_srms/public/...
    $base = '/phalombe_srms/public';
    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base));
        if ($path === '') $path = '/';
    }

    $map = $ROUTES[$method] ?? null;
    if (!$map || !isset($map[$path])) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Route not found', 'path' => $path, 'method' => $method]);
        exit;
    }

    [$class, $action] = $map[$path];

    if (!class_exists($class)) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => "Controller not found: {$class}"]);
        exit;
    }

    $controller = new $class();

    if (!method_exists($controller, $action)) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => "Method not found: {$class}::{$action}"]);
        exit;
    }

    $controller->$action();
}
