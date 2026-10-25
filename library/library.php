<?php
/*
 * ==========================================================
 *  Library Book Management System (PHP + MySQL, OOP)
 *  Group 3
 *  Members: (put all member names here)
 * ==========================================================
 *  HOW TO RUN (XAMPP):
 *  1. Save this file as: C:\xampp\htdocs\library\library.php
 *  2. Start Apache and MySQL in the XAMPP Control Panel
 *  3. Open: http://localhost/library/library.php
 *  The database and table are created automatically.
 * ==========================================================
 */

/* ---------------------------------------------------------
 * CLASS 1: Database
 * Handles only the MySQL connection.
 * Encapsulation: connection details are private.
 * --------------------------------------------------------- */
class Database
{
    private $host = "localhost";
    private $user = "root";          // default XAMPP user
    private $pass = "";              // default XAMPP password is empty
    private $name = "library_db";
    private $conn;

    public function getConnection()
    {
        mysqli_report(MYSQLI_REPORT_OFF);

        // Connect to MySQL server
        $this->conn = new mysqli($this->host, $this->user, $this->pass);
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error .
                ". Make sure MySQL is running in XAMPP.");
        }

        // Create the database and table automatically if missing
        $this->conn->query("CREATE DATABASE IF NOT EXISTS {$this->name}");
        $this->conn->select_db($this->name);
        $this->conn->query(
            "CREATE TABLE IF NOT EXISTS books (
                book_id  INT AUTO_INCREMENT PRIMARY KEY,
                title    VARCHAR(150) NOT NULL,
                author   VARCHAR(100) NOT NULL,
                category VARCHAR(80)  NOT NULL,
                status   ENUM('Available','Borrowed') NOT NULL DEFAULT 'Available'
            )"
        );
        return $this->conn;
    }
}

/* ---------------------------------------------------------
 * CLASS 2: Book
 * Holds book data (private properties) and all CRUD methods.
 * Object interaction: receives the connection from Database.
 * --------------------------------------------------------- */
class Book
{
    private $conn;
    private $id;
    private $title;
    private $author;
    private $category;
    private $status = "Available";

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    // ----- Getters and setters (encapsulation) -----
    public function setId($id)             { $this->id = (int)$id; }
    public function setTitle($title)       { $this->title = trim($title); }
    public function setAuthor($author)     { $this->author = trim($author); }
    public function setCategory($category) { $this->category = trim($category); }
    public function setStatus($status)
    {
        // Only allow valid statuses
        $this->status = ($status === "Borrowed") ? "Borrowed" : "Available";
    }
    public function getTitle()    { return $this->title; }
    public function getAuthor()   { return $this->author; }
    public function getCategory() { return $this->category; }

