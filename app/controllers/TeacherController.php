<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Teacher.php';

class TeacherController extends BaseController
{
    // =========================
    // GET /api/teachers
    // head_teacher: all teachers
    // hod: teachers in their department
    // teacher: self only
    // =========================
    public function index(): void
    {
        $this->requireAuth();

        $pdo  = $this->pdo();
        $role = $this->role();
        $uid  = $this->userId();

        if ($role === 'head_teacher') {
            $stmt = $pdo->query("
                SELECT t.*, d.name AS department_name
                FROM teachers t
                LEFT JOIN departments d ON t.department_id = d.id
                ORDER BY t.lastname ASC
            ");
            $this->json(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }

        if ($role === 'hod') {
            $deptId = $this->hodDepartmentId($uid);
            if (!$deptId) $this->json(['ok' => true, 'data' => []]);

            $stmt = $pdo->prepare("
                SELECT t.*, d.name AS department_name
                FROM teachers t
                LEFT JOIN departments d ON t.department_id = d.id
                WHERE t.department_id = :dept
                ORDER BY t.lastname ASC
            ");
            $stmt->execute([':dept' => $deptId]);
            $this->json(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }

        // teacher
        $stmt = $pdo->prepare("
            SELECT t.*, d.name AS department_name
            FROM teachers t
            LEFT JOIN departments d ON t.department_id = d.id
            WHERE t.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $uid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->json(['ok' => true, 'data' => $row ? [$row] : []]);
    }

    // =========================
    // GET /api/teachers/show?id=1
    // head_teacher: any
    // hod: dept only
    // teacher: self only
    // =========================
    public function show(): void
    {
        $this->requireAuth();

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) $this->json(['ok' => false, 'error' => 'Invalid id'], 400);

        $pdo  = $this->pdo();
        $role = $this->role();
        $uid  = $this->userId();

        if ($role === 'teacher' && $id !== $uid) {
            $this->json(['ok' => false, 'error' => 'Forbidden'], 403);
        }

        if ($role === 'hod') {
            $deptId = $this->hodDepartmentId($uid);
            $stmt = $pdo->prepare("SELECT 1 FROM teachers WHERE id=:id AND department_id=:dept LIMIT 1");
            $stmt->execute([':id' => $id, ':dept' => $deptId]);
            if (!$stmt->fetchColumn()) $this->json(['ok' => false, 'error' => 'Forbidden'], 403);
        }

        $stmt = $pdo->prepare("
            SELECT t.*, d.name AS department_name
            FROM teachers t
            LEFT JOIN departments d ON t.department_id = d.id
            WHERE t.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) $this->json(['ok' => false, 'error' => 'Teacher not found'], 404);

        $this->json(['ok' => true, 'data' => $row]);
    }

    // =========================
    // POST /api/teachers/create
    // head_teacher only
    // =========================
    public function create(): void
    {
        $this->requireRole(['head_teacher']);

        $data = $this->request();

        $m = new Teacher($this->userId(), $this->yearId(), $this->termId());
        $m->teacher_code  = $data['teacher_code'] ?? null;
        $m->firstname     = trim((string)($data['firstname'] ?? ''));
        $m->lastname      = trim((string)($data['lastname'] ?? ''));
        $m->contact_no    = $data['contact_no'] ?? null;
        $m->role          = $data['role'] ?? 'teacher'; // teacher|hod|head_teacher
        $m->department_id = isset($data['department_id']) && $data['department_id'] !== '' ? (int)$data['department_id'] : null;

        if ($m->firstname === '' || $m->lastname === '') {
            $this->json(['ok' => false, 'error' => 'firstname and lastname are required'], 400);
        }

        if ($m->create()) {
            $this->json(['ok' => true, 'id' => (int)$m->id]);
        }

        $this->json(['ok' => false, 'error' => 'Failed to create teacher'], 500);
    }

    // =========================
    // POST /api/teachers/update
    // head_teacher only
    // =========================
    public function update(): void
    {
        $this->requireRole(['head_teacher']);

        $data = $this->request();
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) $this->json(['ok' => false, 'error' => 'Invalid id'], 400);

        $m = new Teacher($this->userId(), $this->yearId(), $this->termId());
        $m->id           = $id;
        $m->teacher_code = $data['teacher_code'] ?? null;
        $m->firstname    = trim((string)($data['firstname'] ?? ''));
        $m->lastname     = trim((string)($data['lastname'] ?? ''));
        $m->contact_no   = $data['contact_no'] ?? null;
        $m->role         = $data['role'] ?? 'teacher';
        $m->department_id = isset($data['department_id']) && $data['department_id'] !== '' ? (int)$data['department_id'] : null;

        if ($m->firstname === '' || $m->lastname === '') {
            $this->json(['ok' => false, 'error' => 'firstname and lastname are required'], 400);
        }

        if ($m->update()) {
            $this->json(['ok' => true]);
        }

        $this->json(['ok' => false, 'error' => 'Failed to update teacher'], 500);
    }

    // =========================
    // POST /api/teachers/delete
    // head_teacher only
    // =========================
    public function delete(): void
    {
        $this->requireRole(['head_teacher']);

        $data = $this->request();
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) $this->json(['ok' => false, 'error' => 'Invalid id'], 400);

        $m = new Teacher($this->userId(), $this->yearId(), $this->termId());
        $m->id = $id;

        if ($m->delete()) {
            $this->json(['ok' => true]);
        }

        $this->json(['ok' => false, 'error' => 'Failed to delete teacher'], 500);
    }

    // =========================
    // GET /api/teachers/classes?teacher_id=1
    // teacher: self only
    // hod: only teachers in their dept
    // head_teacher: any
    // =========================
    public function classes(): void
    {
        $this->requireAuth();

        $teacherId = (int)($_GET['teacher_id'] ?? $this->userId());
        if ($teacherId <= 0) $this->json(['ok' => false, 'error' => 'Invalid teacher_id'], 400);

        $role = $this->role();
        $uid  = $this->userId();

        if ($role === 'teacher' && $teacherId !== $uid) {
            $this->json(['ok' => false, 'error' => 'Forbidden'], 403);
        }

        if ($role === 'hod' && $teacherId !== $uid) {
            $pdo = $this->pdo();
            $deptId = $this->hodDepartmentId($uid);
            $stmt = $pdo->prepare("SELECT 1 FROM teachers WHERE id=:tid AND department_id=:dept LIMIT 1");
            $stmt->execute([':tid' => $teacherId, ':dept' => $deptId]);
            if (!$stmt->fetchColumn()) $this->json(['ok' => false, 'error' => 'Forbidden'], 403);
        }

        $m = new Teacher($this->userId(), $this->yearId(), $this->termId());
        $m->id = $teacherId;

        // model returns JSON string
        $data = json_decode($m->getClasses(), true);
        $this->json(['ok' => true, 'data' => $data ?? []]);
    }

    // =========================
    // GET /api/teachers/subjects?teacher_id=1
    // teacher: self only
    // hod: only teachers in their dept
    // head_teacher: any
    // =========================
    public function subjects(): void
    {
        $this->requireAuth();

        $teacherId = (int)($_GET['teacher_id'] ?? $this->userId());
        if ($teacherId <= 0) $this->json(['ok' => false, 'error' => 'Invalid teacher_id'], 400);

        $role = $this->role();
        $uid  = $this->userId();

        if ($role === 'teacher' && $teacherId !== $uid) {
            $this->json(['ok' => false, 'error' => 'Forbidden'], 403);
        }

        if ($role === 'hod' && $teacherId !== $uid) {
            $pdo = $this->pdo();
            $deptId = $this->hodDepartmentId($uid);
            $stmt = $pdo->prepare("SELECT 1 FROM teachers WHERE id=:tid AND department_id=:dept LIMIT 1");
            $stmt->execute([':tid' => $teacherId, ':dept' => $deptId]);
            if (!$stmt->fetchColumn()) $this->json(['ok' => false, 'error' => 'Forbidden'], 403);
        }

        $m = new Teacher($this->userId(), $this->yearId(), $this->termId());
        $m->id = $teacherId;

        $data = json_decode($m->getSubjects(), true);
        $this->json(['ok' => true, 'data' => $data ?? []]);
    }

    // =========================
    // POST /api/teachers/assign-class
    // head_teacher OR hod (hod limited to their department)
    // body: teacher_id, class_id
    // =========================
    public function assignClass(): void
    {
        $this->requireRole(['head_teacher', 'hod']);

        $data = $this->request();
        $teacherId = (int)($data['teacher_id'] ?? 0);
        $classId   = (int)($data['class_id'] ?? 0);

        if ($teacherId <= 0 || $classId <= 0) {
            $this->json(['ok' => false, 'error' => 'teacher_id and class_id are required'], 400);
        }

        if ($this->role() === 'hod') {
            $pdo = $this->pdo();
            $deptId = $this->hodDepartmentId($this->userId());
            if (!$deptId) $this->json(['ok' => false, 'error' => 'HOD department not found'], 403);

            $stmt = $pdo->prepare("SELECT 1 FROM teachers WHERE id=:tid AND department_id=:dept LIMIT 1");
            $stmt->execute([':tid' => $teacherId, ':dept' => $deptId]);
            if (!$stmt->fetchColumn()) {
                $this->json(['ok' => false, 'error' => 'Forbidden'], 403);
            }
        }

        $m = new Teacher($this->userId(), $this->yearId(), $this->termId());
        $m->id = $teacherId;

        if ($m->assignClass($classId)) {
            $this->json(['ok' => true]);
        }

        $this->json(['ok' => false, 'error' => 'Failed to assign class'], 500);
    }

    // =========================
    // POST /api/teachers/assign-subject
    // head_teacher OR hod (hod limited to their department)
    // body: teacher_id, subject_id
    // =========================
    public function assignSubject(): void
    {
        $this->requireRole(['head_teacher', 'hod']);

        $data = $this->request();
        $teacherId = (int)($data['teacher_id'] ?? 0);
        $subjectId = (int)($data['subject_id'] ?? 0);

        if ($teacherId <= 0 || $subjectId <= 0) {
            $this->json(['ok' => false, 'error' => 'teacher_id and subject_id are required'], 400);
        }

        if ($this->role() === 'hod') {
            $pdo = $this->pdo();
            $deptId = $this->hodDepartmentId($this->userId());
            if (!$deptId) $this->json(['ok' => false, 'error' => 'HOD department not found'], 403);

            $stmt = $pdo->prepare("SELECT 1 FROM teachers WHERE id=:tid AND department_id=:dept LIMIT 1");
            $stmt->execute([':tid' => $teacherId, ':dept' => $deptId]);
            if (!$stmt->fetchColumn()) {
                $this->json(['ok' => false, 'error' => 'Forbidden'], 403);
            }
        }

        $m = new Teacher($this->userId(), $this->yearId(), $this->termId());
        $m->id = $teacherId;

        if ($m->assignSubject($subjectId)) {
            $this->json(['ok' => true]);
        }

        $this->json(['ok' => false, 'error' => 'Failed to assign subject'], 500);
    }

    // =========================
    // GET /api/teachers/dashboard
    // Any logged-in teacher/hod/head_teacher can call,
    // but it returns "my" data based on session user
    // =========================
    public function dashboard(): void
    {
        $this->requireAuth();

        $pdo = $this->pdo();
        $teacherId = $this->userId();
        $today = date('Y-m-d');

        // My classes
        $stmt = $pdo->prepare("
            SELECT c.id, c.name, c.form_id
            FROM teacher_classes tc
            JOIN classes c ON c.id = tc.class_id
            WHERE tc.teacher_id = :tid
            ORDER BY c.form_id ASC, c.name ASC
        ");
        $stmt->execute([':tid' => $teacherId]);
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // My subjects
        $stmt = $pdo->prepare("
            SELECT s.id, s.name
            FROM teacher_subjects ts
            JOIN subjects s ON s.id = ts.subject_id
            WHERE ts.teacher_id = :tid
            ORDER BY s.name ASC
        ");
        $stmt->execute([':tid' => $teacherId]);
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Attendance marked today per class (by this teacher)
        $attendanceToday = [];
        foreach ($classes as $c) {
            $st = $pdo->prepare("
                SELECT COUNT(*)
                FROM attendance
                WHERE class_id = :cid AND attendance_date = :dt AND marked_by = :tid
            ");
            $st->execute([':cid' => $c['id'], ':dt' => $today, ':tid' => $teacherId]);
            $count = (int)$st->fetchColumn();

            $attendanceToday[] = [
                'class_id' => (int)$c['id'],
                'class_name' => $c['name'],
                'marked' => $count > 0
            ];
        }

        $this->json([
            'ok' => true,
            'data' => [
                'date' => $today,
                'user' => [
                    'id' => $teacherId,
                    'role' => $this->role()
                ],
                'my_classes' => $classes,
                'my_subjects' => $subjects,
                'attendance_today' => $attendanceToday
            ]
        ]);
    }
}
