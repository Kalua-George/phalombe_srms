<?php
require_once __DIR__ . '/BaseModel.php';

class Exam extends BaseModel {
    private $table = 'exams';
    private $exam_subjects_table = 'exam_subjects';
    private $grades_table = 'grades';

    public $id;
    public $name;
    public $exam_type;   // CA, Midterm, EndTerm
    public $exam_date;   // YYYY-MM-DD

    public function __construct($user_id = null, $academic_year_id = null, $term_id = null) {
        parent::__construct($user_id, $academic_year_id, $term_id);
    }

    private function validate() {
        if (empty($this->name) || empty($this->exam_type) || empty($this->exam_date)) {
            throw new Exception("name, exam_type and exam_date are required.");
        }

        $allowedTypes = ['CA', 'Midterm', 'EndTerm'];
        if (!in_array($this->exam_type, $allowedTypes, true)) {
            throw new Exception("Invalid exam_type. Allowed: CA, Midterm, EndTerm.");
        }

        $d = DateTime::createFromFormat('Y-m-d', (string)$this->exam_date);
        if (!$d || $d->format('Y-m-d') !== $this->exam_date) {
            throw new Exception("Invalid exam_date. Use YYYY-MM-DD.");
        }

        if (empty($this->academic_year_id) || empty($this->term_id)) {
            throw new Exception("academic_year_id and term_id are required.");
        }
    }

