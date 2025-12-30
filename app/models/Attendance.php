<?php
require_once __DIR__ . '/../config/database.php';

class Attendance {
    private $conn;
    private $table = 'attendance';
    private $logs_table = 'logs';

    public $id;
    public $student_id;
    public $class_id;
    public $status; // present, absent, late
    public $attendance_date;

    public $user_id; // teacher marking attendance
    public $academic_year_id;
    public $term_id;

    public function __construct($user_id = null, $academic_year_id = null, $term_id = null) {
        $database = new Database();
        $this->conn = $database->getConnection();

        $this->user_id = $user_id;
        $this->academic_year_id = $academic_year_id;
        $this->term_id = $term_id;
    }

    // Log helper
    private function logAction($action, $description = null) {
        if (!$this->user_id) return;

        $query = "INSERT INTO {$this->logs_table} 
                  (user_id, action, description, academic_year_id, term_id) 
                  VALUES (:user_id, :action, :description, :academic_year_id, :term_id)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $this->user_id, PDO::PARAM_INT);
        $stmt->bindParam(':action', $action);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':academic_year_id', $this->academic_year_id);
        $stmt->bindParam(':term_id', $this->term_id);
        $stmt->execute();
    }

    // Mark attendance
    public function mark() {
        // Check if attendance already exists
        $queryCheck = "SELECT id FROM {$this->table} 
                       WHERE student_id = :student_id AND class_id = :class_id 
                       AND attendance_date = :attendance_date 
                       AND academic_year_id = :academic_year_id 
                       AND term_id = :term_id";
        $stmtCheck = $this->conn->prepare($queryCheck);
        $stmtCheck->bindParam(':student_id', $this->student_id);
        $stmtCheck->bindParam(':class_id', $this->class_id);
        $stmtCheck->bindParam(':attendance_date', $this->attendance_date);
        $stmtCheck->bindParam(':academic_year_id', $this->academic_year_id);
        $stmtCheck->bindParam(':term_id', $this->term_id);
        $stmtCheck->execute();
        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Update existing record
            $query = "UPDATE {$this->table} SET status = :status WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':status', $this->status);
            $stmt->bindParam(':id', $existing['id']);
            $stmt->execute();

            $this->logAction("Update Attendance", 
                "Updated attendance for student {$this->student_id} on {$this->attendance_date}");
        } else {
            // Insert new record
            $query = "INSERT INTO {$this->table} 
                      (student_id, class_id, status, attendance_date, academic_year_id, term_id) 
                      VALUES (:student_id, :class_id, :status, :attendance_date, :academic_year_id, :term_id)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':student_id', $this->student_id);
            $stmt->bindParam(':class_id', $this->class_id);
            $stmt->bindParam(':status', $this->status);
            $stmt->bindParam(':attendance_date', $this->attendance_date);
            $stmt->bindParam(':academic_year_id', $this->academic_year_id);
            $stmt->bindParam(':term_id', $this->term_id);
            $stmt->execute();

            $this->logAction("Mark Attendance", 
                "Marked attendance for student {$this->student_id} on {$this->attendance_date}");
        }

        return true;
    }

    // Fetch attendance for a student
    public function getByStudent($student_id) {
        $query = "SELECT * FROM {$this->table} 
                  WHERE student_id = :student_id 
                  AND academic_year_id = :academic_year_id 
                  AND term_id = :term_id
                  ORDER BY attendance_date DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':student_id', $student_id);
        $stmt->bindParam(':academic_year_id', $this->academic_year_id);
        $stmt->bindParam(':term_id', $this->term_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Fetch attendance for a class on a specific date
    public function getByClassAndDate($class_id, $date) {
        $query = "SELECT a.*, s.fname, s.lname 
                  FROM {$this->table} a
                  JOIN students s ON a.student_id = s.id
                  WHERE a.class_id = :class_id 
                  AND a.attendance_date = :attendance_date
                  AND a.academic_year_id = :academic_year_id
                  AND a.term_id = :term_id
                  ORDER BY s.lname ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':class_id', $class_id);
        $stmt->bindParam(':attendance_date', $date);
        $stmt->bindParam(':academic_year_id', $this->academic_year_id);
        $stmt->bindParam(':term_id', $this->term_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Delete an attendance record
    public function delete() {
        $query = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        if ($stmt->execute()) {
            $this->logAction("Delete Attendance", "Deleted attendance record ID {$this->id}");
            return true;
        }
        return false;
    }
}
