  <?php
// Turn on error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/*
 * ==========================================================
 *  Library Book Management System (PHP + MySQL, OOP)
 *  
 * CLASS 1: Database
 * Handles MySQL connection with safe exception handling.
 * --------------------------------------------------------- */
class Database
{
    private $host = "sql311.infinityfree.com";
    private $user = "if0_43265625";          
    private $pass = "";               
    private $name = "if0_43265625_booklibrary";
    private $conn;

    public function getConnection()
    {
        // Enable strict error reporting for mysqli to catch connection issues cleanly
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            $this->conn = new mysqli(
                $this->host,
                $this->user,
                $this->pass,
                $this->name
            );

            // Create the books table if it does not exist
            $this->conn->query(
                "CREATE TABLE IF NOT EXISTS books (
                    book_id INT AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(150) NOT NULL,
                    author VARCHAR(100) NOT NULL,
                    category VARCHAR(80) NOT NULL,
                    status ENUM('Available','Borrowed') NOT NULL DEFAULT 'Available'
                )"
            );

            return $this->conn;

        } catch (Exception $e) {
            // Display a clean error box instead of crashing with a 500 Server Error
            echo "<div style='font-family: sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 8px;'>";
            echo "<h3 style='margin-top:0;'>Database Connection Failed</h3>";
            echo "<p><strong>Error Details:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p>Please double-check your InfinityFree database host, username, and password in the Database class.</p>";
            echo "</div>";
            exit;
        }
    }
}

