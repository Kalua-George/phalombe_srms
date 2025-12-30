<?php
require_once __DIR__ . '/../config/database.php';

class Exam {
    private $conn;
    private $table = 'exams';
    private $exam_subjects_table = 'exam_subjects';
    private $grades_table = 'grades';
    private $logs_table = 'logs';

    public $id;
    public $name;
    public $exam_type; // CA, Midterm, EndTerm
    public $exam_date;
    public $academic_year_id;
    public $term_id;

    public $user_id; // teacher performing actions

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

    // Create exam and optionally link subjects
    public function create(array $subject_ids = []) {
        $query = "INSERT INTO {$this->table} (name, exam_type, exam_date, academic_year_id, term_id) 
                  VALUES (:name, :exam_type, :exam_date, :academic_year_id, :term_id)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':exam_type', $this->exam_type);
        $stmt->bindParam(':exam_date', $this->exam_date);
        $stmt->bindParam(':academic_year_id', $this->academic_year_id);
        $stmt->bindParam(':term_id', $this->term_id);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            $this->logAction("Create Exam", "Exam {$this->name} created with ID {$this->id}");

            foreach ($subject_ids as $subject_id) {
                $querySub = "INSERT INTO {$this->exam_subjects_table} (exam_id, subject_id) 
                             VALUES (:exam_id, :subject_id)";
                $stmtSub = $this->conn->prepare($querySub);
                $stmtSub->bindParam(':exam_id', $this->id);
                $stmtSub->bindParam(':subject_id', $subject_id);
                $stmtSub->execute();
            }
            return true;
        }
        return false;
    }

    // Fetch all exams
    public function getAll() {
        $query = "SELECT * FROM {$this->table} ORDER BY exam_date DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Fetch exam by ID
    public function getById($id) {
        $query = "SELECT * FROM {$this->table} WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get subjects linked to this exam
    public function getSubjects() {
        $query = "SELECT s.* 
                  FROM {$this->exam_subjects_table} es
                  JOIN subjects s ON es.subject_id = s.id
                  WHERE es.exam_id = :exam_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':exam_id', $this->id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Enter or update a student's grade with academic year & term
    public function enterGrade($student_id, $subject_id, $teacher_id, $score, $grade_id = null) {
        // Check for existing grade
        $queryCheck = "SELECT id FROM {$this->grades_table} 
                       WHERE exam_id = :exam_id AND student_id = :student_id AND subject_id = :subject_id
                       AND academic_year_id = :academic_year_id AND term_id = :term_id";
        $stmtCheck = $this->conn->prepare($queryCheck);
        $stmtCheck->bindParam(':exam_id', $this->id);
        $stmtCheck->bindParam(':student_id', $student_id);
        $stmtCheck->bindParam(':subject_id', $subject_id);
        $stmtCheck->bindParam(':academic_year_id', $this->academic_year_id);
        $stmtCheck->bindParam(':term_id', $this->term_id);
        $stmtCheck->execute();
        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $query = "UPDATE {$this->grades_table} SET score = :score, teacher_id = :teacher_id, grade_id = :grade_id
                      WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':score', $score);
            $stmt->bindParam(':teacher_id', $teacher_id);
            $stmt->bindParam(':grade_id', $grade_id);
            $stmt->bindParam(':id', $existing['id']);
            $stmt->execute();

            $this->logAction("Update Grade", "Grade updated for student {$student_id}, subject {$subject_id} in exam {$this->id}");
        } else {
            $query = "INSERT INTO {$this->grades_table} 
                      (exam_id, student_id, subject_id, teacher_id, score, grade_id, academic_year_id, term_id)
                      VALUES (:exam_id, :student_id, :subject_id, :teacher_id, :score, :grade_id, :academic_year_id, :term_id)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':exam_id', $this->id);
            $stmt->bindParam(':student_id', $student_id);
            $stmt->bindParam(':subject_id', $subject_id);
            $stmt->bindParam(':teacher_id', $teacher_id);
            $stmt->bindParam(':score', $score);
            $stmt->bindParam(':grade_id', $grade_id);
            $stmt->bindParam(':academic_year_id', $this->academic_year_id);
            $stmt->bindParam(':term_id', $this->term_id);
            $stmt->execute();

            $this->logAction("Enter Grade", "Grade entered for student {$student_id}, subject {$subject_id} in exam {$this->id}");
        }

        return true;
    }

    // Delete exam
    public function delete() {
        $query = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        if ($stmt->execute()) {
            $this->logAction("Delete Exam", "Exam ID {$this->id} deleted");
            return true;
        }
        return false;
    }
}
