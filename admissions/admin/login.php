<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập Admin - Tuyển sinh</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
            padding: 40px;
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 36px;
        }

        .login-header h2 {
            color: #333;
            font-size: 24px;
            margin-bottom: 5px;
        }

        .login-header p {
            color: #666;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .form-group label i {
            color: #667eea;
            margin-right: 5px;
        }

        .form-group input {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .form-group input::placeholder {
            color: #aaa;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid #f5c6cb;
        }

        .error-message i {
            font-size: 18px;
        }

        .btn-login {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-login i {
            font-size: 18px;
        }

        .login-footer {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 13px;
        }

        .login-footer a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .login-footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
            }
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo">
                <i class="fas fa-university"></i>
            </div>
            <h2>Đăng nhập Admin</h2>
            <p>Hệ thống quản lý tuyển sinh</p>
        </div>


        <form id="loginForm">
            <div class="form-group">
                <label><i class="fas fa-user"></i> Tên đăng nhập</label>
                <input type="text" id="username" name="username" placeholder="Nhập tên đăng nhập" required>
            </div>

            <div class="form-group">
                <label><i class="fas fa-lock"></i> Mật khẩu</label>
                <input type="password" id="password" name="password" placeholder="Nhập mật khẩu" required>
            </div>

            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i>
                Đăng nhập
            </button>
        </form>
        <p id="error" style="color:red;"></p>
        <!-- <p id="error" style="color:red;"></p> -->
        <div class="login-footer">
            <p>Tài khoản mặc định: <strong>admin</strong> | Mật khẩu: <strong>admin123</strong></p>
            <p>&copy; 2024 - Hệ thống tuyển sinh</p>
        </div>
    </div>
    <script>
        document.getElementById("loginForm").addEventListener("submit", function(e) {
            e.preventDefault();

            const username = document.getElementById("username").value;
            const password = document.getElementById("password").value;

            fetch("api/check-login.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        username: username,
                        password: password
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // redirect khi login OK
                        window.location.href = "dashboard.php";
                    } else {
                        document.getElementById("error").innerText = data.message;
                    }
                })
                .catch(err => {
                    console.error(err);
                    document.getElementById("error").innerText = "Lỗi kết nối server";
                });
        });
    </script>
</body>

</html>