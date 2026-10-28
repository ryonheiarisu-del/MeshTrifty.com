<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'httponly' => true,
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Strict',
]);
session_start();

function respond(array $body, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
}

function requireCsrf(): void
{
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $expected = $_SESSION['csrf_token'] ?? '';
    if (!is_string($provided) || !is_string($expected) || $expected === '' || !hash_equals($expected, $provided)) {
        respond(['status' => 'error', 'message' => 'Your session expired. Refresh and try again.'], 403);
    }
}

function requireAdmin(): void
{
    if (empty($_SESSION['admin_id'])) {
        respond(['status' => 'error', 'message' => 'Admin sign-in is required.'], 401);
    }
}

function requestData(): stdClass
{
    $data = json_decode(file_get_contents('php://input') ?: '{}');
    if (!$data instanceof stdClass) {
        respond(['status' => 'error', 'message' => 'Invalid JSON request.'], 400);
    }
    return $data;
}

function sendPasswordResetEmail(string $recipient, string $code): void
{
    $sender = getenv('MESHTRIFTY_MAIL_FROM');
    if (!$sender || !filter_var($sender, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Email recovery is not configured. Set MESHTRIFTY_MAIL_FROM and configure PHP SMTP for Apache.');
    }
    $subject = 'MESHTRIFTY password reset code';
    $message = "Your MESHTRIFTY password reset code is $code.\r\n"
        . "It expires in 5 minutes. If you did not request a password reset, you can ignore this email.\r\n";
    $headers = [
        'From: MESHTRIFTY <' . $sender . '>',
        'Reply-To: ' . $sender,
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: PHP/' . PHP_VERSION,
    ];
    if (!@mail($recipient, $subject, $message, implode("\r\n", $headers))) {
        error_log('MESHTRIFTY password reset email could not be handed to the configured mail transport.');
        throw new RuntimeException('The email provider could not send your code. Check the PHP SMTP configuration and try again.');
    }
}

function newCsrfToken(): string
{
    $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

function validString(mixed $value, int $maxLength): bool
{
    return is_string($value) && trim($value) !== '' && mb_strlen(trim($value), 'UTF-8') <= $maxLength;
}

function normalizeProduct(stdClass $product): array
{
    $name = trim((string)($product->name ?? ''));
    $note = trim((string)($product->note ?? ''));
    $category = trim((string)($product->category ?? ''));
    $tag = trim((string)($product->tag ?? ''));
    $image = trim((string)($product->image ?? ''));
    $price = filter_var($product->price ?? null, FILTER_VALIDATE_FLOAT);
    $stock = filter_var($product->stock ?? null, FILTER_VALIDATE_INT);
    $active = filter_var($product->active ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    $releaseInput = $product->release_at ?? null;
    $releaseAt = null;
    if ($releaseInput !== null && $releaseInput !== '') {
        if (!is_string($releaseInput) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,3})?Z$/', $releaseInput)) {
            respond(['status' => 'error', 'message' => 'Choose a valid release date and time.'], 422);
        }
        try {
            $releaseAt = (new DateTimeImmutable($releaseInput))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (Throwable) {
            respond(['status' => 'error', 'message' => 'Choose a valid release date and time.'], 422);
        }
    }
    if (!validString($name, 120) || mb_strlen($note, 'UTF-8') > 255 ||
        !in_array($category, ['Clothing', 'Shoes', 'Accessories'], true) ||
        mb_strlen($tag, 'UTF-8') > 40 || !filter_var($image, FILTER_VALIDATE_URL) ||
        !in_array(parse_url($image, PHP_URL_SCHEME), ['http', 'https'], true) ||
        $price === false || $price <= 0 || $price > 10000000 ||
        $stock === false || $stock < 0 || $stock > 1000000 || $active === null) {
        respond(['status' => 'error', 'message' => 'Check the product details and try again.'], 422);
    }
    return [$name, $note, $price, $category, $tag, $image, $stock, (int)$active, $releaseAt];
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
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $data = $method === 'POST' && str_contains($contentType, 'application/json') ? requestData() : new stdClass();
    $action = $method === 'GET' ? (string)($_GET['action'] ?? '') : (string)($data->action ?? $_POST['action'] ?? '');

    if ($method === 'GET' && $action === 'session') {
        $user = null;
        if (!empty($_SESSION['account_id'])) {
            $stmt = $conn->prepare('SELECT id, name, email, profile_photo FROM accounts WHERE id = ?');
            $stmt->bind_param('i', $_SESSION['account_id']);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc() ?: null;
            $stmt->close();
            if ($user) {
                $user['profile_photo'] = $user['profile_photo'] ? 'api.php?action=profile_photo' : null;
            }
        }
        respond(['status' => 'success', 'user' => $user, 'csrf_token' => newCsrfToken()]);
    }

    if ($method === 'GET' && $action === 'profile_photo') {
        if (empty($_SESSION['account_id'])) {
            http_response_code(401);
            exit;
        }
        $accountId = (int)$_SESSION['account_id'];
        $stmt = $conn->prepare('SELECT profile_photo FROM accounts WHERE id = ?');
        $stmt->bind_param('i', $accountId);
        $stmt->execute();
        $photo = $stmt->get_result()->fetch_column();
        $stmt->close();
        if (!$photo || !preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $photo)) {
            http_response_code(404);
            exit;
        }
        $path = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'meshtrifty_profiles' . DIRECTORY_SEPARATOR . $photo;
        if (!is_file($path)) {
            http_response_code(404);
            exit;
        }
        $extension = strtolower(pathinfo($photo, PATHINFO_EXTENSION));
        header('Content-Type: ' . ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][$extension]);
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    if ($method === 'GET' && $action === 'account_orders') {
        if (empty($_SESSION['account_id'])) {
            respond(['status' => 'error', 'message' => 'Sign in to view your orders.'], 401);
        }
        $accountId = (int)$_SESSION['account_id'];
        $orders = $conn->prepare('SELECT id, order_number, total, status, created_at FROM orders WHERE account_id = ? ORDER BY created_at DESC');
        $orders->bind_param('i', $accountId);
        $orders->execute();
        $orderRows = $orders->get_result()->fetch_all(MYSQLI_ASSOC);
        $orders->close();
        $items = $conn->prepare('SELECT product_name, size, quantity, unit_price FROM order_items WHERE order_id = ?');
        foreach ($orderRows as &$order) {
            $orderId = (int)$order['id'];
            $items->bind_param('i', $orderId);
            $items->execute();
            $order['items'] = $items->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        unset($order);
        $items->close();
        respond(['status' => 'success', 'orders' => $orderRows]);
    }

    if ($method === 'GET' && $action === 'products') {
        $result = $conn->query("SELECT id, name, note, price, category, tag, image, stock, DATE_FORMAT(release_at, '%Y-%m-%dT%H:%i:%sZ') AS release_at FROM products WHERE active = 1 AND (release_at IS NULL OR release_at <= UTC_TIMESTAMP()) ORDER BY id");
        respond(['status' => 'success', 'products' => $result->fetch_all(MYSQLI_ASSOC)]);
    }

    if ($method === 'GET' && $action === 'upcoming_products') {
        $result = $conn->query("SELECT id, name, note, price, category, tag, image, DATE_FORMAT(release_at, '%Y-%m-%dT%H:%i:%sZ') AS release_at FROM products WHERE active = 1 AND stock > 0 AND release_at > UTC_TIMESTAMP() ORDER BY release_at, id");
        respond(['status' => 'success', 'products' => $result->fetch_all(MYSQLI_ASSOC)]);
    }

    if ($method === 'POST') {
        requireCsrf();

        if ($action === 'register' || $action === 'login') {
            $email = strtolower(trim((string)($data->email ?? '')));
            $password = (string)($data->password ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254 || $password === '' || strlen($password) > 4096) {
                respond(['status' => 'error', 'message' => 'Enter a valid email and password.'], 422);
            }
            if ($action === 'register') {
                $name = trim((string)($data->name ?? ''));
                if (!validString($name, 120) || strlen($password) < 12 || strlen($password) > 4096) {
                    respond(['status' => 'error', 'message' => 'Enter your name and a password with at least 12 characters.'], 422);
                }
                $hash = password_hash($password, PASSWORD_DEFAULT);
                try {
                    $stmt = $conn->prepare('INSERT INTO accounts (name, email, password_hash) VALUES (?, ?, ?)');
                    $stmt->bind_param('sss', $name, $email, $hash);
                    $stmt->execute();
                    $accountId = $conn->insert_id;
                    $stmt->close();
                } catch (mysqli_sql_exception $error) {
                    if ($error->getCode() === 1062) {
                        respond(['status' => 'error', 'message' => 'An account with this email already exists. Try signing in.'], 409);
                    }
                    throw $error;
                }
            } else {
                $stmt = $conn->prepare('SELECT id, name, email, profile_photo, password_hash FROM accounts WHERE email = ? LIMIT 1');
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $account = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$account || !password_verify($password, $account['password_hash'])) {
                    respond(['status' => 'error', 'message' => 'Email or password does not match an account.'], 401);
                }
                $accountId = (int)$account['id'];
                $name = $account['name'];
                $email = $account['email'];
                $profilePhoto = $account['profile_photo'];
            }
            session_regenerate_id(true);
            unset($_SESSION['admin_id']);
            $_SESSION['account_id'] = $accountId;
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            respond(['status' => 'success', 'user' => ['id' => $accountId, 'name' => $name, 'email' => $email, 'profile_photo' => !empty($profilePhoto) ? 'api.php?action=profile_photo' : null], 'csrf_token' => $_SESSION['csrf_token']]);
        }

        if ($action === 'request_password_reset') {
            $email = strtolower(trim((string)($data->email ?? '')));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
                respond(['status' => 'error', 'message' => 'Enter a valid email address.'], 422);
            }
            $stmt = $conn->prepare('SELECT id FROM accounts WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $accountId = $stmt->get_result()->fetch_column();
            $stmt->close();
            if (!$accountId) {
                respond(['status' => 'success', 'message' => 'If an account matches that email address, a code has been sent.']);
            }

            $requestIp = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            $rate = $conn->prepare('SELECT SUM(requested_at > UTC_TIMESTAMP() - INTERVAL 60 SECOND) AS recent_account, SUM(requested_at > UTC_TIMESTAMP() - INTERVAL 1 HOUR) AS hourly_account, (SELECT COUNT(*) FROM password_reset_otps WHERE request_ip_hash = ? AND requested_at > UTC_TIMESTAMP() - INTERVAL 1 HOUR) AS hourly_ip FROM password_reset_otps WHERE account_id = ?');
            $rate->bind_param('si', $requestIp, $accountId);
            $rate->execute();
            $limits = $rate->get_result()->fetch_assoc();
            $rate->close();
            if ((int)($limits['recent_account'] ?? 0) > 0 || (int)($limits['hourly_account'] ?? 0) >= 5 || (int)($limits['hourly_ip'] ?? 0) >= 10) {
                respond(['status' => 'success', 'message' => 'If an account matches that email address, a code has been sent.']);
            }

            $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $codeHash = password_hash($code, PASSWORD_DEFAULT);
            $conn->query('UPDATE password_reset_otps SET used_at = UTC_TIMESTAMP() WHERE account_id = ' . (int)$accountId . ' AND used_at IS NULL');
            $challenge = $conn->prepare('INSERT INTO password_reset_otps (account_id, otp_hash, request_ip_hash, requested_at, expires_at) VALUES (?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP() + INTERVAL 5 MINUTE)');
            $challenge->bind_param('iss', $accountId, $codeHash, $requestIp);
            $challenge->execute();
            $challenge->close();
            try {
                sendPasswordResetEmail($email, $code);
            } catch (Throwable $error) {
                $invalidate = $conn->prepare('UPDATE password_reset_otps SET used_at = UTC_TIMESTAMP() WHERE account_id = ? AND used_at IS NULL');
                $invalidate->bind_param('i', $accountId);
                $invalidate->execute();
                $invalidate->close();
                respond(['status' => 'error', 'message' => $error->getMessage()], 503);
            } finally {
                $code = $codeHash = '';
            }
            respond(['status' => 'success', 'message' => 'If an account matches that email address, a code has been sent.']);
        }

        if ($action === 'reset_password') {
            $email = strtolower(trim((string)($data->email ?? '')));
            $code = trim((string)($data->code ?? ''));
            $password = (string)($data->password ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254 ||
                !preg_match('/^\d{6}$/', $code) || strlen($password) < 12 || strlen($password) > 4096) {
                respond(['status' => 'error', 'message' => 'Enter a valid email address, 6-digit code, and a password of at least 12 characters.'], 422);
            }
            $conn->begin_transaction();
            try {
                $accountQuery = $conn->prepare('SELECT id FROM accounts WHERE email = ? FOR UPDATE');
                $accountQuery->bind_param('s', $email);
                $accountQuery->execute();
                $accountId = $accountQuery->get_result()->fetch_column();
                $accountQuery->close();
                if (!$accountId) {
                    throw new DomainException('The code is invalid or expired. Request a new code and try again.');
                }
                $challengeQuery = $conn->prepare('SELECT id, otp_hash, attempts FROM password_reset_otps WHERE account_id = ? AND used_at IS NULL AND expires_at > UTC_TIMESTAMP() ORDER BY requested_at DESC LIMIT 1 FOR UPDATE');
                $challengeQuery->bind_param('i', $accountId);
                $challengeQuery->execute();
                $challenge = $challengeQuery->get_result()->fetch_assoc();
                $challengeQuery->close();
                if (!$challenge || (int)$challenge['attempts'] >= 5 || !password_verify($code, $challenge['otp_hash'])) {
                    if ($challenge && (int)$challenge['attempts'] < 5) {
                        $attempt = $conn->prepare('UPDATE password_reset_otps SET attempts = attempts + 1 WHERE id = ?');
                        $attempt->bind_param('i', $challenge['id']);
                        $attempt->execute();
                        $attempt->close();
                    }
                    throw new DomainException('The code is invalid or expired. Request a new code and try again.');
                }
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $updateAccount = $conn->prepare('UPDATE accounts SET password_hash = ? WHERE id = ?');
                $updateAccount->bind_param('si', $newHash, $accountId);
                $updateAccount->execute();
                $updateAccount->close();
                $useCodes = $conn->prepare('UPDATE password_reset_otps SET used_at = UTC_TIMESTAMP() WHERE account_id = ? AND used_at IS NULL');
                $useCodes->bind_param('i', $accountId);
                $useCodes->execute();
                $useCodes->close();
                $conn->commit();
                respond(['status' => 'success', 'message' => 'Your password has been reset. You can now sign in.']);
            } catch (DomainException $error) {
                $conn->commit();
                respond(['status' => 'error', 'message' => $error->getMessage()], 400);
            } catch (Throwable $error) {
                $conn->rollback();
                throw $error;
            }
        }

        if ($action === 'upload_profile_photo') {
            if (empty($_SESSION['account_id'])) {
                respond(['status' => 'error', 'message' => 'Sign in to update your profile photo.'], 401);
            }
            $file = $_FILES['photo'] ?? null;
            if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK ||
                !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name']) ||
                ($file['size'] ?? 0) < 1 || ($file['size'] ?? 0) > 2 * 1024 * 1024) {
                respond(['status' => 'error', 'message' => 'Choose an image up to 2 MB.'], 422);
            }
            $imageInfo = @getimagesize($file['tmp_name']);
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            if (!$imageInfo || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) ||
                $imageInfo[0] < 1 || $imageInfo[1] < 1 || $imageInfo[0] * $imageInfo[1] > 20000000) {
                respond(['status' => 'error', 'message' => 'Use a valid JPEG, PNG, or WebP image up to 20 megapixels.'], 422);
            }
            $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime];
            $directory = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'meshtrifty_profiles';
            if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw new RuntimeException('Could not create private profile photo storage.');
            }
            $filename = bin2hex(random_bytes(16)) . '.' . $extension;
            $destination = $directory . DIRECTORY_SEPARATOR . $filename;
            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                respond(['status' => 'error', 'message' => 'The profile photo could not be saved.'], 500);
            }
            $accountId = (int)$_SESSION['account_id'];
            $oldQuery = $conn->prepare('SELECT profile_photo FROM accounts WHERE id = ?');
            $oldQuery->bind_param('i', $accountId);
            $oldQuery->execute();
            $oldPhoto = $oldQuery->get_result()->fetch_column();
            $oldQuery->close();
            $updatePhoto = $conn->prepare('UPDATE accounts SET profile_photo = ? WHERE id = ?');
            $updatePhoto->bind_param('si', $filename, $accountId);
            $updatePhoto->execute();
            $updatePhoto->close();
            if ($oldPhoto && preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $oldPhoto)) {
                $oldPath = $directory . DIRECTORY_SEPARATOR . $oldPhoto;
                if (is_file($oldPath)) {
                    unlink($oldPath);
                }
            }
            respond(['status' => 'success', 'profile_photo' => 'api.php?action=profile_photo']);
        }

        if ($action === 'logout') {
            unset($_SESSION['account_id'], $_SESSION['admin_id']);
            session_regenerate_id(true);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            respond(['status' => 'success', 'csrf_token' => $_SESSION['csrf_token']]);
        }

        if ($action === 'create_order') {
            if (empty($_SESSION['account_id'])) {
                respond(['status' => 'error', 'message' => 'Sign in before placing an order.'], 401);
            }
            foreach (['name' => 120, 'email' => 254, 'address' => 255, 'city' => 120, 'postal' => 20] as $field => $limit) {
                if (!validString($data->$field ?? null, $limit)) {
                    respond(['status' => 'error', 'message' => 'Complete all delivery details and try again.'], 422);
                }
            }
            if (!filter_var($data->email, FILTER_VALIDATE_EMAIL) ||
                !in_array($data->payment ?? '', ['card-payment', 'cash-on-delivery'], true) ||
                !is_array($data->items ?? null) || count($data->items) < 1 || count($data->items) > 50) {
                respond(['status' => 'error', 'message' => 'The order details are invalid.'], 422);
            }
            $conn->begin_transaction();
            try {
                $orderItems = [];
                $total = 0.0;
                $productQuery = $conn->prepare('SELECT name, price, category, stock FROM products WHERE id = ? AND active = 1 AND (release_at IS NULL OR release_at <= UTC_TIMESTAMP()) FOR UPDATE');
                foreach ($data->items as $item) {
                    $productId = filter_var($item->product_id ?? null, FILTER_VALIDATE_INT);
                    $quantity = filter_var($item->quantity ?? null, FILTER_VALIDATE_INT);
                    $size = trim((string)($item->size ?? ''));
                    if ($productId === false || $quantity === false || $quantity < 1 || $quantity > 20 || $size === '') {
                        throw new DomainException('The order contains an invalid item.');
                    }
                    $productQuery->bind_param('i', $productId);
                    $productQuery->execute();
                    $product = $productQuery->get_result()->fetch_assoc();
                    if (!$product || (int)$product['stock'] < $quantity) {
                        throw new DomainException('A product is no longer available in the requested quantity.');
                    }
                    $validSizes = match ($product['category']) {
                        'Clothing' => ['SMALL', 'MEDIUM', 'LARGE', 'XL', 'XXL'],
                        'Shoes' => ['39', '40', '41', '42', '43', '44', '45'],
                        'Accessories' => ['MEDIUM', 'LARGE'],
                        default => [],
                    };
                    if (!in_array($size, $validSizes, true)) {
                        throw new DomainException('Choose a valid size for each product.');
                    }
                    $unitPrice = (float)$product['price'];
                    $total += $unitPrice * $quantity;
                    if ($total > 99999999.99) {
                        throw new DomainException('The order total is too high to process.');
                    }
                    $orderItems[] = ['product_id' => $productId, 'name' => $product['name'], 'size' => $size, 'quantity' => $quantity, 'price' => $unitPrice];
                }
                $productQuery->close();
                $orderNumber = 'MT-' . strtoupper(bin2hex(random_bytes(5)));
                $accountId = (int)$_SESSION['account_id'];
                $name = trim($data->name);
                $email = strtolower(trim($data->email));
                $address = trim($data->address);
                $city = trim($data->city);
                $postal = trim($data->postal);
                $payment = (string)$data->payment;
                $stmt = $conn->prepare('INSERT INTO orders (order_number, account_id, customer_name, email, address, city, postal, payment_method, total, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "Pending")');
                $stmt->bind_param('sissssssd', $orderNumber, $accountId, $name, $email, $address, $city, $postal, $payment, $total);
                $stmt->execute();
                $orderId = $conn->insert_id;
                $stmt->close();
                $insertItem = $conn->prepare('INSERT INTO order_items (order_id, product_id, product_name, size, quantity, unit_price) VALUES (?, ?, ?, ?, ?, ?)');
                $decrement = $conn->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
                foreach ($orderItems as $item) {
                    $insertItem->bind_param('iissid', $orderId, $item['product_id'], $item['name'], $item['size'], $item['quantity'], $item['price']);
                    $insertItem->execute();
                    $decrement->bind_param('iii', $item['quantity'], $item['product_id'], $item['quantity']);
                    $decrement->execute();
                    if ($decrement->affected_rows !== 1) {
                        throw new DomainException('A product is no longer available in the requested quantity.');
                    }
                }
                $insertItem->close();
                $decrement->close();
                $conn->commit();
                respond(['status' => 'success', 'order' => ['id' => $orderNumber, 'date' => date(DATE_ATOM), 'name' => $name, 'email' => $email, 'address' => $address, 'city' => $city, 'postal' => $postal, 'payment' => $payment === 'cash-on-delivery' ? 'Cash on delivery' : 'Card payment', 'total' => $total, 'items' => array_map(static fn(array $item): array => ['name' => $item['name'], 'size' => $item['size'], 'quantity' => $item['quantity'], 'price' => $item['price']], $orderItems)]]);
            } catch (DomainException $error) {
                $conn->rollback();
                respond(['status' => 'error', 'message' => $error->getMessage()], 409);
            } catch (Throwable $error) {
                $conn->rollback();
                throw $error;
            }
        }

        if ($action === 'admin_login') {
            $username = trim((string)($data->username ?? ''));
            $password = (string)($data->password ?? '');
            if (!preg_match('/^[A-Za-z0-9._-]{3,64}$/', $username)) {
                respond(['status' => 'error', 'message' => 'Enter a valid admin username.'], 422);
            }
            $stmt = $conn->prepare('SELECT id, name, username, password_hash FROM admins WHERE username = ? LIMIT 1');
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $admin = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($password === '' || strlen($password) > 4096 || !$admin || !password_verify($password, $admin['password_hash'])) {
                respond(['status' => 'error', 'message' => 'Username or password is incorrect.'], 401);
            }
            session_regenerate_id(true);
            unset($_SESSION['account_id']);
            $_SESSION['admin_id'] = (int)$admin['id'];
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            respond(['status' => 'success', 'admin' => ['name' => $admin['name'], 'username' => $admin['username']], 'csrf_token' => $_SESSION['csrf_token']]);
        }

        if ($action === 'admin_logout') {
            requireAdmin();
            unset($_SESSION['admin_id']);
            session_regenerate_id(true);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            respond(['status' => 'success']);
        }

        requireAdmin();

        if ($action === 'save_product') {
            [$name, $note, $price, $category, $tag, $image, $stock, $active, $releaseAt] = normalizeProduct($data);
            if (isset($data->id)) {
                $id = filter_var($data->id, FILTER_VALIDATE_INT);
                if ($id === false || $id < 1) {
                    respond(['status' => 'error', 'message' => 'Invalid product.'], 422);
                }
                $stmt = $conn->prepare('UPDATE products SET name=?, note=?, price=?, category=?, tag=?, image=?, stock=?, active=?, release_at=? WHERE id=?');
                $stmt->bind_param('ssdsssiisi', $name, $note, $price, $category, $tag, $image, $stock, $active, $releaseAt, $id);
            } else {
                $stmt = $conn->prepare('INSERT INTO products (name, note, price, category, tag, image, stock, active, release_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('ssdsssiis', $name, $note, $price, $category, $tag, $image, $stock, $active, $releaseAt);
            }
            $stmt->execute();
            $stmt->close();
            respond(['status' => 'success']);
        }

        if ($action === 'delete_product') {
            $id = filter_var($data->id ?? null, FILTER_VALIDATE_INT);
            if ($id === false || $id < 1) {
                respond(['status' => 'error', 'message' => 'Invalid product.'], 422);
            }
            $stmt = $conn->prepare('UPDATE products SET active = 0 WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            respond(['status' => 'success']);
        }

        if ($action === 'update_order_status') {
            $id = filter_var($data->id ?? null, FILTER_VALIDATE_INT);
            $status = (string)($data->status ?? '');
            if ($id === false || $id < 1 || !in_array($status, ['Pending', 'Processing', 'Shipped', 'Completed', 'Cancelled'], true)) {
                respond(['status' => 'error', 'message' => 'Invalid order update.'], 422);
            }
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare('SELECT status FROM orders WHERE id = ? FOR UPDATE');
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $order = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$order) {
                    throw new DomainException('Order not found.');
                }
                if ($order['status'] === 'Cancelled' && $status !== 'Cancelled') {
                    throw new DomainException('Cancelled orders cannot be reopened.');
                }
                if ($status === 'Cancelled' && $order['status'] !== 'Cancelled') {
                    $items = $conn->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ? AND product_id IS NOT NULL');
                    $items->bind_param('i', $id);
                    $items->execute();
                    $rows = $items->get_result()->fetch_all(MYSQLI_ASSOC);
                    $items->close();
                    foreach ($rows as $item) {
                        $quantity = (int)$item['quantity'];
                        $stock = $conn->prepare('UPDATE products SET stock = GREATEST(0, stock + ?) WHERE id = ?');
                        $stock->bind_param('ii', $quantity, $item['product_id']);
                        $stock->execute();
                        $stock->close();
                    }
                }
                $stmt = $conn->prepare('UPDATE orders SET status = ? WHERE id = ?');
                $stmt->bind_param('si', $status, $id);
                $stmt->execute();
                $stmt->close();
                $conn->commit();
                respond(['status' => 'success']);
            } catch (DomainException $error) {
                $conn->rollback();
                respond(['status' => 'error', 'message' => $error->getMessage()], $error->getMessage() === 'Order not found.' ? 404 : 409);
            } catch (Throwable $error) {
                $conn->rollback();
                throw $error;
            }
        }

        respond(['status' => 'error', 'message' => 'Unknown action.'], 404);
    }

    if ($method === 'GET' && $action === 'admin_session') {
        if (empty($_SESSION['admin_id'])) {
            respond(['status' => 'success', 'admin' => null, 'csrf_token' => newCsrfToken()]);
        }
        $stmt = $conn->prepare('SELECT name, username FROM admins WHERE id = ?');
        $stmt->bind_param('i', $_SESSION['admin_id']);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        respond(['status' => 'success', 'admin' => $admin, 'csrf_token' => newCsrfToken()]);
    }

    if ($method === 'GET' && str_starts_with($action, 'admin_')) {
        requireAdmin();
        if ($action === 'admin_products') {
            $result = $conn->query("SELECT id, name, note, price, category, tag, image, stock, active, DATE_FORMAT(release_at, '%Y-%m-%dT%H:%i:%sZ') AS release_at FROM products ORDER BY id DESC");
            respond(['status' => 'success', 'products' => $result->fetch_all(MYSQLI_ASSOC)]);
        }
        if ($action === 'admin_accounts') {
            $result = $conn->query('SELECT id, name, email, created_at FROM accounts ORDER BY created_at DESC');
            respond(['status' => 'success', 'accounts' => $result->fetch_all(MYSQLI_ASSOC)]);
        }
        if ($action === 'admin_orders') {
            $orders = $conn->query('SELECT id, order_number, customer_name, email, address, city, postal, payment_method, total, status, created_at FROM orders ORDER BY created_at DESC');
            $result = [];
            $items = $conn->prepare('SELECT product_name, size, quantity, unit_price FROM order_items WHERE order_id = ?');
            foreach ($orders->fetch_all(MYSQLI_ASSOC) as $order) {
                $items->bind_param('i', $order['id']);
                $items->execute();
                $order['items'] = $items->get_result()->fetch_all(MYSQLI_ASSOC);
                $result[] = $order;
            }
            $items->close();
            respond(['status' => 'success', 'orders' => $result]);
        }
    }

    respond(['status' => 'error', 'message' => 'Unknown action.'], 404);
} catch (Throwable $error) {
    error_log('MESHTRIFTY API error: ' . $error->getMessage());
    respond(['status' => 'error', 'message' => 'The server could not complete your request. Check the database setup and try again.'], 500);
}
