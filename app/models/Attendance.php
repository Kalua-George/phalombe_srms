<?php
require_once __DIR__ . '/BaseModel.php';

class Attendance extends BaseModel {
    private $table = 'attendance';

    public $id;
    public $student_id;
    public $class_id;
    public $status;          // present, absent, late
    public $attendance_date; // YYYY-MM-DD

    public function __construct($user_id = null, $academic_year_id = null, $term_id = null) {
        parent::__construct($user_id, $academic_year_id, $term_id);
    }

    // Basic validation to keep data clean
    private function validate() {
        $allowed = ['present', 'absent', 'late'];

        if (empty($this->student_id) || empty($this->class_id)) {
            throw new Exception("student_id and class_id are required.");
        }

        if (!in_array($this->status, $allowed, true)) {
            throw new Exception("Invalid status. Allowed: present, absent, late.");
        }

        // Basic date sanity check (expects YYYY-MM-DD)
        $d = DateTime::createFromFormat('Y-m-d', (string)$this->attendance_date);
        if (!$d || $d->format('Y-m-d') !== $this->attendance_date) {
            throw new Exception("Invalid attendance_date. Use YYYY-MM-DD.");
        }

        if (empty($this->academic_year_id) || empty($this->term_id)) {
            throw new Exception("academic_year_id and term_id are required.");
        }
    }

    // Mark attendance (insert or update if already exists)
    public function mark() {
        try {
            $this->validate();

            // Check existing record for the same student/class/date/year/term
            $queryCheck = "SELECT id FROM {$this->table}
                           WHERE student_id = :student_id
                             AND class_id = :class_id
                             AND attendance_date = :attendance_date
                             AND academic_year_id = :academic_year_id
                             AND term_id = :term_id
                           LIMIT 1";
            $stmtCheck = $this->conn->prepare($queryCheck);
            $stmtCheck->bindParam(':student_id', $this->student_id, PDO::PARAM_INT);
            $stmtCheck->bindParam(':class_id', $this->class_id, PDO::PARAM_INT);
            $stmtCheck->bindParam(':attendance_date', $this->attendance_date);
            $stmtCheck->bindParam(':academic_year_id', $this->academic_year_id, PDO::PARAM_INT);
            $stmtCheck->bindParam(':term_id', $this->term_id, PDO::PARAM_INT);
            $stmtCheck->execute();

            $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Update
                $query = "UPDATE {$this->table}
                          SET status = :status
                          WHERE id = :id";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':status', $this->status);
                $stmt->bindParam(':id', $existing['id'], PDO::PARAM_INT);
                $stmt->execute();

                $this->logAction(
                    "Update Attendance",
                    "Updated attendance for student {$this->student_id} on {$this->attendance_date}"
                );

                return true;
            }

            // Insert
            $query = "INSERT INTO {$this->table}
                      (student_id, class_id, status, attendance_date, academic_year_id, term_id)
                      VALUES
                      (:student_id, :class_id, :status, :attendance_date, :academic_year_id, :term_id)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':student_id', $this->student_id, PDO::PARAM_INT);
            $stmt->bindParam(':class_id', $this->class_id, PDO::PARAM_INT);
            $stmt->bindParam(':status', $this->status);
            $stmt->bindParam(':attendance_date', $this->attendance_date);
            $stmt->bindParam(':academic_year_id', $this->academic_year_id, PDO::PARAM_INT);
            $stmt->bindParam(':term_id', $this->term_id, PDO::PARAM_INT);
            $stmt->execute();

            $this->id = (int)$this->conn->lastInsertId();

            $this->logAction(
                "Mark Attendance",
                "Marked attendance for student {$this->student_id} on {$this->attendance_date}"
            );

            return true;

        } catch (Exception $e) {
            error_log("Attendance mark() Error: " . $e->getMessage());
            return false;
        } catch (PDOException $e) {
            error_log("Attendance mark() DB Error: " . $e->getMessage());
            return false;
        }
    }

    // Fetch attendance for a student (current academic year + term)
    public function getByStudent($student_id) {
        try {
            $query = "SELECT *
                      FROM {$this->table}
                      WHERE student_id = :student_id
                        AND academic_year_id = :academic_year_id
                        AND term_id = :term_id
                      ORDER BY attendance_date DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
            $stmt->bindParam(':academic_year_id', $this->academic_year_id, PDO::PARAM_INT);
            $stmt->bindParam(':term_id', $this->term_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Attendance getByStudent Error: " . $e->getMessage());
            return [];
        }
    }

    // Fetch attendance for a class on a specific date (current academic year + term)
    public function getByClassAndDate($class_id, $date) {
        try {
            $query = "SELECT a.*, s.fname, s.lname
                      FROM {$this->table} a
                      JOIN students s ON a.student_id = s.id
                      WHERE a.class_id = :class_id
                        AND a.attendance_date = :attendance_date
                        AND a.academic_year_id = :academic_year_id
                        AND a.term_id = :term_id
                      ORDER BY s.lname ASC";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':class_id', $class_id, PDO::PARAM_INT);
            $stmt->bindParam(':attendance_date', $date);
            $stmt->bindParam(':academic_year_id', $this->academic_year_id, PDO::PARAM_INT);
            $stmt->bindParam(':term_id', $this->term_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Attendance getByClassAndDate Error: " . $e->getMessage());
            return [];
        }
    }

    // Delete an attendance record by $this->id
    public function delete() {
        try {
            if (empty($this->id)) {
                throw new Exception("Attendance id is required for delete.");
            }

            $query = "DELETE FROM {$this->table} WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $this->logAction("Delete Attendance", "Deleted attendance record ID {$this->id}");
                return true;
            }
            return false;

        } catch (Exception $e) {
            error_log("Attendance delete() Error: " . $e->getMessage());
            return false;
        } catch (PDOException $e) {
            error_log("Attendance delete() DB Error: " . $e->getMessage());
            return false;
        }
    }
}
