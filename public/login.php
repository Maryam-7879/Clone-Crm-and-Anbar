<?php
session_start();
require_once __DIR__ . '/../private/config.php';
require_once __DIR__ . '/../private/database.php';
require_once __DIR__ . '/../private/auth.php';
require_once __DIR__ . '/../private/functions.php';

// اگر کاربر لاگین کرده است، به داشبورد هدایت شود
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'درخواست نامعتبر. لطفاً مجدداً تلاش کنید.';
    } elseif (empty($username) || empty($password)) {
        $error = 'لطفاً تمام فیلدها را پر کنید';
    } else {
        $result = loginUser($username, $password);
        if ($result['success']) {
            header('Location: dashboard.php');
            exit();
        } else {
            $error = $result['message'];
        }
    }
}

if (isset($_GET['expired'])) {
    $error = 'جلسه شما منقضی شده است. لطفاً مجدداً وارد شوید.';
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به سیستم - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Cache buster for fonts -->

    <style>
        :root {
            --primary-color: #ff6b35;
            --primary-dark: #e55a2b;
            --secondary-color: #ffffff;
            --text-dark: #2c3e50;
            --text-light: #7f8c8d;
            --border-color: #ecf0f1;
            --shadow: 0 4px 20px rgba(255, 107, 53, 0.1);
        }

        body {
            font-family: 'Vazirmatn', sans-serif;
            background: linear-gradient(135deg, #ff6b35 0%, #f4f4f4 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow);
            overflow: hidden;
            max-width: 900px;
            width: 100%;
            margin: 20px;
        }
        
        @media (max-width: 768px) {
            .login-container {
                margin: 10px;
                border-radius: 15px;
                max-width: 100%;
            }
            
            .login-image {
                padding: 40px 20px;
                min-height: 300px;
            }
            
            .login-image h1 {
                font-size: 2rem;
                margin-bottom: 15px;
            }
            
            .login-image p {
                font-size: 1rem;
            }
            
            .login-form {
                padding: 40px 20px;
            }
            
            .form-title {
                font-size: 1.5rem;
                margin-bottom: 25px;
            }
        }
        
        @media (max-width: 576px) {
            body {
                padding: 10px;
            }
            
            .login-container {
                margin: 5px;
                border-radius: 10px;
            }
            
            .login-image {
                padding: 30px 15px;
                min-height: 250px;
            }
            
            .login-image h1 {
                font-size: 1.7rem;
            }
            
            .login-form {
                padding: 30px 15px;
            }
            
            .form-title {
                font-size: 1.3rem;
            }
            
            .form-control {
                padding: 10px 12px;
            }
            
            .btn {
                padding: 10px 20px;
            }
        }

        .login-image {
            background: linear-gradient(45deg, var(--primary-color), var(--primary-dark));
            padding: 60px 40px;
            color: white;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 500px;
        }

        .login-image h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .login-image p {
            font-size: 1.1rem;
            opacity: 0.9;
            line-height: 1.6;
        }

        .login-form {
            padding: 60px 40px;
        }

        .form-title {
            color: var(--text-dark);
            font-weight: 600;
            margin-bottom: 30px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            color: var(--text-dark);
            font-weight: 500;
            margin-bottom: 8px;
        }

        .form-control {
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(255, 107, 53, 0.25);
        }

        .input-group-text {
            background: transparent;
            border: 2px solid var(--border-color);
            border-left: none;
            border-radius: 10px 0 0 10px;
            color: var(--text-light);
        }

        .input-group .form-control {
            border-right: none;
            border-radius: 0 10px 10px 0;
        }

        .btn-primary {
            background: var(--primary-color);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 500;
            font-size: 16px;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .alert {
            border-radius: 10px;
            border: none;
        }

        .alert-danger {
            background: #fef2f2 !important;
            color: #ef4444 !important;
            border-left: 4px solid #ef4444;
        }

        .alert-success {
            background: #ecfdf5 !important;
            color: #10b981 !important;
            border-left: 4px solid #10b981;
        }

        .login-footer {
            text-align: center;
            margin-top: 30px;
            color: var(--text-light);
        }

        .forgot-password {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }

        .forgot-password:hover {
            color: var(--primary-dark);
        }


    </style>
</head>
<body>
    <div class="login-container">
        <div class="row g-0">
            <div class="col-lg-6 d-none d-lg-block">
                <div class="login-image">
                    <div>
                        <i class="fas fa-users-cog fa-4x mb-4"></i>
                        <h1>سیستم CRM</h1>
                        <p>مدیریت حرفه‌ای ارتباط با مشتریان</p>
                        <p>افزایش فروش، بهبود خدمات و رضایت مشتریان</p>
                        <div class="mt-4">
                            <i class="fas fa-shield-alt me-2"></i>
                            <span>امن و مطمئن</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-6">
                <div class="login-form">
                    <h2 class="form-title">ورود به سیستم</h2>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $success; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        
                        <div class="form-group">
                            <label for="username" class="form-label">نام کاربری یا ایمیل</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-user"></i>
                                </span>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                                       required autocomplete="username">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="password" class="form-label">رمز عبور</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" class="form-control" id="password" name="password" 
                                       required autocomplete="current-password">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="remember_me" name="remember_me">
                                <label class="form-check-label" for="remember_me">
                                    مرا به خاطر بسپار
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" name="login" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt me-2"></i>
                            ورود
                        </button>
                    </form>
                    
                    <div class="login-footer">
                        <a href="#" class="forgot-password">رمز عبور خود را فراموش کرده‌اید؟</a>
                        <p class="mt-3 mb-0">
                            <small>نسخه <?php echo APP_VERSION; ?></small>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