/* ---------------------------------------------------------
 * CLASS 2: Book
 * Holds book data (private properties) and all CRUD methods.
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

    public function setId($id)             { $this->id = (int)$id; }
    public function setTitle($title)       { $this->title = trim($title); }
    public function setAuthor($author)     { $this->author = trim($author); }
    public function setCategory($category) { $this->category = trim($category); }
    public function setStatus($status)
    {
        $this->status = ($status === "Borrowed") ? "Borrowed" : "Available";
    }
    public function getTitle()    { return $this->title; }
    public function getAuthor()   { return $this->author; }
    public function getCategory() { return $this->category; }

    public function create()
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO books (title, author, category, status) VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param("ssss", $this->title, $this->author, $this->category, $this->status);
        return $stmt->execute();
    }

    public function readAll()
    {
        $result = $this->conn->query("SELECT * FROM books ORDER BY book_id DESC");
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function readOne($id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM books WHERE book_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

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

    public function update()
    {
        $stmt = $this->conn->prepare(
            "UPDATE books SET title = ?, author = ?, category = ?, status = ? WHERE book_id = ?"
        );
        $stmt->bind_param("ssssi", $this->title, $this->author, $this->category, $this->status, $this->id);
        return $stmt->execute();
    }

    public function delete($id)
    {
        $stmt = $this->conn->prepare("DELETE FROM books WHERE book_id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

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
$book = new Book($db->getConnection());

$self  = basename($_SERVER["PHP_SELF"]);
$page  = $_GET["page"] ?? "list";
$error = "";

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

function e($text) { return htmlspecialchars((string)$text); }

$q     = trim($_GET["q"] ?? "");
$books = ($q !== "") ? $book->search($q) : $book->readAll();
$edit  = ($page === "edit") ? $book->readOne((int)($_GET["id"] ?? 0)) : null;
if ($page === "edit" && !$edit) { $page = "list"; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Library Book Management System</title>
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; 
            background: #f2f4f8; 
            margin: 0; 
            padding: 15px; 
            color: #333;
        }
        .container { 
            max-width: 950px; 
            margin: auto; 
            background: #fff; 
            padding: 20px; 
            border-radius: 10px; 
            box-shadow: 0 2px 8px rgba(0,0,0,.08); 
        }
        .container.small { max-width: 480px; }
        h1 { margin-top: 0; color: #2c3e50; font-size: 24px; text-align: left; }
        
        .bar { 
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            margin-bottom: 20px; 
            gap: 10px; 
            flex-wrap: wrap; 
        }
        .search { 
            display: flex; 
            align-items: center;
            gap: 6px; 
            flex: 1 1 300px; 
        }
        .search input[type="text"] {
            flex: 1;
            min-width: 0;
            height: 42px;
            margin: 0;
        }
        .search button {
            height: 42px;
            margin: 0;
            white-space: nowrap;
        }
        
        input, select { 
            padding: 10px 12px; 
            border: 1px solid #ccc; 
            border-radius: 6px; 
            font-size: 14px;
            outline: none;
        }
        input:focus, select:focus {
            border-color: #2980b9;
        }
        .form input, .form select { 
            display: block; 
            width: 100%; 
            margin-top: 5px;
            margin-bottom: 16px; 
        }
        .form label { font-weight: 600; font-size: 14px; color: #2c3e50; }
        .form-actions { display: flex; gap: 10px; margin-top: 10px; }
        .form-actions .btn { flex: 1; text-align: center; }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px 10px; border-bottom: 1px solid #eee; text-align: left; }
        th { background: #2c3e50; color: #fff; font-weight: 600; font-size: 14px; }
        .center { text-align: center; color: #888; padding: 20px !important; }
        
        .actions { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
        .actions form { display: flex; gap: 6px; margin: 0; flex-wrap: wrap; }
        .btn, button { 
            display: inline-block;
            padding: 8px 12px; 
            border: none; 
            border-radius: 6px; 
            color: #fff; 
            cursor: pointer; 
            text-decoration: none; 
            font-size: 13px; 
            font-weight: 600;
            background: #2c3e50; 
            text-align: center;
            transition: opacity 0.2s ease;
        }
        .btn:hover, button:hover { opacity: 0.9; }
        .add-btn { text-align: center; height: 42px; display: inline-flex; align-items: center; justify-content: center; }
        .green { background: #27ae60; } .blue { background: #2980b9; } .red { background: #c0392b; }
        .orange { background: #e67e22; } .gray { background: #7f8c8d; }
        
        .tag { padding: 4px 10px; border-radius: 12px; font-size: 12px; color: #fff; font-weight: bold; display: inline-block; }
        .tag.Available { background: #27ae60; } .tag.Borrowed { background: #c0392b; }
        .error { color: #c0392b; font-weight: bold; margin-bottom: 15px; }

        @media (max-width: 768px) {
            body { padding: 10px; }
            .container { padding: 15px; }
            h1 { font-size: 20px; }
            .bar { flex-direction: column; align-items: stretch; }
            .search { width: 100%; }
            .add-btn { width: 100%; height: auto; padding: 10px; }

            table, thead, tbody, th, td, tr { display: block; }
            thead tr { position: absolute; top: -9999px; left: -9999px; }
            tr { 
                background: #fff;
                border: 1px solid #e2e8f0; 
                border-radius: 8px; 
                margin-bottom: 12px; 
                padding: 10px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            }
            td { 
                border: none;
                border-bottom: 1px solid #f0f0f0; 
                position: relative; 
                padding: 8px 0 8px 45%; 
                text-align: right;
                min-height: 20px;
            }
            td:last-child { border-bottom: none; padding-bottom: 0; }
            
            td.center {
                padding: 20px !important;
                text-align: center !important;
                padding-left: 0 !important;
            }
            td.center::before { content: "" !important; display: none !important; }

            td:not(.center)::before { 
                position: absolute; 
                left: 0; 
                width: 40%; 
                padding-right: 10px; 
                white-space: nowrap; 
                text-align: left; 
                font-weight: bold; 
                color: #2c3e50;
                font-size: 13px;
            }
            
            td:nth-of-type(1):not(.center)::before { content: "ID"; }
            td:nth-of-type(2):not(.center)::before { content: "Title"; }
            td:nth-of-type(3)::not(.center)::before { content: "Author"; }
            td:nth-of-type(4)::not(.center)::before { content: "Category"; }
            td:nth-of-type(5)::not(.center)::before { content: "Status"; }
            td:nth-of-type(6)::not(.center)::before { content: "Actions"; }
            
            .actions { justify-content: flex-end; width: 100%; margin-top: 5px; }
            .actions form { width: 100%; justify-content: flex-end; }
            .actions .btn, .actions button { flex: 1; padding: 8px 6px; font-size: 12px; }
        }
    </style>
</head>
<body>

<?php if ($page === "list"): ?>
<div class="container">
    <h1>Library Book Management System</h1>

    <div class="bar">
        <form method="GET" class="search">
            <input type="text" name="q" placeholder="Search by title or author" value="<?= e($q) ?>">
            <button type="submit">Search</button>
            <?php if ($q !== ""): ?><a href="<?= $self ?>" class="btn gray add-btn" style="height:42px;">Clear</a><?php endif; ?>
        </form>
        <a href="<?= $self ?>?page=add" class="btn green add-btn">+ Add Book</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th><th>Title</th><th>Author</th><th>Category</th><th>Status</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
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
        </tbody>
    </table>
</div>

<?php elseif ($page === "add"): ?>
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
        <div class="form-actions">
            <button type="submit" class="btn green">Save Book</button>
            <a href="<?= $self ?>" class="btn gray">Cancel</a>
        </div>
    </form>
</div>

<?php elseif ($page === "edit"): ?>
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
        <div class="form-actions">
            <button type="submit" class="btn blue">Update Book</button>
            <a href="<?= $self ?>" class="btn gray">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

</body>
</html>
