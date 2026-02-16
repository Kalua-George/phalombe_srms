<?php
require_once __DIR__ . '/BaseController.php';

class AttendanceController extends BaseController
{
    // GET /api/attendance/students?class_id=1
    // returns students in that class (for marking attendance UI)
    public function studentsByClass(): void
    {
        $this->requireAuth();

        $classId = (int)($_GET['class_id'] ?? 0);
        if ($classId <= 0) $this->json(['ok' => false, 'error' => 'Invalid class_id'], 400);

        // teacher must be assigned to the class
        if ($this->role() === 'teacher' && !$this->teacherHasClass($this->userId(), $classId)) {
            $this->json(['ok' => false, 'error' => 'Forbidden'], 403);
        }

        $pdo = $this->pdo();
        $stmt = $pdo->prepare("SELECT id, student_code, fname, lname
                               FROM students
                               WHERE class_id = :class_id
                               ORDER BY lname ASC, fname ASC");
        $stmt->execute([':class_id' => $classId]);

        $this->json(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // POST /api/attendance/mark
    // body:
    //  class_id, attendance_date (YYYY-MM-DD)
    //  records: array of { student_id, status }  where status in: present|absent|late|excused
    public function mark(): void
    {
        $this->requireAuth();

        $data = $this->request();

        $classId = (int)($data['class_id'] ?? 0);
        $date    = trim((string)($data['attendance_date'] ?? ''));
        $records = $data['records'] ?? null;

        if ($classId <= 0 || $date === '' || !is_array($records) || count($records) === 0) {
            $this->json(['ok' => false, 'error' => 'class_id, attendance_date, records[] required'], 400);
        }

        // teacher must be assigned to the class
        if ($this->role() === 'teacher' && !$this->teacherHasClass($this->userId(), $classId)) {
            $this->json(['ok' => false, 'error' => 'Forbidden'], 403);
        }

        $yearId = $this->yearId();
        $termId = $this->termId();

        if ($yearId === null || $termId === null) {
            $this->json(['ok' => false, 'error' => 'Academic year/term not set in session'], 400);
        }

        // Validate statuses
        $allowed = ['present','absent','late','excused'];

        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            // Upsert based on UNIQUE(student_id, class_id, attendance_date, academic_year_id, term_id)
            $sql = "INSERT INTO attendance
                    (student_id, class_id, attendance_date, academic_year_id, term_id, status, marked_by)
                    VALUES
                    (:student_id, :class_id, :attendance_date, :academic_year_id, :term_id, :status, :marked_by)
                    ON DUPLICATE KEY UPDATE
                        status = VALUES(status),
                        marked_by = VALUES(marked_by)";

            $stmt = $pdo->prepare($sql);

            $count = 0;
            foreach ($records as $r) {
                $studentId = (int)($r['student_id'] ?? 0);
                $status    = strtolower(trim((string)($r['status'] ?? '')));

                if ($studentId <= 0 || !in_array($status, $allowed, true)) {
                    continue;
                }

                // ensure student belongs to class (prevent cross-class marking)
                $st = $pdo->prepare("SELECT 1 FROM students WHERE id=:sid AND class_id=:cid LIMIT 1");
                $st->execute([':sid' => $studentId, ':cid' => $classId]);
                if (!$st->fetchColumn()) {
                    continue;
                }

                $stmt->execute([
                    ':student_id' => $studentId,
                    ':class_id' => $classId,
                    ':attendance_date' => $date,
                    ':academic_year_id' => $yearId,
                    ':term_id' => $termId,
                    ':status' => $status,
                    ':marked_by' => $this->userId(),
                ]);

                $count++;
            }

            $pdo->commit();
            $this->json(['ok' => true, 'message' => 'Attendance saved', 'saved' => $count]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            $this->json(['ok' => false, 'error' => 'Failed to save attendance'], 500);
        }
    }

    // GET /api/attendance/by-class-date?class_id=1&date=2026-01-15
    public function byClassAndDate(): void
    {
        $this->requireAuth();

        $classId = (int)($_GET['class_id'] ?? 0);
        $date    = trim((string)($_GET['date'] ?? ''));

        if ($classId <= 0 || $date === '') {
            $this->json(['ok' => false, 'error' => 'class_id and date required'], 400);
        }

        // teacher must be assigned to the class
        if ($this->role() === 'teacher' && !$this->teacherHasClass($this->userId(), $classId)) {
            $this->json(['ok' => false, 'error' => 'Forbidden'], 403);
        }

        $yearId = $this->yearId();
        $termId = $this->termId();

        $pdo = $this->pdo();
        $stmt = $pdo->prepare("
            SELECT a.id, a.student_id, a.class_id, a.attendance_date, a.status, a.marked_by,
                   s.student_code, s.fname, s.lname
            FROM attendance a
            JOIN students s ON s.id = a.student_id
            WHERE a.class_id = :class_id
              AND a.attendance_date = :dt
              AND a.academic_year_id = :year
              AND a.term_id = :term
            ORDER BY s.lname ASC, s.fname ASC
        ");
        $stmt->execute([
            ':class_id' => $classId,
            ':dt' => $date,
            ':year' => $yearId,
            ':term' => $termId
        ]);

        $this->json(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // GET /api/attendance/by-student?id=5
    public function byStudent(): void
    {
        $this->requireAuth();

        $studentId = (int)($_GET['id'] ?? 0);
        if ($studentId <= 0) $this->json(['ok' => false, 'error' => 'Invalid student id'], 400);

        // teachers can only view students in their classes
        if ($this->role() === 'teacher') {
            $classId = $this->studentClassId($studentId);
            if (!$classId || !$this->teacherHasClass($this->userId(), (int)$classId)) {
                $this->json(['ok' => false, 'error' => 'Forbidden'], 403);
            }
        }

        $yearId = $this->yearId();
        $termId = $this->termId();

        $pdo = $this->pdo();
        $stmt = $pdo->prepare("
            SELECT id, student_id, class_id, attendance_date, status, marked_by
            FROM attendance
            WHERE student_id = :sid
              AND academic_year_id = :year
              AND term_id = :term
            ORDER BY attendance_date DESC
        ");
        $stmt->execute([
            ':sid' => $studentId,
            ':year' => $yearId,
            ':term' => $termId
        ]);

        $this->json(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }
}
