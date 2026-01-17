<?php

class BaseController
{
    // ---------- JSON helpers ----------
    protected function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
        exit;
    }

    // Supports both FormData ($_POST) and JSON body
    protected function request(): array
    {
        if (!empty($_POST)) return $_POST;

        $raw = file_get_contents('php://input');
        if (!$raw) return [];

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    // ---------- Auth + Role ----------
    protected function requireAuth(): void
    {
        if (empty($_SESSION['user'])) {
            $this->json(['ok' => false, 'error' => 'Unauthorized'], 401);
        }
    }

    protected function requireRole(array $roles): void
    {
        $this->requireAuth();
        $role = (string)($_SESSION['user']['role'] ?? '');
        if (!in_array($role, $roles, true)) {
            $this->json(['ok' => false, 'error' => 'Forbidden'], 403);
        }
    }

    protected function userId(): int
    {
        return (int)($_SESSION['user']['id'] ?? 0);
    }

    protected function role(): string
    {
        return (string)($_SESSION['user']['role'] ?? '');
    }

    protected function yearId(): ?int
    {
        $v = $_SESSION['context']['academic_year_id'] ?? null;
        return $v === null ? null : (int)$v;
    }

    protected function termId(): ?int
    {
        $v = $_SESSION['context']['term_id'] ?? null;
        return $v === null ? null : (int)$v;
    }

    // ---------- PDO helper ----------
    protected function pdo(): PDO
    {
        require_once __DIR__ . '/../models/BaseModel.php';

        // BaseModel sets up the PDO connection
        $bm = new BaseModel($this->userId(), $this->yearId(), $this->termId());

        // Access protected $conn safely via Reflection
        $ref = new ReflectionClass($bm);
        $prop = $ref->getProperty('conn');
        $prop->setAccessible(true);

        /** @var PDO $pdo */
        $pdo = $prop->getValue($bm);
        return $pdo;
    }

    // ---------- Permission helpers ----------
    // Teacher assigned to a class?
    protected function teacherHasClass(int $teacherId, int $classId): bool
    {
        $pdo = $this->pdo();
        $stmt = $pdo->prepare("SELECT 1 FROM teacher_classes WHERE teacher_id=:t AND class_id=:c LIMIT 1");
        $stmt->execute([':t' => $teacherId, ':c' => $classId]);
        return (bool)$stmt->fetchColumn();
    }

    // Teacher assigned to a subject?
    protected function teacherHasSubject(int $teacherId, int $subjectId): bool
    {
        $pdo = $this->pdo();
        $stmt = $pdo->prepare("SELECT 1 FROM teacher_subjects WHERE teacher_id=:t AND subject_id=:s LIMIT 1");
        $stmt->execute([':t' => $teacherId, ':s' => $subjectId]);
        return (bool)$stmt->fetchColumn();
    }

    // Get student's class_id
    protected function studentClassId(int $studentId): ?int
    {
        $pdo = $this->pdo();
        $stmt = $pdo->prepare("SELECT class_id FROM students WHERE id=:id LIMIT 1");
        $stmt->execute([':id' => $studentId]);
        $v = $stmt->fetchColumn();
        return $v === false ? null : (int)$v;
    }

    // HOD’s department_id (derived from departments.hod_id)
    protected function hodDepartmentId(int $hodTeacherId): ?int
    {
        $pdo = $this->pdo();
        $stmt = $pdo->prepare("SELECT id FROM departments WHERE hod_id=:hid LIMIT 1");
        $stmt->execute([':hid' => $hodTeacherId]);
        $v = $stmt->fetchColumn();
        return $v === false ? null : (int)$v;
    }
}
