<?php
require_once __DIR__ . '/BaseController.php';

/**
 * DisciplinaryController
 *
 * NOTE:
 * Your DB schema uses:
 *  - disciplinary_records
 *  - disciplinary_types
 *  - disciplinary_actions
 *
 * This controller uses PDO directly (via BaseController->pdo()) to match that schema,
 * instead of the older "Discipline" model you pasted earlier (which targets discipline_cases).
 */
class DisciplinaryController extends BaseController
{
    // =========================================================
    // GET /api/discipline/types
    // all authenticated roles
    // =========================================================
    public function types(): void
    {
        $this->requireAuth();

        $pdo = $this->pdo();
        $stmt = $pdo->query("SELECT id, name, severity FROM disciplinary_types ORDER BY name ASC");
        $this->json(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // =========================================================
    // GET /api/discipline/actions
    // all authenticated roles
    // =========================================================
    public function actions(): void
    {
        $this->requireAuth();

        $pdo = $this->pdo();
        $stmt = $pdo->query("SELECT id, action_name, description FROM disciplinary_actions ORDER BY action_name ASC");
        $this->json(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // =========================================================
    // POST /api/discipline/create
    // teacher/hod/head_teacher
    //
    // Body:
    //  student_id, type_id, incident_date, reported_by(optional),
    //  action_id(optional), description(optional), follow_up(optional)
    //
    // SoD:
    //  - teacher: student must be in one of their assigned classes
    //  - hod: allow (admin-ish) but still require student in their "department scope" (optional)
    //  - head_teacher: allow any
    // =========================================================
    public function create(): void
    {
        $this->requireRole(['teacher', 'hod', 'head_teacher']);

        $data = $this->request();

        $studentId    = (int)($data['student_id'] ?? 0);
        $typeId       = (int)($data['type_id'] ?? 0);
        $incidentDate = trim((string)($data['incident_date'] ?? ''));
        $actionId     = isset($data['action_id']) && $data['action_id'] !== '' ? (int)$data['action_id'] : null;

        $description  = isset($data['description']) ? trim((string)$data['description']) : null;
        $followUp     = isset($data['follow_up']) ? trim((string)$data['follow_up']) : null;

        // reported_by: default to current teacher
        $reportedBy = isset($data['reported_by']) && $data['reported_by'] !== ''
            ? (int)$data['reported_by']
            : $this->userId();

        if ($studentId <= 0 || $typeId <= 0 || $incidentDate === '') {
            $this->json(['ok' => false, 'error' => 'student_id, type_id, incident_date are required'], 400);
        }

        $role = $this->role();
        $uid  = $this->userId();

        // Teacher scope check: student must be in one of their classes
        if ($role === 'teacher') {
            $classId = $this->studentClassId($studentId);
            if (!$classId || !$this->teacherHasClass($uid, $classId)) {
                $this->json(['ok' => false, 'error' => 'Forbidden: student not in your class'], 403);
            }
            // reported_by must be self for teachers
            $reportedBy = $uid;
        }

        // HOD scope check (reasonable): student must be in dept scope
        if ($role === 'hod') {
            $pdo = $this->pdo();
            $deptId = $this->hodDepartmentId($uid);
            if (!$deptId) $this->json(['ok' => false, 'error' => 'HOD department not found'], 403);

            $stmt = $pdo->prepare("
                SELECT 1
                FROM students s
                JOIN teacher_classes tc ON tc.class_id = s.class_id
                JOIN teachers t ON t.id = tc.teacher_id
                WHERE s.id = :sid AND t.department_id = :dept
                LIMIT 1
            ");
            $stmt->execute([':sid' => $studentId, ':dept' => $deptId]);
            if (!$stmt->fetchColumn()) {
                $this->json(['ok' => false, 'error' => 'Forbidden'], 403);
            }
            // reported_by default to self
            $reportedBy = $uid;
        }

        // head_teacher: allow any, but if they pass reported_by, ensure it's valid-ish
        // (we'll just keep it as provided or self)

        $pdo = $this->pdo();

        $stmt = $pdo->prepare("
            INSERT INTO disciplinary_records
            (student_id, type_id, action_id, reported_by, incident_date, description, follow_up)
            VALUES
            (:student_id, :type_id, :action_id, :reported_by, :incident_date, :description, :follow_up)
        ");

        $ok = $stmt->execute([
            ':student_id'    => $studentId,
            ':type_id'       => $typeId,
            ':action_id'     => $actionId,
            ':reported_by'   => $reportedBy,
            ':incident_date' => $incidentDate,
            ':description'   => $description,
            ':follow_up'     => $followUp
        ]);

        if (!$ok) {
            $this->json(['ok' => false, 'error' => 'Failed to create discipline record'], 500);
        }

        $this->json(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
    }

    // =========================================================
    // POST /api/discipline/update
    // teacher: only if they reported it
    // hod: dept scope
    // head_teacher: any
    //
    // Body: id, type_id(optional), action_id(optional), incident_date(optional),
    //       description(optional), follow_up(optional)
    // =========================================================
    public function update(): void
    {
        $this->requireRole(['teacher', 'hod', 'head_teacher']);

        $data = $this->request();
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) $this->json(['ok' => false, 'error' => 'Invalid id'], 400);

        $pdo  = $this->pdo();
        $role = $this->role();
        $uid  = $this->userId();

        // Fetch existing for permissions
        $stmt = $pdo->prepare("SELECT * FROM disciplinary_records WHERE id=:id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) $this->json(['ok' => false, 'error' => 'Record not found'], 404);

        if ($role === 'teacher') {
            if ((int)$existing['reported_by'] !== $uid) {
                $this->json(['ok' => false, 'error' => 'Forbidden'], 403);
            }
        }

        if ($role === 'hod') {
            $deptId = $this->hodDepartmentId($uid);
            if (!$deptId) $this->json(['ok' => false, 'error' => 'HOD department not found'], 403);

            $stmt = $pdo->prepare("
                SELECT 1
                FROM students s
                JOIN teacher_classes tc ON tc.class_id = s.class_id
                JOIN teachers t ON t.id = tc.teacher_id
                WHERE s.id = :sid AND t.department_id = :dept
                LIMIT 1
            ");
            $stmt->execute([':sid' => (int)$existing['student_id'], ':dept' => $deptId]);
            if (!$stmt->fetchColumn()) $this->json(['ok' => false, 'error' => 'Forbidden'], 403);
        }

        // Build dynamic update
        $fields = [];
        $params = [':id' => $id];

        if (isset($data['type_id']) && $data['type_id'] !== '') {
            $fields[] = "type_id = :type_id";
            $params[':type_id'] = (int)$data['type_id'];
        }
        if (array_key_exists('action_id', $data)) {
            $fields[] = "action_id = :action_id";
            $params[':action_id'] = ($data['action_id'] === '' || $data['action_id'] === null) ? null : (int)$data['action_id'];
        }
        if (isset($data['incident_date']) && trim((string)$data['incident_date']) !== '') {
            $fields[] = "incident_date = :incident_date";
            $params[':incident_date'] = trim((string)$data['incident_date']);
        }
        if (array_key_exists('description', $data)) {
            $fields[] = "description = :description";
            $params[':description'] = ($data['description'] === '' ? null : trim((string)$data['description']));
        }
        if (array_key_exists('follow_up', $data)) {
            $fields[] = "follow_up = :follow_up";
            $params[':follow_up'] = ($data['follow_up'] === '' ? null : trim((string)$data['follow_up']));
        }

        if (!$fields) $this->json(['ok' => false, 'error' => 'Nothing to update'], 400);

        $sql = "UPDATE disciplinary_records SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $ok = $stmt->execute($params);

        if ($ok) {
            $this->json(['ok' => true]);
        }
        $this->json(['ok' => false, 'error' => 'Failed to update record'], 500);
    }

    // =========================================================
    // POST /api/discipline/delete
    // teacher: only if they reported it
    // hod: dept scope
    // head_teacher: any
    // Body: id
    // =========================================================
    public function delete(): void
    {
        $this->requireRole(['teacher', 'hod', 'head_teacher']);

        $data = $this->request();
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) $this->json(['ok' => false, 'error' => 'Invalid id'], 400);

        $pdo  = $this->pdo();
        $role = $this->role();
        $uid  = $this->userId();

        $stmt = $pdo->prepare("SELECT * FROM disciplinary_records WHERE id=:id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) $this->json(['ok' => false, 'error' => 'Record not found'], 404);

        if ($role === 'teacher') {
            if ((int)$existing['reported_by'] !== $uid) {
                $this->json(['ok' => false, 'error' => 'Forbidden'], 403);
            }
        }

        if ($role === 'hod') {
            $deptId = $this->hodDepartmentId($uid);
            if (!$deptId) $this->json(['ok' => false, 'error' => 'HOD department not found'], 403);

            $stmt = $pdo->prepare("
                SELECT 1
                FROM students s
                JOIN teacher_classes tc ON tc.class_id = s.class_id
                JOIN teachers t ON t.id = tc.teacher_id
                WHERE s.id = :sid AND t.department_id = :dept
                LIMIT 1
            ");
            $stmt->execute([':sid' => (int)$existing['student_id'], ':dept' => $deptId]);
            if (!$stmt->fetchColumn()) $this->json(['ok' => false, 'error' => 'Forbidden'], 403);
        }

        $stmt = $pdo->prepare("DELETE FROM disciplinary_records WHERE id=:id");
        $ok = $stmt->execute([':id' => $id]);

        if ($ok) {
            $this->json(['ok' => true]);
        }
        $this->json(['ok' => false, 'error' => 'Failed to delete record'], 500);
    }

    // =========================================================
    // GET /api/discipline/student?student_id=1
    // head_teacher: any
    // hod: dept scope
    // teacher: student must be in their class
    // =========================================================
    public function byStudent(): void
    {
        $this->requireAuth();

        $studentId = (int)($_GET['student_id'] ?? 0);
        if ($studentId <= 0) $this->json(['ok' => false, 'error' => 'Invalid student_id'], 400);

        $pdo  = $this->pdo();
        $role = $this->role();
        $uid  = $this->userId();

        if ($role === 'teacher') {
            $classId = $this->studentClassId($studentId);
            if (!$classId || !$this->teacherHasClass($uid, $classId)) {
                $this->json(['ok' => false, 'error' => 'Forbidden'], 403);
            }
        }

        if ($role === 'hod') {
            $deptId = $this->hodDepartmentId($uid);
            if (!$deptId) $this->json(['ok' => false, 'error' => 'HOD department not found'], 403);

            $stmt = $pdo->prepare("
                SELECT 1
                FROM students s
                JOIN teacher_classes tc ON tc.class_id = s.class_id
                JOIN teachers t ON t.id = tc.teacher_id
                WHERE s.id = :sid AND t.department_id = :dept
                LIMIT 1
            ");
            $stmt->execute([':sid' => $studentId, ':dept' => $deptId]);
            if (!$stmt->fetchColumn()) $this->json(['ok' => false, 'error' => 'Forbidden'], 403);
        }

        $stmt = $pdo->prepare("
            SELECT dr.*,
                   dt.name AS type_name, dt.severity,
                   da.action_name,
                   t.firstname AS reporter_firstname, t.lastname AS reporter_lastname
            FROM disciplinary_records dr
            JOIN disciplinary_types dt ON dt.id = dr.type_id
            LEFT JOIN disciplinary_actions da ON da.id = dr.action_id
            JOIN teachers t ON t.id = dr.reported_by
            WHERE dr.student_id = :sid
            ORDER BY dr.incident_date DESC, dr.id DESC
        ");
        $stmt->execute([':sid' => $studentId]);

        $this->json(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // =========================================================
    // GET /api/discipline/list
    // head_teacher: all records
    // hod: dept scope
    // teacher: only records they reported
    //
    // Optional filters:
    //  ?from=YYYY-MM-DD&to=YYYY-MM-DD&type_id=1&action_id=2&student_id=3
    // =========================================================
    public function list(): void
    {
        $this->requireAuth();

        $pdo  = $this->pdo();
        $role = $this->role();
        $uid  = $this->userId();

        $from = isset($_GET['from']) ? trim((string)$_GET['from']) : '';
        $to   = isset($_GET['to']) ? trim((string)$_GET['to']) : '';
        $typeId = isset($_GET['type_id']) && $_GET['type_id'] !== '' ? (int)$_GET['type_id'] : 0;
        $actionId = isset($_GET['action_id']) && $_GET['action_id'] !== '' ? (int)$_GET['action_id'] : 0;
        $studentId = isset($_GET['student_id']) && $_GET['student_id'] !== '' ? (int)$_GET['student_id'] : 0;

        $sql = "
            SELECT dr.*,
                   st.fname, st.lname,
                   dt.name AS type_name, dt.severity,
                   da.action_name,
                   t.firstname AS reporter_firstname, t.lastname AS reporter_lastname
            FROM disciplinary_records dr
            JOIN students st ON st.id = dr.student_id
            JOIN disciplinary_types dt ON dt.id = dr.type_id
            LEFT JOIN disciplinary_actions da ON da.id = dr.action_id
            JOIN teachers t ON t.id = dr.reported_by
            WHERE 1=1
        ";
        $params = [];

        if ($from !== '') {
            $sql .= " AND dr.incident_date >= :from";
            $params[':from'] = $from;
        }
        if ($to !== '') {
            $sql .= " AND dr.incident_date <= :to";
            $params[':to'] = $to;
        }
        if ($typeId > 0) {
            $sql .= " AND dr.type_id = :type_id";
            $params[':type_id'] = $typeId;
        }
        if ($actionId > 0) {
            $sql .= " AND dr.action_id = :action_id";
            $params[':action_id'] = $actionId;
        }
        if ($studentId > 0) {
            // permission checks in byStudent() cover scope; here we reuse similar approach
            if ($role === 'teacher') {
                $classId = $this->studentClassId($studentId);
                if (!$classId || !$this->teacherHasClass($uid, $classId)) {
                    $this->json(['ok' => false, 'error' => 'Forbidden'], 403);
                }
            }
            $sql .= " AND dr.student_id = :student_id";
            $params[':student_id'] = $studentId;
        }

        if ($role === 'teacher') {
            $sql .= " AND dr.reported_by = :me";
            $params[':me'] = $uid;
        }

        if ($role === 'hod') {
            $deptId = $this->hodDepartmentId($uid);
            if (!$deptId) $this->json(['ok' => false, 'error' => 'HOD department not found'], 403);

            // restrict to students in classes handled by department teachers
            $sql .= "
                AND EXISTS (
                    SELECT 1
                    FROM students sx
                    JOIN teacher_classes tc ON tc.class_id = sx.class_id
                    JOIN teachers tx ON tx.id = tc.teacher_id
                    WHERE sx.id = dr.student_id AND tx.department_id = :dept
                )
            ";
            $params[':dept'] = $deptId;
        }

        $sql .= " ORDER BY dr.incident_date DESC, dr.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $this->json(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }
}
