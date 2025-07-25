<?php
// AI Chat с поддержкой Redis и множественными промптами
// Конфигурация
$openrouter_api_key = 'sk-or-v1-';
$app_name = 'AI Чат Ассистент';
$site_url = 'https://yourdomain.com';

// Данные для авторизации (3 пользователя)
$valid_users = [
    'murkir' => [
        'password' => 'murkir.pp.ua',
        'name' => 'Murkir',
        'role' => 'Администратор',
        'avatar' => '👑'
    ],
    'admin' => [
        'password' => 'admin123',
        'name' => 'Админ',
        'role' => 'Модератор',
        'avatar' => '🛡️'
    ],
    'guest' => [
        'password' => 'guest2024',
        'name' => 'Гость',
        'role' => 'Пользователь',
        'avatar' => '👤'
    ]
];

// Конфигурация Redis
$redis_host = 'localhost';
$redis_port = 6379;
$redis_password = null; // Если нужен пароль
$session_ttl = 86400; // 24 часа в секундах

// Подключение к Redis
try {
    $redis = new Redis();
    $redis->connect($redis_host, $redis_port);
    if ($redis_password) {
        $redis->auth($redis_password);
    }
    $redis_connected = true;
} catch (Exception $e) {
    $redis_connected = false;
    $redis_error = $e->getMessage();
}

// Запуск сессии
session_start();

