<?php
require_once __DIR__ . '/../config/database.php';

class Grade {
    private $conn;
    private $table = 'grades';
    private $logs_table = 'logs';

    public $id;
    public $exam_id;
    public $student_id;
    public $subject_id;
    public $teacher_id;
    public $score;
    public $grade_id; // optional mapping to a grade (A, B, etc.)
    
    public $academic_year_id;
    public $term_id;

    public $user_id; // teacher performing the action

    public function __construct($user_id = null, $academic_year_id = null, $term_id = null) {
        $database = new Database();
        $this->conn = $database->getConnection();

        $this->user_id = $user_id;
        $this->academic_year_id = $academic_year_id;
        $this->term_id = $term_id;
    }

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

    // Create or update grade
    public function save() {
        // check if grade exists
        $queryCheck = "SELECT id FROM {$this->table} 
                       WHERE exam_id = :exam_id AND student_id = :student_id AND subject_id = :subject_id";
        $stmtCheck = $this->conn->prepare($queryCheck);
        $stmtCheck->bindParam(':exam_id', $this->exam_id);
        $stmtCheck->bindParam(':student_id', $this->student_id);
        $stmtCheck->bindParam(':subject_id', $this->subject_id);
        $stmtCheck->execute();

        $existing = $stmtCheck->fetch();

        if ($existing) {
            // update existing grade
            $query = "UPDATE {$this->table} SET 
                      score = :score, 
                      teacher_id = :teacher_id,
                      grade_id = :grade_id,
                      academic_year_id = :academic_year_id,
                      term_id = :term_id
                      WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':score', $this->score);
            $stmt->bindParam(':teacher_id', $this->teacher_id);
            $stmt->bindParam(':grade_id', $this->grade_id);
            $stmt->bindParam(':academic_year_id', $this->academic_year_id);
            $stmt->bindParam(':term_id', $this->term_id);
            $stmt->bindParam(':id', $existing['id']);
            $stmt->execute();

            $this->logAction("Update Grade", "Updated grade ID {$existing['id']} for student {$this->student_id}");
            return true;
        } else {
            // insert new grade
            $query = "INSERT INTO {$this->table} 
                      (exam_id, student_id, subject_id, teacher_id, score, grade_id, academic_year_id, term_id)
                      VALUES (:exam_id, :student_id, :subject_id, :teacher_id, :score, :grade_id, :academic_year_id, :term_id)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':exam_id', $this->exam_id);
            $stmt->bindParam(':student_id', $this->student_id);
            $stmt->bindParam(':subject_id', $this->subject_id);
            $stmt->bindParam(':teacher_id', $this->teacher_id);
            $stmt->bindParam(':score', $this->score);
            $stmt->bindParam(':grade_id', $this->grade_id);
            $stmt->bindParam(':academic_year_id', $this->academic_year_id);
            $stmt->bindParam(':term_id', $this->term_id);
            $stmt->execute();

            $this->id = $this->conn->lastInsertId();
            $this->logAction("Enter Grade", "Entered grade ID {$this->id} for student {$this->student_id}");
            return true;
        }
    }

    // Fetch grades for a student
    public function getByStudent($student_id) {
        $query = "SELECT g.*, s.name AS subject_name, e.name AS exam_name
                  FROM {$this->table} g
                  JOIN subjects s ON g.subject_id = s.id
                  JOIN exams e ON g.exam_id = e.id
                  WHERE g.student_id = :student_id
                  ORDER BY e.exam_date DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Fetch grades for an exam
    public function getByExam($exam_id) {
        $query = "SELECT g.*, s.name AS subject_name, st.fname, st.lname
                  FROM {$this->table} g
                  JOIN subjects s ON g.subject_id = s.id
                  JOIN students st ON g.student_id = st.id
                  WHERE g.exam_id = :exam_id
                  ORDER BY st.lname ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':exam_id', $exam_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
