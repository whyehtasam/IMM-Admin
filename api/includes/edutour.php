<?php
require_once 'database.php';
class EduTour {
    private $db;
    private $uploadDir;
    private $uploadUrl;

    public function __construct() {
        $this->connectDB();
        $this->uploadDir = UPLOAD_DIR;
        $this->uploadUrl = UPLOAD_URL;
    }

    private function connectDB() {
        try {
            $this->db = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME,
                DB_USER,
                DB_PASS
            );
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    public function getTours() {
        $stmt = $this->db->query("SELECT id, title, category, subcategory, description, file_name, created_at FROM edutours ORDER BY created_at DESC");
        $tours = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['url'] = $this->uploadUrl . $row['file_name'];
            $tours[] = $row;
        }
        return $tours;
    }

    public function uploadTour($title, $category, $subcategory, $description, $file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $file['error']);
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception('Invalid file type.');
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $extension;
        $destination = $this->uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new Exception('Failed to move uploaded file.');
        }

        $stmt = $this->db->prepare("INSERT INTO edutours (title, category, subcategory, description, file_name) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$title, $category, $subcategory, $description, $filename]);
        return $this->db->lastInsertId();
    }

    public function updateTour($id, $title, $category, $subcategory, $description, $file = null) {
        $stmt = $this->db->prepare("SELECT file_name FROM edutours WHERE id = ?");
        $stmt->execute([$id]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$current) throw new Exception('Tour not found.');

        $filename = $current['file_name'];

        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            if (file_exists($this->uploadDir . $filename)) {
                unlink($this->uploadDir . $filename);
            }

            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif','image/webp'];
            if (!in_array($file['type'], $allowedTypes)) {
                throw new Exception('Invalid file type.');
            }

            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $extension;
            $destination = $this->uploadDir . $filename;

            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                throw new Exception('Failed to move uploaded file.');
            }
        }

        $stmt = $this->db->prepare("UPDATE edutours SET title = ?, category = ?, subcategory = ?, description = ?, file_name = ? WHERE id = ?");
        $stmt->execute([$title, $category, $subcategory, $description, $filename, $id]);
        return true;
    }

    public function deleteTour($id) {
        $stmt = $this->db->prepare("SELECT file_name FROM edutours WHERE id = ?");
        $stmt->execute([$id]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$current) throw new Exception('Tour not found.');

        $filePath = $this->uploadDir . $current['file_name'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $stmt = $this->db->prepare("DELETE FROM edutours WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
?> 