    // Create exam and (optionally) link subjects
    public function create(array $subject_ids = []) {
        try {
            $this->validate();

            $query = "INSERT INTO {$this->table}
                      (name, exam_type, exam_date, academic_year_id, term_id)
                      VALUES (:name, :exam_type, :exam_date, :academic_year_id, :term_id)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':name', $this->name);
            $stmt->bindParam(':exam_type', $this->exam_type);
            $stmt->bindParam(':exam_date', $this->exam_date);
            $stmt->bindParam(':academic_year_id', $this->academic_year_id, PDO::PARAM_INT);
            $stmt->bindParam(':term_id', $this->term_id, PDO::PARAM_INT);

            if (!$stmt->execute()) return false;

            $this->id = (int)$this->conn->lastInsertId();
            $this->logAction("Create Exam", "Exam {$this->name} created with ID {$this->id}");

            // Link subjects (avoid duplicates)
            if (!empty($subject_ids)) {
                $querySub = "INSERT IGNORE INTO {$this->exam_subjects_table} (exam_id, subject_id)
                             VALUES (:exam_id, :subject_id)";
                $stmtSub = $this->conn->prepare($querySub);

                foreach ($subject_ids as $subject_id) {
                    $stmtSub->bindParam(':exam_id', $this->id, PDO::PARAM_INT);
                    $stmtSub->bindParam(':subject_id', $subject_id, PDO::PARAM_INT);
                    $stmtSub->execute();
                }
            }

            return true;

        } catch (Exception $e) {
            error_log("Exam create() Error: " . $e->getMessage());
            return false;
        } catch (PDOException $e) {
            error_log("Exam create() DB Error: " . $e->getMessage());
            return false;
        }
    }

    // Fetch all exams (by default filtered to current term+year)
    public function getAll($filterByTermYear = true) {
        try {
            $query = "SELECT * FROM {$this->table}";
            if ($filterByTermYear) {
                $query .= " WHERE academic_year_id = :academic_year_id AND term_id = :term_id";
            }
            $query .= " ORDER BY exam_date DESC";

            $stmt = $this->conn->prepare($query);

            if ($filterByTermYear) {
                $stmt->bindParam(':academic_year_id', $this->academic_year_id, PDO::PARAM_INT);
                $stmt->bindParam(':term_id', $this->term_id, PDO::PARAM_INT);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Exam getAll() Error: " . $e->getMessage());
            return [];
        }
    }

    // Fetch exam by ID
    public function getById($id) {
        try {
            $query = "SELECT * FROM {$this->table} WHERE id = :id LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Exam getById() Error: " . $e->getMessage());
            return null;
        }
    }

    // Get subjects linked to this exam ($this->id must be set)
    public function getSubjects() {
        try {
            if (empty($this->id)) throw new Exception("Exam id is required.");

            $query = "SELECT s.*
                      FROM {$this->exam_subjects_table} es
                      JOIN subjects s ON es.subject_id = s.id
                      WHERE es.exam_id = :exam_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':exam_id', $this->id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Exam getSubjects() Error: " . $e->getMessage());
            return [];
        } catch (PDOException $e) {
            error_log("Exam getSubjects() DB Error: " . $e->getMessage());
            return [];
        }
    }

    // Enter or update a student's grade (keeps year+term consistent)
    public function enterGrade($student_id, $subject_id, $teacher_id, $score, $grade_id = null) {
        try {
            if (empty($this->id)) throw new Exception("Exam id is required.");

            if (!is_numeric($score)) throw new Exception("score must be numeric.");
            $score = (float)$score;
            if ($score < 0 || $score > 100) throw new Exception("score must be between 0 and 100.");

            $queryCheck = "SELECT id FROM {$this->grades_table}
                           WHERE exam_id = :exam_id
                             AND student_id = :student_id
                             AND subject_id = :subject_id
                             AND academic_year_id = :academic_year_id
                             AND term_id = :term_id
                           LIMIT 1";
            $stmtCheck = $this->conn->prepare($queryCheck);
            $stmtCheck->bindParam(':exam_id', $this->id, PDO::PARAM_INT);
            $stmtCheck->bindParam(':student_id', $student_id, PDO::PARAM_INT);
            $stmtCheck->bindParam(':subject_id', $subject_id, PDO::PARAM_INT);
            $stmtCheck->bindParam(':academic_year_id', $this->academic_year_id, PDO::PARAM_INT);
            $stmtCheck->bindParam(':term_id', $this->term_id, PDO::PARAM_INT);
            $stmtCheck->execute();

            $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $query = "UPDATE {$this->grades_table}
                          SET score = :score, teacher_id = :teacher_id, grade_id = :grade_id
                          WHERE id = :id";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':score', $score);
                $stmt->bindParam(':teacher_id', $teacher_id, PDO::PARAM_INT);
                $stmt->bindParam(':grade_id', $grade_id);
                $stmt->bindParam(':id', $existing['id'], PDO::PARAM_INT);
                $stmt->execute();

                $this->logAction("Update Grade", "Updated grade for student {$student_id}, subject {$subject_id}, exam {$this->id}");
                return true;
            }

            $query = "INSERT INTO {$this->grades_table}
                      (exam_id, student_id, subject_id, teacher_id, score, grade_id, academic_year_id, term_id)
                      VALUES
                      (:exam_id, :student_id, :subject_id, :teacher_id, :score, :grade_id, :academic_year_id, :term_id)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':exam_id', $this->id, PDO::PARAM_INT);
            $stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
            $stmt->bindParam(':subject_id', $subject_id, PDO::PARAM_INT);
            $stmt->bindParam(':teacher_id', $teacher_id, PDO::PARAM_INT);
            $stmt->bindParam(':score', $score);
            $stmt->bindParam(':grade_id', $grade_id);
            $stmt->bindParam(':academic_year_id', $this->academic_year_id, PDO::PARAM_INT);
            $stmt->bindParam(':term_id', $this->term_id, PDO::PARAM_INT);
            $stmt->execute();

            $this->logAction("Enter Grade", "Entered grade for student {$student_id}, subject {$subject_id}, exam {$this->id}");
            return true;

        } catch (Exception $e) {
            error_log("Exam enterGrade() Error: " . $e->getMessage());
            return false;
        } catch (PDOException $e) {
            error_log("Exam enterGrade() DB Error: " . $e->getMessage());
            return false;
        }
    }

    // Delete exam (optionally you can also delete related exam_subjects / grades in DB with ON DELETE CASCADE)
    public function delete() {
        try {
            if (empty($this->id)) throw new Exception("Exam id is required for delete.");

            $query = "DELETE FROM {$this->table} WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $this->logAction("Delete Exam", "Exam ID {$this->id} deleted");
                return true;
            }
            return false;

        } catch (Exception $e) {
            error_log("Exam delete() Error: " . $e->getMessage());
            return false;
        } catch (PDOException $e) {
            error_log("Exam delete() DB Error: " . $e->getMessage());
            return false;
        }
    }
}
