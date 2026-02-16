<?php
require_once __DIR__ . '/BaseController.php';

class DepartmentController extends BaseController
{
    // =========================================================
    // GET /api/departments
    // head_teacher: all
    // hod/teacher: read-only list (useful for UI)
    // =========================================================
    public function index(): void
    {
        $this->requireAuth();

        $pdo = $this->pdo();
        $stmt = $pdo->query("
            SELECT d.*,
                   ht.firstname AS hod_firstname,
                   ht.lastname  AS hod_lastname
            FROM departments d
            LEFT JOIN teachers ht ON ht.id = d.hod_id
            ORDER BY d.name ASC
        ");
        $this->json(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // =========================================================
    // GET /api/departments/show?id=1
    // any authenticated
    // =========================================================
    public function show(): void
    {
        $this->requireAuth();

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) $this->json(['ok' => false, 'error' => 'Invalid id'], 400);

        $pdo = $this->pdo();
        $stmt = $pdo->prepare("
            SELECT d.*,
                   ht.firstname AS hod_firstname,
                   ht.lastname  AS hod_lastname
            FROM departments d
            LEFT JOIN teachers ht ON ht.id = d.hod_id
            WHERE d.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) $this->json(['ok' => false, 'error' => 'Department not found'], 404);
        $this->json(['ok' => true, 'data' => $row]);
    }

    // =========================================================
    // POST /api/departments/create
    // head_teacher only
    // body: name
    // =========================================================
    public function create(): void
    {
        $this->requireRole(['head_teacher']);

        $data = $this->request();
        $name = trim((string)($data['name'] ?? ''));

        if ($name === '') {
            $this->json(['ok' => false, 'error' => 'name is required'], 400);
        }

        $pdo = $this->pdo();

        // uniqueness check (table has UNIQUE KEY name)
        $stmt = $pdo->prepare("SELECT 1 FROM departments WHERE name = :n LIMIT 1");
        $stmt->execute([':n' => $name]);
        if ($stmt->fetchColumn()) {
            $this->json(['ok' => false, 'error' => 'Department name already exists'], 409);
        }

        $stmt = $pdo->prepare("INSERT INTO departments (name) VALUES (:name)");
        $ok = $stmt->execute([':name' => $name]);

        if ($ok) {
            $this->json(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
        }

        $this->json(['ok' => false, 'error' => 'Failed to create department'], 500);
    }

    // =========================================================
    // POST /api/departments/update
    // head_teacher only
    // body: id, name
    // =========================================================
    public function update(): void
    {
        $this->requireRole(['head_teacher']);

        $data = $this->request();
        $id   = (int)($data['id'] ?? 0);
        $name = trim((string)($data['name'] ?? ''));

        if ($id <= 0 || $name === '') {
            $this->json(['ok' => false, 'error' => 'id and name are required'], 400);
        }

        $pdo = $this->pdo();

        // make sure department exists
        $stmt = $pdo->prepare("SELECT 1 FROM departments WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        if (!$stmt->fetchColumn()) {
            $this->json(['ok' => false, 'error' => 'Department not found'], 404);
        }

        // uniqueness check for other departments
        $stmt = $pdo->prepare("SELECT 1 FROM departments WHERE name = :n AND id <> :id LIMIT 1");
        $stmt->execute([':n' => $name, ':id' => $id]);
        if ($stmt->fetchColumn()) {
            $this->json(['ok' => false, 'error' => 'Department name already exists'], 409);
        }

        $stmt = $pdo->prepare("UPDATE departments SET name = :name WHERE id = :id");
        $ok = $stmt->execute([':name' => $name, ':id' => $id]);

        if ($ok) {
            $this->json(['ok' => true]);
        }

        $this->json(['ok' => false, 'error' => 'Failed to update department'], 500);
    }

    // =========================================================
    // POST /api/departments/delete
    // head_teacher only
    // body: id
    //
    // Note:
    // - FK departments.hod_id -> teachers.id is SET NULL so safe.
    // - Teachers referencing department_id will become NULL if you set FK to SET NULL (yours is SET NULL)
    // =========================================================
    public function delete(): void
    {
        $this->requireRole(['head_teacher']);

        $data = $this->request();
        $id = (int)($data['id'] ?? 0);

        if ($id <= 0) {
            $this->json(['ok' => false, 'error' => 'Invalid id'], 400);
        }

        $pdo = $this->pdo();

        $stmt = $pdo->prepare("DELETE FROM departments WHERE id = :id");
        $ok = $stmt->execute([':id' => $id]);

        if ($ok) {
            $this->json(['ok' => true]);
        }

        $this->json(['ok' => false, 'error' => 'Failed to delete department'], 500);
    }

    // =========================================================
    // POST /api/departments/set-hod
    // head_teacher only
    // body: department_id, hod_id (teacher id)
    //
    // Rules:
    // - hod_id must be a teacher that belongs to the same department (recommended)
    // - also update teachers.role to 'hod' (optional but common)
    // =========================================================
    public function setHod(): void
    {
        $this->requireRole(['head_teacher']);

        $data = $this->request();
        $departmentId = (int)($data['department_id'] ?? 0);
        $hodId        = (int)($data['hod_id'] ?? 0);

        if ($departmentId <= 0 || $hodId <= 0) {
            $this->json(['ok' => false, 'error' => 'department_id and hod_id are required'], 400);
        }

        $pdo = $this->pdo();

        // Ensure department exists
        $stmt = $pdo->prepare("SELECT 1 FROM departments WHERE id=:id LIMIT 1");
        $stmt->execute([':id' => $departmentId]);
        if (!$stmt->fetchColumn()) {
            $this->json(['ok' => false, 'error' => 'Department not found'], 404);
        }

        // Ensure teacher exists + belongs to department (strict SoD)
        $stmt = $pdo->prepare("SELECT department_id FROM teachers WHERE id=:id LIMIT 1");
        $stmt->execute([':id' => $hodId]);
        $teacherDept = $stmt->fetchColumn();
        if ($teacherDept === false) {
            $this->json(['ok' => false, 'error' => 'Teacher not found'], 404);
        }
        if ((int)$teacherDept !== $departmentId) {
            $this->json(['ok' => false, 'error' => 'Teacher must belong to the same department'], 400);
        }

        // Update department.hod_id
        $stmt = $pdo->prepare("UPDATE departments SET hod_id=:hod WHERE id=:dept");
        $ok = $stmt->execute([':hod' => $hodId, ':dept' => $departmentId]);

        if (!$ok) {
            $this->json(['ok' => false, 'error' => 'Failed to set HOD'], 500);
        }

        // Optional: set teacher role to hod
        $stmt = $pdo->prepare("UPDATE teachers SET role='hod' WHERE id=:id");
        $stmt->execute([':id' => $hodId]);

        $this->json(['ok' => true]);
    }

    // =========================================================
    // GET /api/departments/teachers?department_id=1
    // Any authenticated
    // =========================================================
    public function teachers(): void
    {
        $this->requireAuth();

        $departmentId = (int)($_GET['department_id'] ?? 0);
        if ($departmentId <= 0) {
            $this->json(['ok' => false, 'error' => 'department_id is required'], 400);
        }

        $pdo = $this->pdo();
        $stmt = $pdo->prepare("
            SELECT id, teacher_code, firstname, lastname, role, contact_no, department_id
            FROM teachers
            WHERE department_id = :dept
            ORDER BY lastname ASC, firstname ASC
        ");
        $stmt->execute([':dept' => $departmentId]);

        $this->json(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }
}