    // ----- CREATE: add a new book -----
    public function create()
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO books (title, author, category, status) VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param("ssss", $this->title, $this->author, $this->category, $this->status);
        return $stmt->execute();
    }

    // ----- READ: all books -----
    public function readAll()
    {
        $result = $this->conn->query("SELECT * FROM books ORDER BY book_id DESC");
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // ----- READ: one book by ID (for the edit form) -----
    public function readOne($id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM books WHERE book_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    // ----- SEARCH: by title or author -----
    public function search($keyword)
    {
        $like = "%" . $keyword . "%";
        $stmt = $this->conn->prepare(
            "SELECT * FROM books WHERE title LIKE ? OR author LIKE ? ORDER BY book_id DESC"
        );
        $stmt->bind_param("ss", $like, $like);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // ----- UPDATE: edit book information -----
    public function update()
    {
        $stmt = $this->conn->prepare(
            "UPDATE books SET title = ?, author = ?, category = ?, status = ? WHERE book_id = ?"
        );
        $stmt->bind_param("ssssi", $this->title, $this->author, $this->category, $this->status, $this->id);
        return $stmt->execute();
    }

    // ----- DELETE: remove a book -----
    public function delete($id)
    {
        $stmt = $this->conn->prepare("DELETE FROM books WHERE book_id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    // ----- Switch status Available <-> Borrowed -----
    public function toggleStatus($id)
    {
        $stmt = $this->conn->prepare(
            "UPDATE books SET status = IF(status = 'Available', 'Borrowed', 'Available') WHERE book_id = ?"
        );
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }
}

/* ---------------------------------------------------------
 * MAIN PROGRAM: create objects and handle requests
 * --------------------------------------------------------- */
$db   = new Database();
$book = new Book($db->getConnection());   // object interaction

$self  = basename($_SERVER["PHP_SELF"]);
$page  = $_GET["page"] ?? "list";         // list | add | edit
$error = "";

// Handle form submissions (POST)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    $id     = (int)($_POST["id"] ?? 0);

    if ($action === "delete") {
        $book->delete($id);
        header("Location: $self");
        exit;
    }

    if ($action === "toggle") {
        $book->toggleStatus($id);
        $q = trim($_POST["q"] ?? "");
        header("Location: $self" . ($q !== "" ? "?q=" . urlencode($q) : ""));
        exit;
    }

    if ($action === "add" || $action === "update") {
        // Set values using setters
        $book->setId($id);
        $book->setTitle($_POST["title"] ?? "");
        $book->setAuthor($_POST["author"] ?? "");
        $book->setCategory($_POST["category"] ?? "");
        $book->setStatus($_POST["status"] ?? "Available");

        if ($book->getTitle() === "" || $book->getAuthor() === "" || $book->getCategory() === "") {
            $error = "Please fill in all fields.";
            $page  = ($action === "add") ? "add" : "edit";
            $_GET["id"] = $id;
        } else {
            if ($action === "add") { $book->create(); } else { $book->update(); }
            header("Location: $self");
            exit;
        }
    }
}

// Helper to safely print text in HTML
function e($text) { return htmlspecialchars((string)$text); }

// Data for the pages
$q     = trim($_GET["q"] ?? "");
$books = ($q !== "") ? $book->search($q) : $book->readAll();
$edit  = ($page === "edit") ? $book->readOne((int)($_GET["id"] ?? 0)) : null;
if ($page === "edit" && !$edit) { $page = "list"; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Library Book Management System</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f2f4f8; margin: 0; padding: 20px; }
        .container { max-width: 950px; margin: auto; background: #fff; padding: 25px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
        .container.small { max-width: 450px; }
        h1 { margin-top: 0; color: #2c3e50; }
        .bar { display: flex; justify-content: space-between; margin-bottom: 15px; gap: 10px; flex-wrap: wrap; }
        .search { display: flex; gap: 6px; }
        input, select { padding: 8px; border: 1px solid #ccc; border-radius: 5px; }
        .form input, .form select { display: block; width: 100%; box-sizing: border-box; margin-bottom: 12px; }
        .form label { font-weight: bold; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background: #2c3e50; color: #fff; }
        .center { text-align: center; color: #888; }
        .actions { display: flex; gap: 5px; align-items: center; }
        .actions form { display: flex; gap: 5px; margin: 0; }
        .btn, button { padding: 7px 12px; border: none; border-radius: 5px; color: #fff; cursor: pointer; text-decoration: none; font-size: 13px; background: #2c3e50; }
        .green { background: #27ae60; } .blue { background: #2980b9; } .red { background: #c0392b; }
        .orange { background: #e67e22; } .gray { background: #7f8c8d; }
        .tag { padding: 3px 9px; border-radius: 12px; font-size: 12px; color: #fff; }
        .tag.Available { background: #27ae60; } .tag.Borrowed { background: #c0392b; }
        .error { color: #c0392b; font-weight: bold; }
    </style>
</head>
<body>

<?php if ($page === "list"): ?>
<!-- ================= PAGE: LIST (display + search + delete + status) ================= -->
<div class="container">
    <h1>Library Book Management System</h1>

    <div class="bar">
        <form method="GET" class="search">
            <input type="text" name="q" placeholder="Search by title or author" value="<?= e($q) ?>">
            <button type="submit">Search</button>
            <?php if ($q !== ""): ?><a href="<?= $self ?>" class="btn gray">Clear</a><?php endif; ?>
        </form>
        <a href="<?= $self ?>?page=add" class="btn green">+ Add Book</a>
    </div>

    <table>
        <tr>
            <th>ID</th><th>Title</th><th>Author</th><th>Category</th><th>Status</th><th>Actions</th>
        </tr>
        <?php if (count($books) === 0): ?>
            <tr><td colspan="6" class="center">No books found.</td></tr>
        <?php endif; ?>
        <?php foreach ($books as $b): ?>
        <tr>
            <td><?= $b["book_id"] ?></td>
            <td><?= e($b["title"]) ?></td>
            <td><?= e($b["author"]) ?></td>
            <td><?= e($b["category"]) ?></td>
            <td><span class="tag <?= $b["status"] ?>"><?= $b["status"] ?></span></td>
            <td class="actions">
                <a href="<?= $self ?>?page=edit&id=<?= $b["book_id"] ?>" class="btn blue">Edit</a>
                <form method="POST">
                    <input type="hidden" name="id" value="<?= $b["book_id"] ?>">
                    <input type="hidden" name="q" value="<?= e($q) ?>">
                    <button name="action" value="toggle" class="btn orange">
                        Mark <?= $b["status"] === "Available" ? "Borrowed" : "Available" ?>
                    </button>
                    <button name="action" value="delete" class="btn red"
                            onclick="return confirm('Delete this book?')">Delete</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<?php elseif ($page === "add"): ?>
<!-- ================= PAGE: ADD (create) ================= -->
<div class="container small">
    <h1>Add Book</h1>
    <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
    <form method="POST" class="form">
        <input type="hidden" name="action" value="add">
        <label>Title</label>
        <input type="text" name="title" value="<?= e($_POST["title"] ?? "") ?>" required>
        <label>Author</label>
        <input type="text" name="author" value="<?= e($_POST["author"] ?? "") ?>" required>
        <label>Category</label>
        <input type="text" name="category" value="<?= e($_POST["category"] ?? "") ?>" required>
        <label>Status</label>
        <select name="status">
            <option>Available</option>
            <option>Borrowed</option>
        </select>
        <button type="submit" class="btn green">Save Book</button>
        <a href="<?= $self ?>" class="btn gray">Cancel</a>
    </form>
</div>

<?php elseif ($page === "edit"): ?>
<!-- ================= PAGE: EDIT (update) ================= -->
<div class="container small">
    <h1>Edit Book #<?= $edit["book_id"] ?></h1>
    <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
    <form method="POST" class="form">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?= $edit["book_id"] ?>">
        <label>Title</label>
        <input type="text" name="title" value="<?= e($edit["title"]) ?>" required>
        <label>Author</label>
        <input type="text" name="author" value="<?= e($edit["author"]) ?>" required>
        <label>Category</label>
        <input type="text" name="category" value="<?= e($edit["category"]) ?>" required>
        <label>Status</label>
        <select name="status">
            <option <?= $edit["status"] === "Available" ? "selected" : "" ?>>Available</option>
            <option <?= $edit["status"] === "Borrowed" ? "selected" : "" ?>>Borrowed</option>
        </select>
        <button type="submit" class="btn blue">Update Book</button>
        <a href="<?= $self ?>" class="btn gray">Cancel</a>
    </form>
</div>
<?php endif; ?>

</body>
</html>
