<?php
require_once __DIR__ . '/BaseModel.php';

class Grade extends BaseModel {
    private $table = 'grades';

    public $id;
    public $exam_id;
    public $student_id;
    public $subject_id;
    public $teacher_id;
    public $score;
    public $grade_id; // optional mapping (A, B, etc.)

    public function __construct($user_id = null, $academic_year_id = null, $term_id = null) {
        parent::__construct($user_id, $academic_year_id, $term_id);
    }

    private function validate() {
        if (empty($this->exam_id) || empty($this->student_id) || empty($this->subject_id) || empty($this->teacher_id)) {
            throw new Exception("exam_id, student_id, subject_id, and teacher_id are required.");
        }

        if (!is_numeric($this->score)) {
            throw new Exception("score must be numeric.");
        }

        // Basic score sanity check (adjust max if your school uses different)
        $score = (float)$this->score;
        if ($score < 0 || $score > 100) {
            throw new Exception("score must be between 0 and 100.");
        }

        if (empty($this->academic_year_id) || empty($this->term_id)) {
            throw new Exception("academic_year_id and term_id are required.");
        }
    }

    // Create or update grade (upsert)
    public function save() {
        try {
            $this->validate();

            // Check if grade exists (include year+term for uniqueness)
            $queryCheck = "SELECT id FROM {$this->table}
                           WHERE exam_id = :exam_id
                             AND student_id = :student_id
                             AND subject_id = :subject_id
                             AND academic_year_id = :academic_year_id
                             AND term_id = :term_id
                           LIMIT 1";
            $stmtCheck = $this->conn->prepare($queryCheck);
            $stmtCheck->bindParam(':exam_id', $this->exam_id, PDO::PARAM_INT);
            $stmtCheck->bindParam(':student_id', $this->student_id, PDO::PARAM_INT);
            $stmtCheck->bindParam(':subject_id', $this->subject_id, PDO::PARAM_INT);
            $stmtCheck->bindParam(':academic_year_id', $this->academic_year_id, PDO::PARAM_INT);
            $stmtCheck->bindParam(':term_id', $this->term_id, PDO::PARAM_INT);
            $stmtCheck->execute();

            $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Update existing grade
                $query = "UPDATE {$this->table} SET
                          score = :score,
                          teacher_id = :teacher_id,
                          grade_id = :grade_id
                          WHERE id = :id";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':score', $this->score);
                $stmt->bindParam(':teacher_id', $this->teacher_id, PDO::PARAM_INT);
                $stmt->bindParam(':grade_id', $this->grade_id);
                $stmt->bindParam(':id', $existing['id'], PDO::PARAM_INT);
                $stmt->execute();

                $this->logAction("Update Grade", "Updated grade ID {$existing['id']} for student {$this->student_id}");
                return true;
            }

            // Insert new grade
            $query = "INSERT INTO {$this->table}
                      (exam_id, student_id, subject_id, teacher_id, score, grade_id, academic_year_id, term_id)
                      VALUES
                      (:exam_id, :student_id, :subject_id, :teacher_id, :score, :grade_id, :academic_year_id, :term_id)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':exam_id', $this->exam_id, PDO::PARAM_INT);
            $stmt->bindParam(':student_id', $this->student_id, PDO::PARAM_INT);
            $stmt->bindParam(':subject_id', $this->subject_id, PDO::PARAM_INT);
            $stmt->bindParam(':teacher_id', $this->teacher_id, PDO::PARAM_INT);
            $stmt->bindParam(':score', $this->score);
            $stmt->bindParam(':grade_id', $this->grade_id);
            $stmt->bindParam(':academic_year_id', $this->academic_year_id, PDO::PARAM_INT);
            $stmt->bindParam(':term_id', $this->term_id, PDO::PARAM_INT);
            $stmt->execute();

            $this->id = (int)$this->conn->lastInsertId();
            $this->logAction("Enter Grade", "Entered grade ID {$this->id} for student {$this->student_id}");
            return true;

        } catch (Exception $e) {
            error_log("Grade save() Error: " . $e->getMessage());
            return false;
        } catch (PDOException $e) {
            error_log("Grade save() DB Error: " . $e->getMessage());
            return false;
        }
    }

    // Fetch grades for a student (optionally filter by year+term using BaseModel values)
    public function getByStudent($student_id, $filterByTermYear = true) {
        try {
            $query = "SELECT g.*, s.name AS subject_name, e.name AS exam_name
                      FROM {$this->table} g
                      JOIN subjects s ON g.subject_id = s.id
                      JOIN exams e ON g.exam_id = e.id
                      WHERE g.student_id = :student_id";

            if ($filterByTermYear) {
                $query .= " AND g.academic_year_id = :academic_year_id AND g.term_id = :term_id";
            }

            $query .= " ORDER BY e.exam_date DESC";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);

            if ($filterByTermYear) {
                $stmt->bindParam(':academic_year_id', $this->academic_year_id, PDO::PARAM_INT);
                $stmt->bindParam(':term_id', $this->term_id, PDO::PARAM_INT);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Grade getByStudent Error: " . $e->getMessage());
            return [];
        }
    }

    // Fetch grades for an exam (optionally filter by year+term)
    public function getByExam($exam_id, $filterByTermYear = true) {
        try {
            $query = "SELECT g.*, s.name AS subject_name, st.fname, st.lname
                      FROM {$this->table} g
                      JOIN subjects s ON g.subject_id = s.id
                      JOIN students st ON g.student_id = st.id
                      WHERE g.exam_id = :exam_id";

            if ($filterByTermYear) {
                $query .= " AND g.academic_year_id = :academic_year_id AND g.term_id = :term_id";
            }

            $query .= " ORDER BY st.lname ASC";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':exam_id', $exam_id, PDO::PARAM_INT);

            if ($filterByTermYear) {
                $stmt->bindParam(':academic_year_id', $this->academic_year_id, PDO::PARAM_INT);
                $stmt->bindParam(':term_id', $this->term_id, PDO::PARAM_INT);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Grade getByExam Error: " . $e->getMessage());
            return [];
        }
    }
}