// Обработка авторизации
$auth_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $login = trim($_POST['login'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    // Проверяем пользователя в массиве
    if (isset($valid_users[$login]) && $valid_users[$login]['password'] === $password) {
        $_SESSION['authenticated'] = true;
        $_SESSION['username'] = $login;
        $_SESSION['user_data'] = $valid_users[$login];
        $_SESSION['login_time'] = time();
        
        // Генерируем уникальный chat_session_id для каждого пользователя
        $_SESSION['chat_session_id'] = 'user_' . $login . '_chat';
        
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $auth_error = 'Неверный логин или пароль!';
    }
}

// Обработка выхода
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Проверка авторизации
$is_authenticated = isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;

// Если не авторизован - показываем форму авторизации
if (!$is_authenticated) {
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Авторизация | AI Чат</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .login-container {
                background: rgba(255, 255, 255, 0.95);
                padding: 40px;
                border-radius: 20px;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
                width: 100%;
                max-width: 500px;
                text-align: center;
                max-height: 90vh;
                overflow-y: auto;
            }

            .login-header {
                margin-bottom: 30px;
            }

            .login-header h1 {
                color: #2c3e50;
                font-size: 2rem;
                margin-bottom: 10px;
            }

            .login-header p {
                color: #6c757d;
                font-size: 1rem;
            }

            .form-group {
                margin-bottom: 20px;
                text-align: left;
            }

            .form-group label {
                display: block;
                font-weight: 600;
                color: #555;
                margin-bottom: 8px;
            }

            .form-group input {
                width: 100%;
                padding: 15px;
                border: 2px solid #e9ecef;
                border-radius: 10px;
                font-size: 16px;
                transition: border-color 0.3s ease;
            }

            .form-group input:focus {
                outline: none;
                border-color: #667eea;
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            }

            .login-btn {
                width: 100%;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                padding: 15px;
                border-radius: 10px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: transform 0.2s ease, box-shadow 0.2s ease;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
            }

            .login-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
            }

            .error-message {
                background: #f8d7da;
                color: #721c24;
                padding: 15px;
                border-radius: 8px;
                border: 1px solid #f5c6cb;
                margin-bottom: 20px;
                font-size: 14px;
            }

            .redis-status {
                background: rgba(102, 126, 234, 0.1);
                padding: 10px 15px;
                border-radius: 8px;
                margin-top: 20px;
                font-size: 0.8rem;
                color: #667eea;
            }

            .redis-status.connected {
                background: rgba(40, 167, 69, 0.1);
                color: #28a745;
            }

            .redis-status.disconnected {
                background: rgba(220, 53, 69, 0.1);
                color: #dc3545;
            }

            .available-users {
                margin-top: 30px;
                text-align: left;
            }

            .available-users h4 {
                color: #2c3e50;
                margin-bottom: 15px;
                text-align: center;
                font-size: 1rem;
            }

            .user-card {
                background: rgba(102, 126, 234, 0.1);
                border: 2px solid rgba(102, 126, 234, 0.2);
                border-radius: 10px;
                padding: 12px;
                margin-bottom: 10px;
                cursor: pointer;
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .user-card:hover {
                background: rgba(102, 126, 234, 0.2);
                border-color: #667eea;
                transform: translateX(5px);
            }

            .user-avatar {
                font-size: 1.5rem;
                width: 40px;
                height: 40px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: white;
                border-radius: 50%;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }

            .user-info-card {
                flex: 1;
            }

            .user-info-card strong {
                display: block;
                color: #2c3e50;
                font-size: 0.9rem;
                margin-bottom: 2px;
            }

            .user-info-card small {
                display: block;
                color: #6c757d;
                font-size: 0.75rem;
                margin-bottom: 4px;
            }

            .user-info-card code {
                background: rgba(255,255,255,0.8);
                padding: 2px 6px;
                border-radius: 4px;
                font-size: 0.7rem;
                color: #495057;
                border: 1px solid rgba(0,0,0,0.1);
            }

            @media (max-width: 480px) {
                .login-container {
                    margin: 20px;
                    padding: 30px 20px;
                }
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="login-header">
                <h1><i class="fas fa-robot"></i> AI Чат</h1>
                <p>Войдите для доступа к чату</p>
            </div>

            <?php if ($auth_error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($auth_error); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="action" value="login">
                
                <div class="form-group">
                    <label for="login">
                        <i class="fas fa-user"></i> Логин:
                    </label>
                    <input type="text" id="login" name="login" required autocomplete="username" 
                           value="<?php echo htmlspecialchars($_POST['login'] ?? ''); ?>" autofocus 
                           placeholder="Введите логин">
                </div>

                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i> Пароль:
                    </label>
                    <input type="password" id="password" name="password" required autocomplete="current-password"
                           placeholder="Введите пароль">
                </div>

                <button type="submit" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i>
                    Войти в чат
                </button>
            </form>

            <!-- Список доступных пользователей -->
            <div class="available-users">
                <h4><i class="fas fa-users"></i> Доступные пользователи:</h4>
                <?php foreach ($valid_users as $username => $user_data): ?>
                    <div class="user-card" onclick="fillLoginForm('<?php echo $username; ?>')">
                        <span class="user-avatar"><?php echo $user_data['avatar']; ?></span>
                        <div class="user-info-card">
                            <strong><?php echo htmlspecialchars($user_data['name']); ?></strong>
                            <small><?php echo htmlspecialchars($user_data['role']); ?></small>
                            <code>Логин: <?php echo $username; ?></code>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="redis-status <?php echo $redis_connected ? 'connected' : 'disconnected'; ?>">
                <i class="fas fa-<?php echo $redis_connected ? 'database' : 'exclamation-triangle'; ?>"></i>
                Redis: <?php echo $redis_connected ? 'Подключен (TTL: 24ч)' : 'Отключен'; ?>
            </div>
        </div>

        <script>
            // Функция заполнения формы логина
            function fillLoginForm(username) {
                const loginField = document.getElementById('login');
                const passwordField = document.getElementById('password');
                
                loginField.value = username;
                passwordField.focus();
                
                // Подсветка выбранной карточки
                document.querySelectorAll('.user-card').forEach(card => {
                    card.style.background = 'rgba(102, 126, 234, 0.1)';
                    card.style.borderColor = 'rgba(102, 126, 234, 0.2)';
                });
                
                event.target.closest('.user-card').style.background = 'rgba(40, 167, 69, 0.1)';
                event.target.closest('.user-card').style.borderColor = '#28a745';
            }

            // Автофокус на поле ввода
            document.addEventListener('DOMContentLoaded', function() {
                const loginField = document.getElementById('login');
                if (loginField && !loginField.value) {
                    loginField.focus();
                }
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}

// Генерация chat_session_id для авторизованного пользователя
$chat_session_id = $_SESSION['chat_session_id'];

// Функции для работы с Redis
function getChatHistory($redis, $session_id) {
    if (!$redis) return [];
    
    try {
        $history = $redis->get("chat_history:{$session_id}");
        return $history ? json_decode($history, true) : [];
    } catch (Exception $e) {
        return [];
    }
}

function saveChatHistory($redis, $session_id, $history) {
    if (!$redis) return false;
    
    try {
        global $session_ttl;
        $redis->setex("chat_history:{$session_id}", $session_ttl, json_encode($history));
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function addMessageToHistory($redis, $session_id, $message) {
    $history = getChatHistory($redis, $session_id);
    $history[] = [
        'id' => uniqid(),
        'role' => $message['role'],
        'content' => $message['content'],
        'timestamp' => time(),
        'model' => $message['model'] ?? null,
        'prompt_type' => $message['prompt_type'] ?? null
    ];
    
    // Ограничиваем историю последними 50 сообщениями
    if (count($history) > 50) {
        $history = array_slice($history, -50);
    }
    
    saveChatHistory($redis, $session_id, $history);
    return $history;
}

// Доступные AI модели (полный список)
function getOpenRouterModels() {
    return [
        // 🆓 БЕСПЛАТНЫЕ МОДЕЛИ
        'qwen/qwen-2.5-72b-instruct:free' => [
            'name' => '🆓 Qwen 2.5 72B Instruct',
            'description' => 'Мощная бесплатная модель от Alibaba',
            'price' => 'БЕСПЛАТНО',
            'cost_1000' => '$0.00',
            'speed' => '⚡⚡⚡⚡',
            'quality' => '⭐⭐⭐⭐',
            'recommended' => true,
            'category' => 'free'
        ],
        
        'meta-llama/llama-3.3-70b-instruct:free' => [
            'name' => '🆓 Llama 3.3 70B Instruct',
            'description' => 'Отличная бесплатная модель от Meta',
            'price' => 'БЕСПЛАТНО',
            'cost_1000' => '$0.00',
            'speed' => '⚡⚡⚡⚡',
            'quality' => '⭐⭐⭐⭐',
            'recommended' => true,
            'category' => 'free'
        ],
        
        'deepseek/deepseek-r1:free' => [
            'name' => '🆓 DeepSeek R1',
            'description' => 'Новейшая бесплатная модель с рассуждениями',
            'price' => 'БЕСПЛАТНО',
            'cost_1000' => '$0.00',
            'speed' => '⚡⚡⚡',
            'quality' => '⭐⭐⭐⭐⭐',
            'recommended' => true,
            'category' => 'free'
        ],
        
        'mistralai/mistral-nemo:free' => [
            'name' => '🆓 Mistral Nemo',
            'description' => 'Быстрая и качественная бесплатная модель',
            'price' => 'БЕСПЛАТНО',
            'cost_1000' => '$0.00',
            'speed' => '⚡⚡⚡⚡⚡',
            'quality' => '⭐⭐⭐⭐',
            'recommended' => false,
            'category' => 'free'
        ],

        // 💰 БЮДЖЕТНЫЕ МОДЕЛИ
        'deepseek/deepseek-chat' => [
            'name' => '💰 DeepSeek Chat',
            'description' => 'Отличное качество по низкой цене',
            'price' => '$0.14 / $0.28 за 1М токенов',
            'cost_1000' => '$0.42',
            'speed' => '⚡⚡⚡⚡',
            'quality' => '⭐⭐⭐⭐',
            'recommended' => true,
            'category' => 'budget'
        ],
        
        'openai/gpt-4.1-nano' => [
            'name' => '💰 GPT-4.1 Nano',
            'description' => 'Новейшая быстрая и дешевая модель OpenAI',
            'price' => '$0.10 / $0.40 за 1М токенов',
            'cost_1000' => '$0.50',
            'speed' => '⚡⚡⚡⚡⚡',
            'quality' => '⭐⭐⭐⭐',
            'recommended' => true,
            'category' => 'budget'
        ],
        
        'google/gemini-2.5-flash' => [
            'name' => '💰 Gemini 2.5 Flash',
            'description' => 'СУПЕР ПОПУЛЯРНАЯ! Топ модель по цене/качеству',
            'price' => '$0.075 / $0.30 за 1М токенов',
            'cost_1000' => '$0.375',
            'speed' => '⚡⚡⚡⚡⚡',
            'quality' => '⭐⭐⭐⭐⭐',
            'recommended' => true,
            'category' => 'budget'
        ],
        
        'qwen/qwen-2.5-72b-instruct' => [
            'name' => '💰 Qwen 2.5 72B Instruct',
            'description' => 'Мощная модель по доступной цене',
            'price' => '$0.40 / $1.20 за 1М токенов',
            'cost_1000' => '$1.60',
            'speed' => '⚡⚡⚡⚡',
            'quality' => '⭐⭐⭐⭐⭐',
            'recommended' => true,
            'category' => 'budget'
        ],
        
        'meta-llama/llama-3.3-70b-instruct' => [
            'name' => '💰 Llama 3.3 70B Instruct',
            'description' => 'Отличная модель от Meta, хорошая цена',
            'price' => '$0.59 / $0.79 за 1М токенов',
            'cost_1000' => '$1.38',
            'speed' => '⚡⚡⚡⚡',
            'quality' => '⭐⭐⭐⭐',
            'recommended' => false,
            'category' => 'budget'
        ],

        // 🥇 ПРЕМИУМ МОДЕЛИ
        'google/gemini-2.5-pro' => [
            'name' => '🥇 Gemini 2.5 Pro',
            'description' => 'Топовая модель Google с отличными возможностями',
            'price' => '$1.25 / $5.00 за 1М токенов',
            'cost_1000' => '$6.25',
            'speed' => '⚡⚡⚡⚡',
            'quality' => '⭐⭐⭐⭐⭐',
            'recommended' => true,
            'category' => 'premium'
        ],
        
        'openai/gpt-4o' => [
            'name' => '🥇 GPT-4o',
            'description' => 'Мультимодальная модель от OpenAI',
            'price' => '$2.50 / $10.00 за 1М токенов',
            'cost_1000' => '$12.50',
            'speed' => '⚡⚡⚡',
            'quality' => '⭐⭐⭐⭐⭐',
            'recommended' => false,
            'category' => 'premium'
        ],
        
        'openai/gpt-4o-mini' => [
            'name' => '🥇 GPT-4o Mini',
            'description' => 'Быстрая и качественная мини-версия',
            'price' => '$0.15 / $0.60 за 1М токенов',
            'cost_1000' => '$0.75',
            'speed' => '⚡⚡⚡⚡⚡',
            'quality' => '⭐⭐⭐⭐',
            'recommended' => true,
            'category' => 'premium'
        ],
        
        'anthropic/claude-3.5-sonnet' => [
            'name' => '🥇 Claude 3.5 Sonnet',
            'description' => 'Топовая модель от Anthropic для текста и кода',
            'price' => '$3.00 / $15.00 за 1М токенов',
            'cost_1000' => '$18.00',
            'speed' => '⚡⚡⚡',
            'quality' => '⭐⭐⭐⭐⭐',
            'recommended' => false,
            'category' => 'premium'
        ],
        
        'anthropic/claude-3-haiku' => [
            'name' => '🥇 Claude 3 Haiku',
            'description' => 'Быстрая и экономичная версия Claude',
            'price' => '$0.25 / $1.25 за 1М токенов',
            'cost_1000' => '$1.50',
            'speed' => '⚡⚡⚡⚡⚡',
            'quality' => '⭐⭐⭐⭐',
            'recommended' => true,
            'category' => 'premium'
        ],

        // 🚀 НОВЕЙШИЕ И ПОПУЛЯРНЫЕ МОДЕЛИ
        'anthropic/claude-3.7-sonnet' => [
            'name' => '🚀 Claude 3.7 Sonnet',
            'description' => 'Новейшая модель Anthropic с улучшенными возможностями',
            'price' => '$3.00 / $15.00 за 1М токенов',
            'cost_1000' => '$18.00',
            'speed' => '⚡⚡⚡',
            'quality' => '⭐⭐⭐⭐⭐',
            'recommended' => true,
            'category' => 'newest'
        ],
        
        'anthropic/claude-sonnet-4' => [
            'name' => '🚀 Claude Sonnet 4',
            'description' => 'Революционная Claude 4 с мгновенными ответами',
            'price' => '$5.00 / $25.00 за 1М токенов',
            'cost_1000' => '$30.00',
            'speed' => '⚡⚡⚡⚡',
            'quality' => '⭐⭐⭐⭐⭐',
            'recommended' => true,
            'category' => 'newest'
        ],
        
        'anthropic/claude-opus-4' => [
            'name' => '🚀 Claude Opus 4',
            'description' => 'Топовая модель Claude 4 с максимальными возможностями',
            'price' => '$15.00 / $75.00 за 1М токенов',
            'cost_1000' => '$90.00',
            'speed' => '⚡⚡',
            'quality' => '⭐⭐⭐⭐⭐',
            'recommended' => false,
            'category' => 'newest'
        ],
        
        'x-ai/grok-3' => [
            'name' => '🚀 Grok 3.0',
            'description' => 'Мощная модель xAI с думающим режимом',
            'price' => '$2.50 / $12.50 за 1М токенов',
            'cost_1000' => '$15.00',
            'speed' => '⚡⚡⚡',
            'quality' => '⭐⭐⭐⭐⭐',
            'recommended' => true,
            'category' => 'newest'
        ],
        
        'x-ai/grok-4' => [
            'name' => '🚀 Grok 4.0',
            'description' => 'Новейшая модель xAI с продвинутыми рассуждениями',
            'price' => '$4.00 / $20.00 за 1М токенов',
            'cost_1000' => '$24.00',
            'speed' => '⚡⚡',
            'quality' => '⭐⭐⭐⭐⭐',
            'recommended' => true,
            'category' => 'newest'
        ],
        
        'deepseek/deepseek-r1' => [
            'name' => '🚀 DeepSeek R1',
            'description' => 'Революционная модель с рассуждениями. Конкурент GPT-o1',
            'price' => '$0.55 / $2.19 за 1М токенов',
            'cost_1000' => '$2.74',
            'speed' => '⚡⚡⚡',
            'quality' => '⭐⭐⭐⭐⭐',
            'recommended' => true,
            'category' => 'newest'
        ],
        
        'mistralai/mistral-large-2407' => [
            'name' => '🚀 Mistral Large 2407',
            'description' => 'Флагманская модель Mistral с отличным качеством',
            'price' => '$3.00 / $9.00 за 1М токенов',
            'cost_1000' => '$12.00',
            'speed' => '⚡⚡⚡',
            'quality' => '⭐⭐⭐⭐⭐',
            'recommended' => true,
            'category' => 'newest'
        ],
        
        'x-ai/grok-2-1212' => [
            'name' => '🚀 Grok 2.0',
            'description' => 'Модель от xAI с юмором и актуальными данными',
            'price' => '$2.00 / $10.00 за 1М токенов',
            'cost_1000' => '$12.00',
            'speed' => '⚡⚡⚡',
            'quality' => '⭐⭐⭐⭐⭐',
            'recommended' => false,
            'category' => 'newest'
        ],
        
        'openai/o1-mini' => [
            'name' => '🚀 GPT-o1 Mini',
            'description' => 'Модель с усиленными рассуждениями от OpenAI',
            'price' => '$3.00 / $12.00 за 1М токенов',
            'cost_1000' => '$15.00',
            'speed' => '⚡⚡',
            'quality' => '⭐⭐⭐⭐⭐',
            'recommended' => false,
            'category' => 'newest'
        ],
        
        'cohere/command-r-plus' => [
            'name' => '🚀 Command R+',
            'description' => 'Мощная модель Cohere для RAG и сложных задач',
            'price' => '$3.00 / $15.00 за 1М токенов',
            'cost_1000' => '$18.00',
            'speed' => '⚡⚡⚡',
            'quality' => '⭐⭐⭐⭐',
            'recommended' => false,
            'category' => 'newest'
        ]
    ];
}

// Система промптов
function getChatPrompts() {
    return [
        'general' => [
            'name' => '💬 Общение',
            'description' => 'Обычный разговор и вопросы',
            'icon' => '💬',
            'system_prompt' => "Ты дружелюбный и полезный AI-ассистент. Отвечай естественно, помогай пользователю с любыми вопросами. Будь вежливым, информативным и старайся давать полные и полезные ответы."
        ],
        
        'seo_copywriter' => [
            'name' => '📝 SEO Копирайтер',
            'description' => 'Создание SEO-текстов и контента',
            'icon' => '📝',
            'system_prompt' => "Ты профессиональный SEO-копирайтер с 10+ лет опыта. Специализируешься на создании SEO-оптимизированного контента, мета-тегов, ключевых слов и текстов для сайтов. Умеешь анализировать конкурентов и создавать контент, который ранжируется в поисковых системах."
        ],
        
        'programmer' => [
            'name' => '💻 Программист',
            'description' => 'Помощь с кодом и разработкой',
            'icon' => '💻',
            'system_prompt' => "Ты опытный программист-ментор. Помогаешь с написанием кода, отладкой, объяснением алгоритмов и архитектурных решений. Знаешь множество языков программирования и современные технологии. Даешь четкие объяснения и примеры кода."
        ],
        
        'business_analyst' => [
            'name' => '📊 Бизнес-аналитик',
            'description' => 'Бизнес-планы, анализ и стратегии',
            'icon' => '📊',
            'system_prompt' => "Ты профессиональный бизнес-аналитик и консультант. Помогаешь с бизнес-планами, анализом рынка, финансовым планированием, стратегиями развития бизнеса. Можешь анализировать данные и давать конкретные рекомендации."
        ],
        
        'marketer' => [
            'name' => '📈 Маркетолог',
            'description' => 'Маркетинговые стратегии и реклама',
            'icon' => '📈',
            'system_prompt' => "Ты эксперт по цифровому маркетингу. Специализируешься на создании рекламных кампаний, контент-маркетинге, SMM, email-маркетинге, аналитике. Помогаешь с воронками продаж, таргетингом и оптимизацией конверсий."
        ],
        
        'translator' => [
            'name' => '🌍 Переводчик',
            'description' => 'Перевод и работа с языками',
            'icon' => '🌍',
            'system_prompt' => "Ты профессиональный переводчик, владеющий множеством языков. Делаешь качественные переводы с учетом контекста, культурных особенностей и стилистики. Можешь объяснить грамматические нюансы и помочь с изучением языков."
        ],
        
        'designer' => [
            'name' => '🎨 Дизайнер',
            'description' => 'Дизайн, UX/UI, брендинг',
            'icon' => '🎨',
            'system_prompt' => "Ты креативный дизайнер с экспертизой в графическом дизайне, UX/UI, брендинге и веб-дизайне. Помогаешь с концепциями, цветовыми схемами, типографикой, композицией. Даешь советы по пользовательскому опыту и визуальной иерархии."
        ],
        
        'teacher' => [
            'name' => '🎓 Преподаватель',
            'description' => 'Обучение и объяснение сложных тем',
            'icon' => '🎓',
            'system_prompt' => "Ты опытный преподаватель, который умеет объяснять сложные темы простым языком. Адаптируешь объяснения под уровень ученика, используешь примеры, аналогии и структурированную подачу материала. Помогаешь с учебными планами и методиками."
        ],
        
        'creative_writer' => [
            'name' => '✍️ Писатель',
            'description' => 'Творческое письмо и сторителлинг',
            'icon' => '✍️',
            'system_prompt' => "Ты талантливый писатель и сторителлер. Помогаешь с созданием историй, сценариев, романов, статей. Владеешь различными стилями письма, умеешь создавать захватывающие сюжеты и яркие персонажи."
        ],
        
        'psychologist' => [
            'name' => '🧠 Психолог',
            'description' => 'Психологическая поддержка и советы',
            'icon' => '🧠',
            'system_prompt' => "Ты понимающий психолог-консультант. Помогаешь разобраться в эмоциональных вопросах, даешь советы по саморазвитию, мотивации, преодолению стресса. Используешь техники когнитивно-поведенческой терапии и эмпатичный подход."
        ],
        
        'fitness_trainer' => [
            'name' => '💪 Фитнес-тренер',
            'description' => 'Спорт, тренировки и здоровье',
            'icon' => '💪',
            'system_prompt' => "Ты сертифицированный фитнес-тренер и специалист по здоровому образу жизни. Помогаешь составлять тренировочные программы, планы питания, даешь советы по восстановлению и мотивации. Знаешь анатомию и физиологию."
        ],
        
        'chef' => [
            'name' => '👨‍🍳 Шеф-повар',
            'description' => 'Кулинария и рецепты',
            'icon' => '👨‍🍳',
            'system_prompt' => "Ты профессиональный шеф-повар с международным опытом. Знаешь кухни мира, можешь создавать рецепты, советовать по технике приготовления, сочетанию продуктов. Учитываешь диетические ограничения и предпочтения."
        ]
    ];
}

// Функции для умного поиска в БД
function analyzeNeedForSearch($message) {
    // Ключевые слова для поиска
    $search_triggers = [
        'найди', 'найти', 'поиск', 'ищи', 'искать',
        'сколько', 'статистика', 'когда я', 'что я',
        'покажи', 'выведи', 'список', 'история',
        'потратил', 'использовал', 'писал', 'говорил',
        'модель', 'токен', 'промпт', 'сообщений',
        'вчера', 'сегодня', 'неделя', 'месяц'
    ];
    
    $message_lower = mb_strtolower($message);
    
    foreach ($search_triggers as $trigger) {
        if (strpos($message_lower, $trigger) !== false) {
            return true;
        }
    }
    
    return false;
}

function searchUserHistory($user_id, $query, $filters = []) {
    global $redis;
    
    // Для демо используем Redis, но здесь будет MariaDB
    $history = getChatHistory($redis, 'user_' . $user_id . '_chat');
    
    $results = [];
    $stats = [
        'total_messages' => count($history),
        'search_matches' => 0,
        'models_used' => [],
        'prompts_used' => [],
        'date_range' => []
    ];
    
    $query_lower = mb_strtolower($query);
    
    foreach ($history as $message) {
        if ($message['role'] === 'system') continue;
        
        $content_lower = mb_strtolower($message['content']);
        
        // Поиск по содержимому
        if (empty($query) || strpos($content_lower, $query_lower) !== false) {
            $results[] = [
                'content' => substr($message['content'], 0, 200) . (strlen($message['content']) > 200 ? '...' : ''),
                'role' => $message['role'],
                'timestamp' => $message['timestamp'],
                'date' => date('d.m.Y H:i', $message['timestamp']),
                'model' => $message['model'] ?? 'unknown',
                'prompt_type' => $message['prompt_type'] ?? 'general'
            ];
            $stats['search_matches']++;
        }
        
        // Собираем статистику
        if (isset($message['model'])) {
            $stats['models_used'][$message['model']] = ($stats['models_used'][$message['model']] ?? 0) + 1;
        }
        if (isset($message['prompt_type'])) {
            $stats['prompts_used'][$message['prompt_type']] = ($stats['prompts_used'][$message['prompt_type']] ?? 0) + 1;
        }
        if (isset($message['timestamp'])) {
            $date = date('Y-m-d', $message['timestamp']);
            $stats['date_range'][$date] = ($stats['date_range'][$date] ?? 0) + 1;
        }
    }
    
    // Сортируем по популярности
    arsort($stats['models_used']);
    arsort($stats['prompts_used']);
    arsort($stats['date_range']);
    
    // Ограничиваем результаты
    $results = array_slice($results, 0, 10);
    
    return [
        'results' => $results,
        'stats' => $stats,
        'query' => $query
    ];
}

function generateSearchPrompt($user_message, $search_data, $user_name, $base_prompt) {
    $results = $search_data['results'];
    $stats = $search_data['stats'];
    $query = $search_data['query'];
    
    // Начинаем с базового промпта (SEO копирайтер, Программист, etc.)
    $context = $base_prompt . "\n\n";
    $context .= "ДОПОЛНИТЕЛЬНЫЙ КОНТЕКСТ ИЗ ПОИСКА:\n";
    $context .= "Пользователь {$user_name} спросил: \"{$user_message}\"\n\n";
    
    if (empty($results)) {
        $context .= "🔍 РЕЗУЛЬТАТЫ ПОИСКА: Ничего не найдено";
        if (!empty($query)) {
            $context .= " по запросу \"$query\"";
        }
        $context .= "\n\n📊 ОБЩАЯ СТАТИСТИКА:\n";
    } else {
        $context .= "🔍 РЕЗУЛЬТАТЫ ПОИСКА";
        if (!empty($query)) {
            $context .= " по запросу \"$query\"";
        }
        $context .= " (найдено {$stats['search_matches']} из {$stats['total_messages']}):\n\n";
        
        foreach ($results as $i => $result) {
            $role_icon = $result['role'] === 'user' ? '👤' : '🤖';
            $context .= ($i + 1) . ". {$role_icon} {$result['date']} ({$result['model']}, {$result['prompt_type']}):\n";
            $context .= "   \"{$result['content']}\"\n\n";
        }
    }
    
    // Добавляем статистику
    $context .= "📊 СТАТИСТИКА:\n";
    $context .= "📝 Всего сообщений: {$stats['total_messages']}\n";
    
    if (!empty($stats['models_used'])) {
        $context .= "🤖 Топ модели: ";
        $top_models = array_slice($stats['models_used'], 0, 3, true);
        $model_list = [];
        foreach ($top_models as $model => $count) {
            $model_list[] = "$model ($count)";
        }
        $context .= implode(', ', $model_list) . "\n";
    }
    
    if (!empty($stats['prompts_used'])) {
        $context .= "🎭 Топ промпты: ";
        $top_prompts = array_slice($stats['prompts_used'], 0, 3, true);
        $prompt_list = [];
        foreach ($top_prompts as $prompt => $count) {
            $prompt_list[] = "$prompt ($count)";
        }
        $context .= implode(', ', $prompt_list) . "\n";
    }
    
    if (!empty($stats['date_range'])) {
        $context .= "📅 Активные дни: ";
        $top_dates = array_slice($stats['date_range'], 0, 3, true);
        $date_list = [];
        foreach ($top_dates as $date => $count) {
            $formatted_date = date('d.m', strtotime($date));
            $date_list[] = "$formatted_date ($count)";
        }
        $context .= implode(', ', $date_list) . "\n";
    }
    
    $context .= "\n=== ВАЖНО ===\n";
    $context .= "Отвечай в СВОЕЙ РОЛИ (как указано в начале промпта), но используй найденные данные из истории пользователя. ";
    $context .= "Если нашлись результаты - проанализируй их в контексте своей специализации. ";
    $context .= "Если ничего не найдено - предложи альтернативы исходя из своей роли.";
    
    return $context;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    
    if ($action === 'send_message') {
        $message = trim($_POST['message'] ?? '');
        $model = $_POST['model'] ?? 'qwen/qwen-2.5-72b-instruct:free';
        $prompt_type = $_POST['prompt_type'] ?? 'general';
        
        if (empty($message)) {
            echo json_encode(['success' => false, 'error' => 'Сообщение не может быть пустым']);
            exit;
        }
        
        // Добавляем сообщение пользователя в историю
        $user_message = [
            'role' => 'user',
            'content' => $message,
            'model' => $model,
            'prompt_type' => $prompt_type
        ];
        
        if ($redis_connected) {
            addMessageToHistory($redis, $chat_session_id, $user_message);
        }
        
        // Получаем системный промпт
        $prompts = getChatPrompts();
        $system_prompt = $prompts[$prompt_type]['system_prompt'] ?? $prompts['general']['system_prompt'];
        
        // Получаем историю для контекста
        $history = $redis_connected ? getChatHistory($redis, $chat_session_id) : [];
        
        // Формируем сообщения для API
        $messages = [];
        
        // Добавляем системный промпт
        $messages[] = [
            'role' => 'system',
            'content' => $system_prompt
        ];
        
        // Добавляем последние 10 сообщений из истории для контекста
        $recent_history = array_slice($history, -10);
        foreach ($recent_history as $hist_msg) {
            if ($hist_msg['role'] !== 'system') {
                $messages[] = [
                    'role' => $hist_msg['role'],
                    'content' => $hist_msg['content']
                ];
            }
        }
        
        // Запрос к OpenRouter API
        $data = [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => 2000,
            'temperature' => 0.7,
            'stream' => false
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://openrouter.ai/api/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $openrouter_api_key,
            'HTTP-Referer: ' . $site_url,
            'X-Title: ' . $app_name
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code == 200) {
            $response_data = json_decode($response, true);
            if (isset($response_data['choices'][0]['message']['content'])) {
                $ai_response = $response_data['choices'][0]['message']['content'];
                
                // Добавляем ответ AI в историю
                $ai_message = [
                    'role' => 'assistant',
                    'content' => $ai_response,
                    'model' => $model,
                    'prompt_type' => $prompt_type
                ];
                
                if ($redis_connected) {
                    addMessageToHistory($redis, $chat_session_id, $ai_message);
                }
                
                echo json_encode([
                    'success' => true,
                    'response' => $ai_response,
                    'model' => $model,
                    'prompt_type' => $prompt_type
                ]);
            } else {
                echo json_encode([
                    'success' => false, 
                    'error' => 'Ошибка в ответе API: ' . (isset($response_data['error']['message']) ? $response_data['error']['message'] : 'Неизвестная ошибка')
                ]);
            }
        } else {
            $response_data = json_decode($response, true);
            echo json_encode([
                'success' => false,
                'error' => 'Ошибка запроса (' . $http_code . '): ' . (isset($response_data['error']['message']) ? $response_data['error']['message'] : 'Неизвестная ошибка')
            ]);
        }
        exit;
    }
    
    if ($action === 'get_history') {
        $history = $redis_connected ? getChatHistory($redis, $chat_session_id) : [];
        echo json_encode(['success' => true, 'history' => $history]);
        exit;
    }
    
    if ($action === 'clear_history') {
        if ($redis_connected) {
            try {
                $redis->del("chat_history:{$chat_session_id}");
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => true]); // Если Redis недоступен
        }
        exit;
    }
}

$models = getOpenRouterModels();
$prompts = getChatPrompts();
$chat_history = $redis_connected ? getChatHistory($redis, $chat_session_id) : [];
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Чат Ассистент | 24 лучших модели с Redis</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            overflow: hidden;
        }

        .chat-container {
            display: flex;
            height: 100vh;
            max-width: 1400px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 0 50px rgba(0, 0, 0, 0.2);
        }

        /* SIDEBAR */
        .sidebar {
            width: 350px;
            background: #2c3e50;
            color: white;
            display: flex;
            flex-direction: column;
            border-radius: 0 0 0 20px;
        }

        .sidebar-header {
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            text-align: center;
        }

        .sidebar-header h1 {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }

        .sidebar-header p {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .redis-status {
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 12px;
            border-radius: 15px;
            margin-top: 10px;
            font-size: 0.8rem;
            display: inline-block;
        }

        .redis-status.connected {
            background: rgba(40, 167, 69, 0.8);
        }

        .redis-status.disconnected {
            background: rgba(220, 53, 69, 0.8);
        }

        .user-info {
            background: rgba(255, 255, 255, 0.2);
            padding: 15px;
            border-radius: 12px;
            margin: 10px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar-main {
            font-size: 2rem;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 50%;
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        }

        .user-details {
            flex: 1;
            color: rgba(255, 255, 255, 0.95);
        }

        .user-details strong {
            display: block;
            color: white;
            font-size: 1rem;
            margin-bottom: 2px;
        }

        .user-details small {
            display: block;
            font-size: 0.8rem;
            opacity: 0.8;
            margin-bottom: 4px;
        }

        .login-time {
            font-size: 0.7rem;
            opacity: 0.7;
        }

        .prompt-controls {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }

        .prompt-btn {
            flex: 1;
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .prompt-btn.create {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }

        .prompt-btn.create:hover {
            background: linear-gradient(135deg, #218838 0%, #1e7e6a 100%);
            transform: translateY(-1px);
        }

        .prompt-btn.manage {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
        }

        .prompt-btn.manage:hover {
            background: linear-gradient(135deg, #5a6268 0%, #343a40 100%);
            transform: translateY(-1px);
        }

        /* МОДАЛЬНЫЕ ОКНА */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 2000;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .modal-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .modal {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            transform: translateY(-50px);
            transition: transform 0.3s ease;
        }

        .modal-overlay.show .modal {
            transform: translateY(0);
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.3rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            transition: background 0.3s ease;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .modal-body {
            padding: 25px;
        }

        .form-row {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: #555;
            margin-bottom: 8px;
        }

        .form-input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            resize: vertical;
            min-height: 120px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.5;
            transition: border-color 0.3s ease;
        }

        .form-textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .emoji-picker {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .emoji-item {
            font-size: 1.5rem;
            padding: 8px;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .emoji-item:hover {
            background: #e9ecef;
            transform: scale(1.1);
        }

        .emoji-item.selected {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.1);
        }

        .modal-footer {
            padding: 20px 25px;
            border-top: 1px solid #e9ecef;
            display: flex;
            gap: 15px;
            justify-content: flex-end;
        }

        .modal-btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .modal-btn.primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .modal-btn.primary:hover {
            background: linear-gradient(135deg, #5a67d8 0%, #6b5b95 100%);
            transform: translateY(-2px);
        }

        .modal-btn.secondary {
            background: #6c757d;
            color: white;
        }

        .modal-btn.secondary:hover {
            background: #5a6268;
        }

        /* УПРАВЛЕНИЕ ПРОМПТАМИ */
        .custom-prompts-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .custom-prompt-item {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .custom-prompt-item:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.1);
        }

        .prompt-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .prompt-item-title {
            font-weight: 600;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .prompt-item-actions {
            display: flex;
            gap: 8px;
        }

        .prompt-action-btn {
            background: none;
            border: none;
            padding: 6px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .prompt-action-btn.edit {
            color: #007bff;
        }

        .prompt-action-btn.edit:hover {
            background: rgba(0, 123, 255, 0.1);
        }

        .prompt-action-btn.delete {
            color: #dc3545;
        }

        .prompt-action-btn.delete:hover {
            background: rgba(220, 53, 69, 0.1);
        }

        .prompt-item-description {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 8px;
        }

        .prompt-item-preview {
            background: white;
            padding: 10px;
            border-radius: 6px;
            font-size: 0.8rem;
            color: #495057;
            max-height: 80px;
            overflow: hidden;
            border-left: 3px solid #667eea;
        }

        .empty-prompts {
            text-align: center;
            color: #6c757d;
            padding: 40px 20px;
        }

        .empty-prompts i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.3;
        }

        .settings-section {
            padding: 20px;
            border-bottom: 1px solid #34495e;
        }

        .setting-group {
            margin-bottom: 20px;
        }

        .setting-label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: #ecf0f1;
        }

        .setting-select {
            width: 100%;
            padding: 10px;
            border: none;
            border-radius: 6px;
            background: #34495e;
            color: white;
            font-size: 0.9rem;
        }

        .setting-select option {
            background: #34495e;
            color: white;
        }

        .model-info, .prompt-info {
            background: rgba(52, 73, 94, 0.8);
            padding: 12px;
            border-radius: 8px;
            margin-top: 8px;
            font-size: 0.8rem;
        }

        .model-info.free { border-left: 4px solid #17a2b8; }
        .model-info.budget { border-left: 4px solid #ffc107; }
        .model-info.premium { border-left: 4px solid #dc3545; }
        .model-info.newest { border-left: 4px solid #28a745; }

        .chat-controls {
            padding: 20px;
            margin-top: auto;
        }

        .control-btn {
            width: 100%;
            padding: 12px;
            margin-bottom: 10px;
            border: none;
            border-radius: 8px;
            background: #e74c3c;
            color: white;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .control-btn:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }

        .control-btn.export {
            background: #27ae60;
        }

        .control-btn.export:hover {
            background: #229954;
        }

        .control-btn.logout {
            background: #6c757d;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .control-btn.logout:hover {
            background: #5a6268;
        }

        /* MAIN CHAT AREA */
        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #f8f9fa;
        }

        .chat-header {
            padding: 20px;
            background: white;
            border-bottom: 2px solid #e9ecef;
            display: flex;
            justify-content: between;
            align-items: center;
        }

        .chat-title {
            font-size: 1.3rem;
            color: #2c3e50;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-avatar {
            font-size: 1.5rem;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            color: white;
            box-shadow: 0 3px 10px rgba(102, 126, 234, 0.3);
        }

        .user-role {
            font-size: 0.8rem;
            color: #6c757d;
            font-weight: 400;
        }

        .chat-stats {
            display: flex;
            gap: 15px;
            font-size: 0.8rem;
            color: #6c757d;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            scroll-behavior: smooth;
        }

        .message {
            max-width: 80%;
            margin-bottom: 20px;
            animation: fadeInUp 0.3s ease;
        }

        .message.user {
            margin-left: auto;
        }

        .message-content {
            padding: 15px 20px;
            border-radius: 18px;
            position: relative;
            word-wrap: break-word;
        }

        .message.user .message-content {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-bottom-right-radius: 6px;
        }

        .message.assistant .message-content {
            background: white;
            color: #2c3e50;
            border: 1px solid #e9ecef;
            border-bottom-left-radius: 6px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .message-meta {
            font-size: 0.7rem;
            opacity: 0.7;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .message.user .message-meta {
            justify-content: flex-end;
            color: rgba(255, 255, 255, 0.8);
        }

        .message.assistant .message-meta {
            color: #6c757d;
        }

        .copy-btn {
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            opacity: 0.6;
            transition: opacity 0.3s ease;
            padding: 4px;
        }

        .copy-btn:hover {
            opacity: 1;
        }

        .search-indicator {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.6rem;
            font-weight: bold;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }

        .search-examples {
            background: rgba(102, 126, 234, 0.1);
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
            text-align: left;
        }

        .search-examples h4 {
            color: #667eea;
            margin-bottom: 15px;
            text-align: center;
        }

        .example-queries {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
        }

        .example-query {
            background: rgba(102, 126, 234, 0.8);
            color: white;
            padding: 8px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .example-query:hover {
            background: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        /* CHAT INPUT */
        .chat-input-area {
            padding: 20px;
            background: white;
            border-top: 2px solid #e9ecef;
        }

        .input-container {
            display: flex;
            gap: 10px;
            max-width: 100%;
        }

        .message-input {
            flex: 1;
            padding: 15px 20px;
            border: 2px solid #e9ecef;
            border-radius: 25px;
            font-size: 16px;
            resize: none;
            outline: none;
            transition: border-color 0.3s ease;
            min-height: 50px;
            max-height: 150px;
        }

        .message-input:focus {
            border-color: #667eea;
        }

        .send-btn {
            width: 50px;
            height: 50px;
            border: none;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .send-btn:hover:not(:disabled) {
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .send-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .typing-indicator {
            max-width: 80%;
            margin-bottom: 20px;
            display: none;
        }

        .typing-indicator.show {
            display: block;
        }

        .typing-content {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 18px;
            border-bottom-left-radius: 6px;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .typing-dots {
            display: flex;
            gap: 4px;
        }

        .typing-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #667eea;
            animation: typingDots 1.4s infinite ease-in-out;
        }

        .typing-dot:nth-child(1) { animation-delay: -0.32s; }
        .typing-dot:nth-child(2) { animation-delay: -0.16s; }

        .empty-chat {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #6c757d;
            text-align: center;
            padding: 40px;
        }

        .empty-chat i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .empty-chat h3 {
            margin-bottom: 10px;
            font-size: 1.5rem;
        }

        .quick-prompts {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 30px;
            max-width: 800px;
        }

        .quick-prompt {
            background: white;
            padding: 15px;
            border-radius: 12px;
            border: 2px solid #e9ecef;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: left;
        }

        .quick-prompt:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .quick-prompt-icon {
            font-size: 1.5rem;
            margin-bottom: 8px;
        }

        .quick-prompt-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .quick-prompt-desc {
            font-size: 0.8rem;
            color: #6c757d;
        }

        .quick-prompt.custom {
            border: 2px solid #28a745;
            background: rgba(40, 167, 69, 0.05);
        }

        .quick-prompt.custom:hover {
            border-color: #218838;
            background: rgba(40, 167, 69, 0.1);
        }

        .quick-prompt.create-new {
            border: 2px dashed #667eea;
            background: rgba(102, 126, 234, 0.05);
        }

        .quick-prompt.create-new:hover {
            border-color: #5a67d8;
            background: rgba(102, 126, 234, 0.1);
        }

        /* ANIMATIONS */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes typingDots {
            0%, 80%, 100% {
                transform: scale(0);
                opacity: 0.5;
            }
            40% {
                transform: scale(1);
                opacity: 1;
            }
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .chat-container {
                height: 100vh;
            }
            
            .sidebar {
                position: fixed;
                left: -350px;
                top: 0;
                height: 100vh;
                z-index: 1000;
                transition: left 0.3s ease;
            }
            
            .sidebar.open {
                left: 0;
            }
            
            .chat-main {
                width: 100%;
            }
            
            .mobile-menu-btn {
                position: fixed;
                top: 20px;
                left: 20px;
                z-index: 1001;
                background: #667eea;
                color: white;
                border: none;
                width: 50px;
                height: 50px;
                border-radius: 50%;
                font-size: 18px;
                cursor: pointer;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            }
            
            .quick-prompts {
                grid-template-columns: 1fr;
            }
            
            .message {
                max-width: 90%;
            }
        }

        /* SCROLLBAR */
        .chat-messages::-webkit-scrollbar {
            width: 6px;
        }

        .chat-messages::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .chat-messages::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }

        .chat-messages::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        /* NOTIFICATION */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #28a745;
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            z-index: 1000;
            opacity: 0;
            transform: translateY(-20px);
            transition: all 0.3s ease;
        }

        .notification.show {
            opacity: 1;
            transform: translateY(0);
        }

        .notification.error {
            background: #dc3545;
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <!-- SIDEBAR -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h1><i class="fas fa-robot"></i> AI Чат</h1>
                <p>Множество моделей и промптов</p>
                <div class="user-info">
                    <div class="user-avatar-main"><?php echo $_SESSION['user_data']['avatar']; ?></div>
                    <div class="user-details">
                        <strong><?php echo htmlspecialchars($_SESSION['user_data']['name']); ?></strong>
                        <small><?php echo htmlspecialchars($_SESSION['user_data']['role']); ?></small>
                        <div class="login-time">Вход: <?php echo date('d.m.Y H:i', $_SESSION['login_time']); ?></div>
                    </div>
                </div>
                <div class="redis-status <?php echo $redis_connected ? 'connected' : 'disconnected'; ?>">
                    <i class="fas fa-<?php echo $redis_connected ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                    Redis: <?php echo $redis_connected ? 'Подключен (TTL: 24ч)' : 'Отключен'; ?>
                    <?php if (!$redis_connected && isset($redis_error)): ?>
                        <br><small><?php echo htmlspecialchars($redis_error); ?></small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="settings-section">
                <div class="setting-group">
                    <label class="setting-label">🤖 AI Модель:</label>
                    <select class="setting-select" id="modelSelect">
                        <?php 
                        $categoryNames = [
                            'free' => '🆓 БЕСПЛАТНЫЕ',
                            'budget' => '💰 БЮДЖЕТНЫЕ',
                            'premium' => '🥇 ПРЕМИУМ',
                            'newest' => '🚀 НОВЕЙШИЕ'
                        ];
                        
                        $categorizedModels = [];
                        foreach ($models as $key => $model) {
                            $categorizedModels[$model['category']][$key] = $model;
                        }
                        
                        foreach ($categoryNames as $category => $categoryName) {
                            if (isset($categorizedModels[$category])) {
                                echo '<optgroup label="' . $categoryName . '">';
                                foreach ($categorizedModels[$category] as $key => $model) {
                                    $selected = $key === 'qwen/qwen-2.5-72b-instruct:free' ? 'selected' : '';
                                    echo '<option value="' . $key . '" ' . $selected . '>';
                                    echo $model['name'];
                                    echo '</option>';
                                }
                                echo '</optgroup>';
                            }
                        }
                        ?>
                    </select>
                    <div class="model-info" id="modelInfo"></div>
                </div>

                <div class="setting-group">
                    <label class="setting-label">
                        🎭 Тип ассистента: 
                        <span id="customPromptsCounter" style="background: #28a745; color: white; padding: 2px 6px; border-radius: 10px; font-size: 0.6rem; margin-left: 5px;"></span>
                    </label>
                    <select class="setting-select" id="promptSelect">
                        <optgroup label="🏠 СИСТЕМНЫЕ ПРОМПТЫ">
                            <?php foreach ($prompts as $key => $prompt): ?>
                                <option value="<?php echo $key; ?>" <?php echo $key === 'general' ? 'selected' : ''; ?>>
                                    <?php echo $prompt['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="✨ МОИ ПРОМПТЫ" id="customPromptsGroup">
                            <!-- Кастомные промпты загружаются через JavaScript -->
                        </optgroup>
                    </select>
                    <div class="prompt-info" id="promptInfo"></div>
                    
                    <!-- Кнопки управления промптами -->
                    <div class="prompt-controls">
                        <button class="prompt-btn create" onclick="showCreatePromptModal()">
                            <i class="fas fa-plus"></i> Создать промпт
                        </button>
                        <button class="prompt-btn manage" onclick="showManagePromptsModal()">
                            <i class="fas fa-cog"></i> Управление
                        </button>
                    </div>
                </div>
            </div>

            <div class="chat-controls">
                <button class="control-btn" onclick="clearChat()">
                    <i class="fas fa-trash-alt"></i> Очистить чат
                </button>
                <button class="control-btn export" onclick="exportChat()">
                    <i class="fas fa-download"></i> Экспорт истории
                </button>
                <a href="?action=logout" class="control-btn logout" onclick="return confirm('Выйти из системы?')">
                    <i class="fas fa-sign-out-alt"></i> Выйти
                </a>
            </div>
        </div>

        <!-- MAIN CHAT AREA -->
        <div class="chat-main">
            <div class="chat-header">
                <div class="chat-title">
                    <span class="header-avatar"><?php echo $_SESSION['user_data']['avatar']; ?></span>
                    Чат: <?php echo htmlspecialchars($_SESSION['user_data']['name']); ?>
                    <span class="user-role">(<?php echo htmlspecialchars($_SESSION['user_data']['role']); ?>)</span>
                </div>
                <div class="chat-stats">
                    <div class="stat-item">
                        <i class="fas fa-message"></i>
                        <span id="messageCount"><?php echo count($chat_history); ?></span> сообщений
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-clock"></i>
                        TTL: 24 часа
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-sync"></i>
                        Синхронизация между устройствами
                    </div>
                </div>
            </div>

            <div class="chat-messages" id="chatMessages">
                <!-- Сообщения будут загружены через JavaScript -->
            </div>

            <div class="typing-indicator" id="typingIndicator">
                <div class="typing-content">
                    <div class="typing-dots">
                        <div class="typing-dot"></div>
                        <div class="typing-dot"></div>
                        <div class="typing-dot"></div>
                    </div>
                    <span>ИИ печатает...</span>
                </div>
            </div>

            <div class="chat-input-area">
                <div class="input-container">
                    <textarea 
                        class="message-input" 
                        id="messageInput" 
                        placeholder="Напишите ваше сообщение..."
                        rows="1"
                    ></textarea>
                    <button class="send-btn" id="sendBtn" onclick="sendMessage()">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MOBILE MENU BUTTON -->
    <button class="mobile-menu-btn" id="mobileMenuBtn" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- МОДАЛЬНЫЕ ОКНА -->
    <!-- Создание промпта -->
    <div class="modal-overlay" id="createPromptModal">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-title">
                    <i class="fas fa-magic"></i>
                    Создать свой промпт
                </div>
                <button class="modal-close" onclick="hideCreatePromptModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="createPromptForm">
                    <div class="form-row">
                        <label class="form-label">Название промпта:</label>
                        <input type="text" class="form-input" id="promptName" placeholder="Например: Контент-маркетолог" required>
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Описание:</label>
                        <input type="text" class="form-input" id="promptDescription" placeholder="Краткое описание роли и задач" required>
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Выберите иконку:</label>
                        <div class="emoji-picker" id="emojiPicker">
                            <span class="emoji-item" data-emoji="🎯">🎯</span>
                            <span class="emoji-item" data-emoji="🚀">🚀</span>
                            <span class="emoji-item" data-emoji="💡">💡</span>
                            <span class="emoji-item" data-emoji="⚡">⚡</span>
                            <span class="emoji-item" data-emoji="🔥">🔥</span>
                            <span class="emoji-item" data-emoji="💎">💎</span>
                            <span class="emoji-item" data-emoji="🎨">🎨</span>
                            <span class="emoji-item" data-emoji="📱">📱</span>
                            <span class="emoji-item" data-emoji="🎵">🎵</span>
                            <span class="emoji-item" data-emoji="📷">📷</span>
                            <span class="emoji-item" data-emoji="🎮">🎮</span>
                            <span class="emoji-item" data-emoji="🏆">🏆</span>
                            <span class="emoji-item" data-emoji="🌟">🌟</span>
                            <span class="emoji-item" data-emoji="🔮">🔮</span>
                            <span class="emoji-item" data-emoji="🎭">🎭</span>
                            <span class="emoji-item" data-emoji="🍕">🍕</span>
                            <span class="emoji-item" data-emoji="🌍">🌍</span>
                            <span class="emoji-item" data-emoji="🎪">🎪</span>
                        </div>
                        <input type="hidden" id="selectedEmoji" value="🎯">
                    </div>
                    
                    <div class="form-row">
                        <label class="form-label">Системный промпт:</label>
                        <textarea class="form-textarea" id="promptContent" placeholder="Опишите детально, как должен вести себя AI в этой роли. Например:

Ты профессиональный контент-маркетолог с 10+ лет опыта. Специализируешься на создании вирусного контента, стратегий продвижения в социальных сетях и анализе аудитории. Умеешь создавать цепляющие заголовки, писать посты для разных платформ и планировать контент-календари." required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="modal-btn secondary" onclick="hideCreatePromptModal()">
                    <i class="fas fa-times"></i> Отмена
                </button>
                <button class="modal-btn primary" onclick="saveCustomPrompt()">
                    <i class="fas fa-save"></i> Сохранить промпт
                </button>
            </div>
        </div>
    </div>

    <!-- Управление промптами -->
    <div class="modal-overlay" id="managePromptsModal">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-title">
                    <i class="fas fa-cog"></i>
                    Управление промптами
                </div>
                <button class="modal-close" onclick="hideManagePromptsModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="prompts-info" style="background: #e3f2fd; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #2196f3;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong>📊 Ваши промпты:</strong> <span id="promptsCountDisplay">0</span>
                        </div>
                        <div style="font-size: 0.8rem; color: #6c757d;">
                            Сохранено в браузере для: <?php echo htmlspecialchars($_SESSION['username']); ?>
                        </div>
                    </div>
                </div>
                
                <div class="custom-prompts-list" id="customPromptsList">
                    <!-- Список промптов загружается через JavaScript -->
                </div>
            </div>
            <div class="modal-footer">
                <button class="modal-btn secondary" onclick="console.log('Кнопка экспорт нажата'); exportCustomPrompts();">
                    <i class="fas fa-download"></i> Экспорт
                </button>
                <button class="modal-btn secondary" onclick="console.log('Кнопка импорт нажата'); showImportPrompts();">
                    <i class="fas fa-upload"></i> Импорт
                </button>
                <button class="modal-btn primary" onclick="hideManagePromptsModal()">
                    <i class="fas fa-check"></i> Готово
                </button>
            </div>
        </div>
    </div>

    <script>
        // Данные о моделях и промптах
        const models = <?php echo json_encode($models); ?>;
        const prompts = <?php echo json_encode($prompts); ?>;
        let customPrompts = {}; // Кастомные промпты пользователя
        
        let isTyping = false;

        // Инициализация при загрузке
        document.addEventListener('DOMContentLoaded', function() {
            loadCustomPrompts();
            updateModelInfo();
            updatePromptInfo();
            loadChatHistory();
            setupEventListeners();
            setupEmojiPicker();
        });

        // === УПРАВЛЕНИЕ КАСТОМНЫМИ ПРОМПТАМИ ===

        function loadCustomPrompts() {
            try {
                const saved = localStorage.getItem('customPrompts_<?php echo $_SESSION['username']; ?>');
                if (saved) {
                    customPrompts = JSON.parse(saved);
                    updatePromptsSelect();
                }
            } catch (e) {
                console.error('Ошибка загрузки кастомных промптов:', e);
                customPrompts = {};
            }
        }

        function saveCustomPromptsToStorage() {
            try {
                localStorage.setItem('customPrompts_<?php echo $_SESSION['username']; ?>', JSON.stringify(customPrompts));
            } catch (e) {
                console.error('Ошибка сохранения кастомных промптов:', e);
                showNotification('Ошибка сохранения промптов', 'error');
            }
        }

        function updatePromptsSelect() {
            const customGroup = document.getElementById('customPromptsGroup');
            const promptSelect = document.getElementById('promptSelect');
            const counter = document.getElementById('customPromptsCounter');
            const currentSelected = promptSelect.value;
            const savedPrompt = localStorage.getItem('selected_prompt_<?php echo $_SESSION['username']; ?>');
            
            // Очищаем группу кастомных промптов
            customGroup.innerHTML = '';
            
            // Добавляем кастомные промпты
            Object.keys(customPrompts).forEach(key => {
                const prompt = customPrompts[key];
                const option = document.createElement('option');
                option.value = 'custom_' + key;
                option.textContent = `${prompt.icon} ${prompt.name}`;
                customGroup.appendChild(option);
            });
            
            // Обновляем счетчик
            const customCount = Object.keys(customPrompts).length;
            if (customCount > 0) {
                counter.textContent = `+${customCount} своих`;
                counter.style.display = 'inline';
                customGroup.style.display = 'block';
            } else {
                counter.style.display = 'none';
                customGroup.style.display = 'none';
            }
            
            // Восстанавливаем выбор
            let targetPrompt = currentSelected || savedPrompt || 'general';
            
            // Проверяем, что промпт существует
            const options = [...promptSelect.options];
            if (options.some(opt => opt.value === targetPrompt)) {
                promptSelect.value = targetPrompt;
            } else {
                promptSelect.value = 'general';
            }
            
            updatePromptInfo();
        }

        function showCreatePromptModal() {
            document.getElementById('createPromptModal').classList.add('show');
            document.getElementById('promptName').focus();
            
            // Сбрасываем форму
            document.getElementById('createPromptForm').reset();
            document.getElementById('selectedEmoji').value = '🎯';
            document.querySelectorAll('.emoji-item').forEach(item => {
                item.classList.remove('selected');
            });
            document.querySelector('[data-emoji="🎯"]').classList.add('selected');
            
            // Восстанавливаем оригинальную кнопку если была изменена
            const saveBtn = document.querySelector('#createPromptModal .modal-btn.primary');
            saveBtn.innerHTML = '<i class="fas fa-save"></i> Сохранить промпт';
            saveBtn.onclick = saveCustomPrompt;
            
            // Восстанавливаем черновик если есть
            const draft = localStorage.getItem('promptDraft_<?php echo $_SESSION['username']; ?>');
            if (draft) {
                try {
                    const draftData = JSON.parse(draft);
                    if (draftData.name || draftData.description || draftData.content) {
                        const restore = confirm('Обнаружен черновик промпта. Восстановить?');
                        if (restore) {
                            nameField.value = draftData.name || '';
                            descField.value = draftData.description || '';
                            contentField.value = draftData.content || '';
                            document.getElementById('selectedEmoji').value = draftData.emoji || '🎯';
                            
                            // Обновляем выбранную иконку
                            document.querySelectorAll('.emoji-item').forEach(item => {
                                item.classList.remove('selected');
                                if (item.dataset.emoji === draftData.emoji) {
                                    item.classList.add('selected');
                                }
                            });
                        }
                    }
                } catch (e) {
                    console.error('Ошибка восстановления черновика:', e);
                }
            }
        }

        function setupFormValidation() {
            const nameField = document.getElementById('promptName');
            const descField = document.getElementById('promptDescription');
            const contentField = document.getElementById('promptContent');
            const saveBtn = document.querySelector('#createPromptModal .modal-btn.primary');
            
            function validateForm() {
                const isValid = nameField.value.trim() && descField.value.trim() && contentField.value.trim();
                saveBtn.disabled = !isValid;
                saveBtn.style.opacity = isValid ? '1' : '0.5';
            }
            
            // Начальная валидация
            validateForm();
            
            // Валидация при изменении + автосохранение черновика
            [nameField, descField, contentField].forEach(field => {
                field.addEventListener('input', function() {
                    validateForm();
                    // Автосохранение черновика
                    const draft = {
                        name: nameField.value,
                        description: descField.value,
                        content: contentField.value,
                        emoji: document.getElementById('selectedEmoji').value
                    };
                    localStorage.setItem('promptDraft_<?php echo $_SESSION['username']; ?>', JSON.stringify(draft));
                });
            });
        }

        function hideCreatePromptModal() {
            document.getElementById('createPromptModal').classList.remove('show');
        }

        function setupEmojiPicker() {
            document.querySelectorAll('.emoji-item').forEach(item => {
                item.addEventListener('click', function() {
                    // Убираем выделение с других
                    document.querySelectorAll('.emoji-item').forEach(el => el.classList.remove('selected'));
                    
                    // Выделяем текущий
                    this.classList.add('selected');
                    document.getElementById('selectedEmoji').value = this.dataset.emoji;
                });
            });
        }

        function saveCustomPrompt() {
            const name = document.getElementById('promptName').value.trim();
            const description = document.getElementById('promptDescription').value.trim();
            const content = document.getElementById('promptContent').value.trim();
            const icon = document.getElementById('selectedEmoji').value;
            
            if (!name || !description || !content) {
                showNotification('Заполните все поля!', 'error');
                return;
            }
            
            // Генерируем уникальный ключ
            const key = name.toLowerCase().replace(/[^a-zа-яё0-9]/g, '_') + '_' + Date.now();
            
            // Сохраняем промпт
            customPrompts[key] = {
                name: name,
                description: description,
                icon: icon,
                system_prompt: content,
                created_at: new Date().toISOString(),
                author: '<?php echo $_SESSION['username']; ?>'
            };
            
            saveCustomPromptsToStorage();
            updatePromptsSelect();
            hideCreatePromptModal();
            
            // Очищаем черновик после успешного сохранения
            localStorage.removeItem('promptDraft_<?php echo $_SESSION['username']; ?>');
            
            // Автоматически выбираем созданный промпт
            document.getElementById('promptSelect').value = 'custom_' + key;
            updatePromptInfo();
            
            showNotification(`🎉 Промпт "${name}" создан!`, 'success');
        }

        function showManagePromptsModal() {
            console.log('Открытие модального окна управления промптами');
            console.log('Количество кастомных промптов:', Object.keys(customPrompts).length);
            console.log('Промпты:', customPrompts);
            
            document.getElementById('managePromptsModal').classList.add('show');
            updateCustomPromptsList();
        }

        function hideManagePromptsModal() {
            document.getElementById('managePromptsModal').classList.remove('show');
        }

        function updateCustomPromptsList() {
            const list = document.getElementById('customPromptsList');
            const counter = document.getElementById('promptsCountDisplay');
            const promptsCount = Object.keys(customPrompts).length;
            
            // Обновляем счетчик
            if (counter) {
                counter.textContent = promptsCount;
            }
            
            if (promptsCount === 0) {
                list.innerHTML = `
                    <div class="empty-prompts">
                        <i class="fas fa-magic"></i>
                        <h3>Нет своих промптов</h3>
                        <p>Создайте первый промпт для персонализации AI</p>
                        <button class="modal-btn primary" onclick="hideManagePromptsModal(); showCreatePromptModal();" style="margin-top: 15px;">
                            <i class="fas fa-plus"></i> Создать первый промпт
                        </button>
                    </div>
                `;
                return;
            }
            
            list.innerHTML = '';
            
            Object.keys(customPrompts).forEach(key => {
                const prompt = customPrompts[key];
                const item = document.createElement('div');
                item.className = 'custom-prompt-item';
                item.innerHTML = `
                    <div class="prompt-item-header">
                        <div class="prompt-item-title">
                            <span>${prompt.icon}</span>
                            <span>${prompt.name}</span>
                        </div>
                        <div class="prompt-item-actions">
                            <button class="prompt-action-btn edit" onclick="editCustomPrompt('${key}')" title="Редактировать">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="prompt-action-btn delete" onclick="deleteCustomPrompt('${key}')" title="Удалить">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="prompt-item-description">${prompt.description}</div>
                    <div class="prompt-item-preview">${prompt.system_prompt.substring(0, 150)}${prompt.system_prompt.length > 150 ? '...' : ''}</div>
                `;
                list.appendChild(item);
            });
        }

        function editCustomPrompt(key) {
            const prompt = customPrompts[key];
            if (!prompt) return;
            
            // Заполняем форму данными промпта
            document.getElementById('promptName').value = prompt.name;
            document.getElementById('promptDescription').value = prompt.description;
            document.getElementById('promptContent').value = prompt.system_prompt;
            document.getElementById('selectedEmoji').value = prompt.icon;
            
            // Выделяем иконку
            document.querySelectorAll('.emoji-item').forEach(item => {
                item.classList.remove('selected');
                if (item.dataset.emoji === prompt.icon) {
                    item.classList.add('selected');
                }
            });
            
            // Показываем модальное окно создания в режиме редактирования
            hideManagePromptsModal();
            showCreatePromptModal();
            
            // Меняем кнопку сохранения
            const saveBtn = document.querySelector('#createPromptModal .modal-btn.primary');
            saveBtn.innerHTML = '<i class="fas fa-save"></i> Обновить промпт';
            saveBtn.onclick = function() {
                updateCustomPrompt(key);
            };
        }

        function updateCustomPrompt(key) {
            const name = document.getElementById('promptName').value.trim();
            const description = document.getElementById('promptDescription').value.trim();
            const content = document.getElementById('promptContent').value.trim();
            const icon = document.getElementById('selectedEmoji').value;
            
            if (!name || !description || !content) {
                showNotification('Заполните все поля!', 'error');
                return;
            }
            
            // Обновляем промпт
            customPrompts[key] = {
                ...customPrompts[key],
                name: name,
                description: description,
                icon: icon,
                system_prompt: content,
                updated_at: new Date().toISOString()
            };
            
            saveCustomPromptsToStorage();
            updatePromptsSelect();
            hideCreatePromptModal();
            
            // Восстанавливаем кнопку
            const saveBtn = document.querySelector('#createPromptModal .modal-btn.primary');
            saveBtn.innerHTML = '<i class="fas fa-save"></i> Сохранить промпт';
            saveBtn.onclick = saveCustomPrompt;
            
            showNotification(`✏️ Промпт "${name}" обновлен!`, 'success');
        }

        function deleteCustomPrompt(key) {
            const prompt = customPrompts[key];
            if (!prompt) return;
            
            if (confirm(`Удалить промпт "${prompt.name}"?`)) {
                delete customPrompts[key];
                saveCustomPromptsToStorage();
                updatePromptsSelect();
                updateCustomPromptsList();
                showNotification(`🗑️ Промпт "${prompt.name}" удален`, 'success');
            }
        }

        function exportCustomPrompts() {
            console.log('Экспорт промптов. Количество:', Object.keys(customPrompts).length);
            
            if (Object.keys(customPrompts).length === 0) {
                showNotification('❌ Нет промптов для экспорта. Создайте хотя бы один промпт!', 'error');
                return;
            }
            
            try {
                const exportData = {
                    prompts: customPrompts,
                    exported_at: new Date().toISOString(),
                    exported_by: '<?php echo $_SESSION['username']; ?>',
                    version: '1.0',
                    total_prompts: Object.keys(customPrompts).length
                };
                
                const jsonString = JSON.stringify(exportData, null, 2);
                const blob = new Blob([jsonString], { type: 'application/json;charset=utf-8' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `custom-prompts-<?php echo $_SESSION['username']; ?>-${new Date().toISOString().slice(0, 10)}.json`;
                a.style.display = 'none';
                
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                
                showNotification(`📦 Экспортировано ${Object.keys(customPrompts).length} промптов`, 'success');
                console.log('Экспорт успешен');
            } catch (error) {
                console.error('Ошибка экспорта:', error);
                showNotification('❌ Ошибка при экспорте промптов', 'error');
            }
        }

        function showImportPrompts() {
            console.log('Запуск импорта промптов');
            
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = '.json';
            input.style.display = 'none';
            
            input.onchange = function(e) {
                console.log('Файл выбран:', e.target.files[0]);
                
                const file = e.target.files[0];
                if (!file) {
                    console.log('Файл не выбран');
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    try {
                        console.log('Читаем файл...');
                        const data = JSON.parse(e.target.result);
                        console.log('Данные из файла:', data);
                        
                        if (data.prompts && typeof data.prompts === 'object') {
                            const importedCount = Object.keys(data.prompts).length;
                            console.log(`Найдено промптов для импорта: ${importedCount}`);
                            
                            // Проверяем на конфликты имен
                            const conflicts = [];
                            Object.keys(data.prompts).forEach(key => {
                                if (customPrompts[key]) {
                                    conflicts.push(data.prompts[key].name);
                                }
                            });
                            
                            let proceed = true;
                            if (conflicts.length > 0) {
                                proceed = confirm(`Найдены конфликты с существующими промптами:\n${conflicts.join(', ')}\n\nПерезаписать?`);
                            }
                            
                            if (proceed) {
                                Object.assign(customPrompts, data.prompts);
                                saveCustomPromptsToStorage();
                                updatePromptsSelect();
                                updateCustomPromptsList();
                                showNotification(`📥 Импортировано ${importedCount} промптов!`, 'success');
                                console.log('Импорт завершен успешно');
                            } else {
                                console.log('Импорт отменен пользователем');
                                showNotification('❌ Импорт отменен', 'error');
                            }
                        } else {
                            console.error('Неверный формат файла:', data);
                            showNotification('❌ Неверный формат файла. Ожидается JSON с полем "prompts"', 'error');
                        }
                    } catch (err) {
                        console.error('Ошибка парсинга JSON:', err);
                        showNotification('❌ Ошибка чтения файла. Убедитесь, что это валидный JSON', 'error');
                    }
                };
                
                reader.onerror = function() {
                    console.error('Ошибка чтения файла');
                    showNotification('❌ Ошибка чтения файла', 'error');
                };
                
                reader.readAsText(file);
            };
            
            // Добавляем в DOM и кликаем
            document.body.appendChild(input);
            input.click();
            document.body.removeChild(input);
        }

        function setupEventListeners() {
            // Enter для отправки сообщения
            document.getElementById('messageInput').addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });

            // Автоматическое изменение высоты textarea
            document.getElementById('messageInput').addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });

            // Обновление информации при изменении модели/промпта
            document.getElementById('modelSelect').addEventListener('change', updateModelInfo);
            document.getElementById('promptSelect').addEventListener('change', function() {
                updatePromptInfo();
                // Сохраняем выбранный промпт
                localStorage.setItem('selected_prompt_<?php echo $_SESSION['username']; ?>', this.value);
            });
        }

        function updateModelInfo() {
            const select = document.getElementById('modelSelect');
            const info = document.getElementById('modelInfo');
            const selectedModel = select.value;
            const model = models[selectedModel];
            
            if (model) {
                info.className = 'model-info ' + model.category;
                info.innerHTML = `
                    <strong>${model.name}</strong><br>
                    ${model.description}<br>
                    <small>Цена: ${model.cost_1000} за 1000 запросов</small><br>
                    <small>Скорость: ${model.speed} | Качество: ${model.quality}</small>
                `;
            }
        }

        function updatePromptInfo() {
            const select = document.getElementById('promptSelect');
            const info = document.getElementById('promptInfo');
            const selectedPrompt = select.value;
            const prompt = prompts[selectedPrompt];
            
            if (prompt) {
                info.innerHTML = `
                    <strong>${prompt.icon} ${prompt.name}</strong><br>
                    ${prompt.description}
                `;
            }
        }

        function loadChatHistory() {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_history'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayChatHistory(data.history);
                }
            })
            .catch(error => {
                console.error('Ошибка загрузки истории:', error);
            });
        }

        function displayChatHistory(history) {
            const chatMessages = document.getElementById('chatMessages');
            
            if (history.length === 0) {
                const customPromptsHtml = Object.keys(customPrompts).map(key => {
                    const prompt = customPrompts[key];
                    return `
                        <div class="quick-prompt custom" onclick="setPromptAndFocus('custom_${key}')">
                            <div class="quick-prompt-icon">${prompt.icon}</div>
                            <div class="quick-prompt-title">${prompt.name} <span style="background: #28a745; color: white; padding: 1px 4px; border-radius: 6px; font-size: 0.6rem;">СВОЙ</span></div>
                            <div class="quick-prompt-desc">${prompt.description}</div>
                        </div>
                    `;
                }).join('');
                
                chatMessages.innerHTML = `
                    <div class="empty-chat">
                        <i class="fas fa-search"></i>
                        <h3>🧠 Умный AI чат с поиском!</h3>
                        <p>Выберите тип ассистента или создайте свой промпт.<br>
                        <strong>AI ищет в истории + персональные промпты!</strong></p>
                        
                        <div class="search-examples">
                            <h4>🔍 Примеры умного поиска:</h4>
                            <div class="example-queries">
                                <span class="example-query" onclick="setExampleQuery(this)">Найди где я говорил о программировании</span>
                                <span class="example-query" onclick="setExampleQuery(this)">Сколько токенов я потратил?</span>
                                <span class="example-query" onclick="setExampleQuery(this)">Какие модели я использовал?</span>
                                <span class="example-query" onclick="setExampleQuery(this)">Покажи мою статистику за неделю</span>
                                <span class="example-query" onclick="setExampleQuery(this)">Что я писал вчера?</span>
                                <span class="example-query" onclick="setExampleQuery(this)">Найди мои SEO тексты</span>
                            </div>
                        </div>
                        
                        <div class="quick-prompts">
                            ${Object.entries(prompts).map(([key, prompt]) => `
                                <div class="quick-prompt" onclick="setPromptAndFocus('${key}')">
                                    <div class="quick-prompt-icon">${prompt.icon}</div>
                                    <div class="quick-prompt-title">${prompt.name}</div>
                                    <div class="quick-prompt-desc">${prompt.description}</div>
                                </div>
                            `).join('')}
                            ${customPromptsHtml}
                            <div class="quick-prompt create-new" onclick="showCreatePromptModal()">
                                <div class="quick-prompt-icon">➕</div>
                                <div class="quick-prompt-title">Создать свой промпт</div>
                                <div class="quick-prompt-desc">Персонализируйте AI под свои задачи</div>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                chatMessages.innerHTML = '';
                history.forEach(message => {
                    if (message.role !== 'system') {
                        addMessageToUI(message);
                    }
                });
                scrollToBottom();
            }
            
            updateMessageCount(history.filter(m => m.role !== 'system').length);
        }

        function setPromptAndFocus(promptKey) {
            document.getElementById('promptSelect').value = promptKey;
            updatePromptInfo();
            document.getElementById('messageInput').focus();
        }

        function setExampleQuery(element) {
            const query = element.textContent;
            const messageInput = document.getElementById('messageInput');
            messageInput.value = query;
            messageInput.focus();
            
            // Подсветка выбранного примера
            document.querySelectorAll('.example-query').forEach(el => {
                el.style.background = 'rgba(102, 126, 234, 0.8)';
            });
            element.style.background = '#28a745';
            
            // Убираем подсветку через 2 секунды
            setTimeout(() => {
                element.style.background = 'rgba(102, 126, 234, 0.8)';
            }, 2000);
        }

        function addMessageToUI(message) {
            const chatMessages = document.getElementById('chatMessages');
            
            // Удаляем empty-chat если есть
            const emptyChat = chatMessages.querySelector('.empty-chat');
            if (emptyChat) {
                emptyChat.remove();
            }
            
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${message.role}`;
            
            const modelInfo = message.model ? models[message.model] : null;
            const promptInfo = message.prompt_type ? prompts[message.prompt_type] : null;
            
            // Генерируем уникальный ID для кнопки копирования
            const copyBtnId = 'copy-btn-' + Math.random().toString(36).substr(2, 9);
            
            let metaInfo = '';
            if (message.role === 'assistant' && modelInfo && promptInfo) {
                let searchIndicator = '';
                if (message.search_performed) {
                    searchIndicator = '<span class="search-indicator">🔍 Поиск выполнен</span><span>•</span>';
                }
                
                metaInfo = `
                    <div class="message-meta">
                        ${searchIndicator}
                        <span>${promptInfo.icon} ${promptInfo.name}</span>
                        <span>•</span>
                        <span>${modelInfo.name}</span>
                        <span>•</span>
                        <span>${formatTime(message.timestamp)}</span>
                        <button class="copy-btn" id="${copyBtnId}" title="Копировать">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                `;
            } else if (message.role === 'user') {
                metaInfo = `
                    <div class="message-meta">
                        <span>${formatTime(message.timestamp)}</span>
                        <button class="copy-btn" id="${copyBtnId}" title="Копировать">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                `;
            }
            
            messageDiv.innerHTML = `
                <div class="message-content">${formatMessage(message.content)}</div>
                ${metaInfo}
            `;
            
            chatMessages.appendChild(messageDiv);
            
            // Добавляем обработчик клика для кнопки копирования
            const copyBtn = document.getElementById(copyBtnId);
            if (copyBtn) {
                copyBtn.addEventListener('click', function() {
                    copyToClipboard(message.content);
                });
            }
            
            scrollToBottom();
        }

        function formatMessage(content) {
            // Простое форматирование: замена переносов строк на <br>
            return content.replace(/\n/g, '<br>');
        }

        function formatTime(timestamp) {
            const date = new Date(timestamp * 1000);
            return date.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
        }

        function sendMessage() {
            if (isTyping) return;
            
            const messageInput = document.getElementById('messageInput');
            const message = messageInput.value.trim();
            
            if (!message) return;
            
            const model = document.getElementById('modelSelect').value;
            const promptType = document.getElementById('promptSelect').value;
            
            // Подготавливаем данные для отправки
            let postData = `action=send_message&message=${encodeURIComponent(message)}&model=${encodeURIComponent(model)}&prompt_type=${encodeURIComponent(promptType)}`;
            
            // Если выбран кастомный промпт, добавляем его содержимое
            if (promptType.startsWith('custom_')) {
                const key = promptType.replace('custom_', '');
                const customPrompt = customPrompts[key];
                if (customPrompt && customPrompt.system_prompt) {
                    postData += `&custom_prompt=${encodeURIComponent(customPrompt.system_prompt)}`;
                }
            }
            
            // Добавляем сообщение пользователя в UI
            const userMessage = {
                role: 'user',
                content: message,
                timestamp: Math.floor(Date.now() / 1000),
                model: model,
                prompt_type: promptType
            };
            
            addMessageToUI(userMessage);
            
            // Очищаем поле ввода
            messageInput.value = '';
            messageInput.style.height = 'auto';
            
            // Показываем индикатор печати
            showTypingIndicator(message);
            
            // Отключаем кнопку отправки
            const sendBtn = document.getElementById('sendBtn');
            sendBtn.disabled = true;
            sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            isTyping = true;
            
            // Отправляем запрос
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: postData
            })
            .then(response => response.json())
            .then(data => {
                hideTypingIndicator();
                
                if (data.success) {
                    const aiMessage = {
                        role: 'assistant',
                        content: data.response,
                        timestamp: Math.floor(Date.now() / 1000),
                        model: data.model,
                        prompt_type: data.prompt_type,
                        search_performed: data.search_performed || false
                    };
                    
                    addMessageToUI(aiMessage);
                    updateMessageCount();
                    
                    // Показываем уведомление если был выполнен поиск
                    if (data.search_performed) {
                        let promptName = '';
                        if (promptType.startsWith('custom_')) {
                            const key = promptType.replace('custom_', '');
                            promptName = customPrompts[key]?.name || 'Кастомный промпт';
                        } else {
                            promptName = prompts[promptType]?.name || 'Промпт';
                        }
                        showNotification(`🔍 Поиск выполнен в роли: ${promptName}`, 'success');
                    }
                } else {
                    showNotification('Ошибка: ' + data.error, 'error');
                }
            })
            .catch(error => {
                hideTypingIndicator();
                showNotification('Ошибка сети: ' + error.message, 'error');
                console.error('Ошибка:', error);
            })
            .finally(() => {
                // Восстанавливаем кнопку отправки
                sendBtn.disabled = false;
                sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
                isTyping = false;
                messageInput.focus();
            });
        }

        function showTypingIndicator(message = null) {
            const indicator = document.getElementById('typingIndicator');
            const typingText = indicator.querySelector('span');
            
            // Проверяем если сообщение содержит поисковые слова
            if (message) {
                const messageText = message.toLowerCase();
                const searchTriggers = ['найди', 'найти', 'поиск', 'ищи', 'сколько', 'статистика', 'покажи', 'что я'];
                
                const needsSearch = searchTriggers.some(trigger => messageText.includes(trigger));
                
                if (needsSearch) {
                    typingText.textContent = '🔍 ИИ ищет в истории...';
                } else {
                    typingText.textContent = 'ИИ печатает...';
                }
            } else {
                typingText.textContent = 'ИИ печатает...';
            }
            
            indicator.classList.add('show');
            scrollToBottom();
        }

        function hideTypingIndicator() {
            document.getElementById('typingIndicator').classList.remove('show');
        }

        function scrollToBottom() {
            const chatMessages = document.getElementById('chatMessages');
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        function updateMessageCount(count = null) {
            if (count === null) {
                const messages = document.querySelectorAll('.message');
                count = messages.length;
            }
            document.getElementById('messageCount').textContent = count;
        }

        function clearChat() {
            if (!confirm('Очистить историю чата?')) return;
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=clear_history'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadChatHistory();
                    showNotification('История чата очищена');
                } else {
                    showNotification('Ошибка при очистке: ' + data.error, 'error');
                }
            })
            .catch(error => {
                showNotification('Ошибка сети: ' + error.message, 'error');
            });
        }

        function exportChat() {
            const messages = document.querySelectorAll('.message');
            if (messages.length === 0) {
                showNotification('Нет сообщений для экспорта', 'error');
                return;
            }
            
            let exportText = `AI Чат - Экспорт истории\n`;
            exportText += `Пользователь: <?php echo htmlspecialchars($_SESSION['user_data']['name']); ?> (<?php echo htmlspecialchars($_SESSION['username']); ?>)\n`;
            exportText += `Роль: <?php echo htmlspecialchars($_SESSION['user_data']['role']); ?>\n`;
            exportText += `Дата экспорта: ${new Date().toLocaleString('ru-RU')}\n`;
            exportText += `Всего сообщений: ${messages.length}\n\n`;
            exportText += `${'='.repeat(60)}\n\n`;
            
            messages.forEach((messageEl, index) => {
                const isUser = messageEl.classList.contains('user');
                const content = messageEl.querySelector('.message-content').textContent;
                const meta = messageEl.querySelector('.message-meta');
                const timestamp = meta ? meta.textContent.split('•').pop().trim() : '';
                
                exportText += `${index + 1}. ${isUser ? '<?php echo $_SESSION['user_data']['avatar']; ?> ' + '<?php echo htmlspecialchars($_SESSION['user_data']['name']); ?>' : '🤖 ИИ Ассистент'}`;
                if (timestamp) exportText += ` (${timestamp})`;
                exportText += `:\n${content}\n\n`;
            });
            
            exportText += `${'='.repeat(60)}\n`;
            exportText += `Экспорт создан системой AI Чат\n`;
            exportText += `Redis TTL: 24 часа | Синхронизация между устройствами\n`;
            
            // Создаем и скачиваем файл
            const blob = new Blob([exportText], { type: 'text/plain;charset=utf-8' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `ai-chat-<?php echo htmlspecialchars($_SESSION['username']); ?>-${new Date().toISOString().slice(0, 10)}.txt`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            
            showNotification('История экспортирована');
        }

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                showNotification('Скопировано в буфер обмена');
            }).catch(err => {
                showNotification('Ошибка копирования', 'error');
            });
        }

        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => notification.classList.add('show'), 100);
            
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    if (document.body.contains(notification)) {
                        document.body.removeChild(notification);
                    }
                }, 300);
            }, type === 'error' ? 5000 : 3000);
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('open');
        }

        // Закрытие sidebar при клике вне его на мобильных
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                const sidebar = document.getElementById('sidebar');
                const mobileMenuBtn = document.getElementById('mobileMenuBtn');
                
                if (!sidebar.contains(e.target) && !mobileMenuBtn.contains(e.target)) {
                    sidebar.classList.remove('open');
                }
            }
        });

        // Автофокус на поле ввода при загрузке
        window.addEventListener('load', function() {
            document.getElementById('messageInput').focus();
        });

        // Закрытие модальных окон
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideCreatePromptModal();
                hideManagePromptsModal();
            }
        });

        // Закрытие модальных окон по клику на overlay
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                hideCreatePromptModal();
                hideManagePromptsModal();
            }
        });
    </script>
</body>
</html>