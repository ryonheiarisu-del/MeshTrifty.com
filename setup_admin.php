<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if ($argc !== 3 || !preg_match('/^[A-Za-z0-9._-]{3,64}$/', $argv[1]) || trim($argv[2]) === '') {
    fwrite(STDERR, "Usage: php setup_admin.php admin \"Admin Name\"\n");
    exit(1);
}

$username = trim($argv[1]);
$name = trim($argv[2]);
if (mb_strlen($name, 'UTF-8') > 120) {
    fwrite(STDERR, "Admin name is too long.\n");
    exit(1);
}

fwrite(STDOUT, "Choose an admin password (at least 14 characters): ");
$password = rtrim((string)fgets(STDIN), "\r\n");
fwrite(STDOUT, "Confirm admin password: ");
$confirmation = rtrim((string)fgets(STDIN), "\r\n");
if (strlen($password) < 14 || !hash_equals($password, $confirmation)) {
    fwrite(STDERR, "Passwords must match and be at least 14 characters.\n");
    exit(1);
}

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn = new mysqli(
        getenv('MESHTRIFTY_DB_HOST') ?: 'localhost',
        getenv('MESHTRIFTY_DB_USER') ?: 'root',
        getenv('MESHTRIFTY_DB_PASSWORD') ?: '',
        getenv('MESHTRIFTY_DB_NAME') ?: 'meshtrifty'
    );
    $conn->set_charset('utf8mb4');
    if ((int)$conn->query('SELECT COUNT(*) AS count FROM admins')->fetch_assoc()['count'] > 0) {
        throw new RuntimeException('An admin already exists. The initial setup script only creates the first admin.');
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('INSERT INTO admins (name, username, password_hash) VALUES (?, ?, ?)');
    $stmt->bind_param('sss', $name, $username, $hash);
    $stmt->execute();
    $stmt->close();
    fwrite(STDOUT, "Admin account created. Sign in to /admin.php with username '$username'.\n");
} catch (Throwable $error) {
    fwrite(STDERR, "Could not create admin: " . $error->getMessage() . "\n");
    exit(1);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
    $password = $confirmation = '';
}
