<?php
declare(strict_types=1);

session_start();
require __DIR__ . '/db.php';

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function isAjaxRequest(): bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}

function respondWithJson(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function redirectBack(): never
{
    header('Location: index.php');
    exit;
}

function returnErrors(array $errors, string $email, string $name): never
{
    if (isAjaxRequest()) {
        respondWithJson(['success' => false, 'errors' => $errors], 422);
    }

    $_SESSION['errors'] = $errors;
    $_SESSION['old_email'] = $email;
    $_SESSION['old_name'] = $name;
    redirectBack();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'logout') {
        unset($_SESSION['welcome']);
        if (isAjaxRequest()) {
            respondWithJson(['success' => true, 'message' => 'Signed out.']);
        }
        redirectBack();
    }

    $errors = [];
    $email = trim((string) ($_POST['email'] ?? ''));
    $name = trim((string) ($_POST['name'] ?? ''));

    if ($action === 'register') {
        $password = (string) ($_POST['password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($name === '') {
            $errors[] = 'Name is required.';
        }
        if ($email === '') {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }
        if ($password === '') {
            $errors[] = 'Password is required.';
        }
        if ($confirmPassword === '') {
            $errors[] = 'Confirm password is required.';
        } elseif ($password !== $confirmPassword) {
            $errors[] = 'Password and confirm password must be the same.';
        }

        if ($errors !== []) {
            returnErrors($errors, $email, $name);
        }

        try {
            $check = $pdo->prepare('SELECT user_id FROM `user` WHERE email = :email LIMIT 1');
            $check->execute(['email' => $email]);
            if ($check->fetch()) {
                returnErrors(['Email Already Exists'], $email, $name);
            }

            $insert = $pdo->prepare(
                'INSERT INTO `user` (email, name, password) VALUES (:email, :name, :password)'
            );
            $insert->execute([
                'email' => $email,
                'name' => $name,
                'password' => md5($password),
            ]);

            $_SESSION['welcome'] = $name;
            if (isAjaxRequest()) {
                respondWithJson(['success' => true, 'message' => 'Welcome ' . $name . '!']);
            }
            redirectBack();
        } catch (PDOException $exception) {
            // The unique index is the final safeguard if simultaneous requests
            if ($exception->getCode() === '23000') {
                returnErrors(['Email Already Exists'], $email, $name);
            }
            returnErrors(
                ['Could not create the account. Check the database connection and try again.'],
                $email,
                $name
            );
        }
    } elseif ($action === 'login') {
        $loginPassword = (string) ($_POST['password'] ?? '');

        if ($email === '') {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }
        if ($loginPassword === '') {
            $errors[] = 'Password is required.';
        }

        if ($errors !== []) {
            returnErrors($errors, $email, '');
        }

        $query = $pdo->prepare('SELECT name, password FROM `user` WHERE email = :email LIMIT 1');
        $query->execute(['email' => $email]);
        $account = $query->fetch();

        // password_verify also allows accounts created 
        $passwordMatches = $account && (
            hash_equals((string) $account['password'], md5($loginPassword))
            || password_verify($loginPassword, (string) $account['password'])
        );

        if (!$passwordMatches) {
            returnErrors(['Email or password is incorrect.'], $email, '');
        }

        $_SESSION['welcome'] = (string) $account['name'];
        if (isAjaxRequest()) {
            respondWithJson(['success' => true, 'message' => 'Welcome ' . $account['name'] . '!']);
        }
        redirectBack();
    } else {
        returnErrors(['Unknown form action.'], $email, $name);
    }
}

$errors = $_SESSION['errors'] ?? [];
$welcome = $_SESSION['welcome'] ?? null;
$oldEmail = $_SESSION['old_email'] ?? '';
$oldName = $_SESSION['old_name'] ?? '';
unset($_SESSION['errors'], $_SESSION['old_email'], $_SESSION['old_name']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login and Registration</title>
    <link rel="stylesheet" href="style.css">
    <script src="validation.js?v=20261021-2" defer></script>
</head>
<body>
<main class="page">
    <header class="intro">
        <p class="eyebrow">Database</p>
        <h1>Login and Registration</h1>
        <p>Create an account or sign in with your email.</p>
    </header>

    <section id="welcome-message" class="notice success" role="status" <?= $welcome === null ? 'hidden' : '' ?>>
        <span id="welcome-text"><?= $welcome === null ? '' : 'Welcome ' . escape((string) $welcome) . '!' ?></span>
        <form method="post" class="inline-form" data-logout-form>
            <input type="hidden" name="action" value="logout">
            <button class="text-button" type="submit">Sign out</button>
        </form>
    </section>

    <?php if ($errors !== []): ?>
        <section class="notice error" role="alert">
            <strong>Please fix the following:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= escape((string) $error) ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <div class="forms">
        <section class="card">
            <h2>Login</h2>
            <p class="card-hint">Already registered? Sign in here.</p>
            <div class="form-message" data-message-for="login" aria-live="polite" hidden></div>
            <form method="post" action="index.php" class="validate-form" data-form-type="login" novalidate>
                <input type="hidden" name="action" value="login">
                <label for="login-email">Email</label>
                <input id="login-email" name="email" type="email" autocomplete="email" value="<?= escape((string) $oldEmail) ?>">
                <label for="login-password">Password</label>
                <input id="login-password" name="password" type="password" autocomplete="current-password">
                <button type="submit">Login</button>
            </form>
        </section>

        <section class="card">
            <h2>Create account</h2>
            <p class="card-hint">All fields are required.</p>
            <div class="form-message" data-message-for="register" aria-live="polite" hidden></div>
            <form method="post" action="index.php" class="validate-form" data-form-type="register" novalidate>
                <input type="hidden" name="action" value="register">
                <label for="register-name">Name</label>
                <input id="register-name" name="name" type="text" autocomplete="name" value="<?= escape((string) $oldName) ?>">
                <label for="register-email">Email</label>
                <input id="register-email" name="email" type="email" autocomplete="email" value="<?= escape((string) $oldEmail) ?>">
                <label for="register-password">Password</label>
                <input id="register-password" name="password" type="password" autocomplete="new-password">
                <label for="confirm-password">Confirm password</label>
                <input id="confirm-password" name="confirm_password" type="password" autocomplete="new-password">
                <button type="submit">Register</button>
            </form>
        </section>
    </div>
    
</main>
</body>
</html>
