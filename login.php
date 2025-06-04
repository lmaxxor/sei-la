<?php
// login.php
require_once __DIR__ . '/sessao/session_handler.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Frases para o hero, versão de conversão
$hero_phrases = [
    "O saber da Terapia Ocupacional em áudio: evolução e atualização na palma da sua mão.",
    "Dê voz ao seu aprendizado: Terapia Ocupacional em áudio para o seu futuro.",
    "Amplie seus horizontes na Terapia Ocupacional: conteúdo em áudio feito sob medida para você.",
    "Conhecimento em Terapia Ocupacional que se encaixa na sua rotina, em formato de áudio."
];
$selected_hero_phrase = $hero_phrases[array_rand($hero_phrases)];

// Mensagens de sessão
$login_errors = $_SESSION['login_errors'] ?? [];
$login_email_attempt = $_SESSION['login_email_attempt'] ?? '';
$register_success_msg = $_SESSION['register_success'] ?? '';
unset($_SESSION['login_errors'], $_SESSION['login_email_attempt'], $_SESSION['register_success']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - AudioTO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary': '#007AFF',
                        'primary-dark': '#0056b3',
                        'secondary': '#5856D6',
                        'light-bg': '#F9FAFB',
                        'dark-text': '#1F2937',
                        'medium-text': '#4B5563',
                        'light-text': '#6B7280',
                        'danger-bg': '#FEE2E2',
                        'danger-text': '#B91C1C',
                        'success-bg': '#D1FAE5',
                        'success-text': '#065F46',
                    },
                    fontFamily: {
                        'sans': ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'Roboto', '"Helvetica Neue"', 'Arial', '"Noto Sans"', 'sans-serif', '"Apple Color Emoji"', '"Segoe UI Emoji"', '"Segoe UI Symbol"', '"Noto Color Emoji"'],
                        'raleway': ['Raleway', 'sans-serif'],
                    },
                    backgroundImage: {
                        'login-hero': "url('https://placehold.co/1200x800/3B82F6/FFFFFF?text=audio+to&font=raleway&font-size=90')",
                    },
                    animation: {
                        'fade-in-up': 'fadeInUp 0.6s ease-out forwards',
                        'fade-in': 'fadeIn 0.5s ease-out forwards',
                    },
                    keyframes: {
                        fadeInUp: {
                            '0%': { opacity: '0', transform: 'translateY(20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        },
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        }
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Raleway:wght@700;800&display=swap" rel="stylesheet">
    <style>
        html, body {
            height: 100%;
            font-family: 'Inter', sans-serif;
            overflow-x: hidden;
        }
        .form-input-container { position: relative; }
        .form-input {
            width: 100%; padding-left: 2.5rem; padding-right: 1rem; padding-top: .75rem; padding-bottom: .75rem;
            border: 1px solid #d1d5db; border-radius: .5rem;
            font-size: .875rem; color: #111827;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-input:focus { border-color: #007AFF; box-shadow: 0 0 0 2px #007aff44; outline: none; }
        .form-input-icon { position: absolute; left: .75rem; top: 50%; transform: translateY(-50%); pointer-events: none; }
        .form-label { display: block; margin-bottom: .375rem; color: #4B5563; font-size: .95rem; font-weight: 500; }
        .btn-primary-full {
            width: 100%; background: #007AFF; color: #fff; font-weight: 600;
            padding: .75rem 1.5rem; border-radius: .5rem;
            transition: all 0.3s; box-shadow: 0 2px 8px 0 #007aff1a;
        }
        .btn-primary-full:hover { background: #0056b3; transform: scale(1.03); }
        .message-box { font-size: .95rem; padding: .75rem; border-radius: .375rem; margin-bottom: .25rem; animation: fadeIn .5s; }
        .error-message { background: #FEE2E2; color: #B91C1C; border: 1px solid #B91C1C55; }
        .success-message { background: #D1FAE5; color: #065F46; border: 1px solid #065F4655; }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px);}
            to { opacity: 1; transform: translateY(0);}
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    </style>
</head>
<body class="bg-light-bg">

    <div class="flex flex-col md:flex-row min-h-screen">
        <div class="hidden md:flex md:w-1/2 lg:w-3/5 bg-login-hero bg-cover bg-center items-center justify-center p-12 text-white relative animate-fade-in">
            <div class="absolute inset-0 bg-primary opacity-80"></div>
            <div class="relative z-10 text-center animate-fade-in-up" style="animation-delay: 0.2s;">
                <h1 class="text-6xl lg:text-7xl font-bold mb-6 font-raleway">AudioTO</h1>
                <p class="text-xl lg:text-2xl font-light max-w-lg mx-auto">
                    <?php echo htmlspecialchars($selected_hero_phrase); ?>
                </p>
            </div>
        </div>

        <div class="w-full md:w-1/2 lg:w-2/5 flex items-center justify-center p-6 sm:p-8 lg:p-12 bg-light-bg">
            <div class="w-full max-w-md">
                <div class="text-center mb-8 md:hidden animate-fade-in">
                    <h1 class="text-5xl font-bold text-primary font-raleway">AudioTO</h1>
                    <p class="text-base text-medium-text mt-2">
                        <?php echo htmlspecialchars($selected_hero_phrase); ?>
                    </p>
                </div>

                <div class="bg-white p-8 md:p-10 rounded-xl shadow-2xl">
                    <div style="animation: fadeInUp 0.5s;">
                        <h2 class="text-2xl sm:text-3xl font-semibold text-dark-text text-center mb-1 sm:mb-2">Bem-vindo(a) de volta!</h2>
                        <p class="text-center text-medium-text mb-6 sm:mb-8">Entre na sua conta para continuar.</p>
                    </div>
                    
                    <div id="messageContainer" class="mb-4">
                        <?php if (!empty($login_errors)): ?>
                            <div class="message-box error-message">
                                <ul class="list-disc list-inside pl-2">
                                    <?php foreach ($login_errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($register_success_msg)): ?>
                            <div class="message-box success-message">
                                <?php echo htmlspecialchars($register_success_msg); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <form id="loginForm" action="login_handler.php" method="POST" class="space-y-6">
                        <div style="animation: fadeInUp 0.5s 0.1s both;">
                            <label for="email" class="form-label">Endereço de Email</label>
                            <div class="form-input-container">
                                <svg class="form-input-icon w-5 h-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                </svg>
                                <input type="email" id="email" name="email" class="form-input" placeholder="seu.email@exemplo.com" value="<?php echo htmlspecialchars($login_email_attempt); ?>" required autocomplete="email" autofocus>
                            </div>
                        </div>
                        
                        <div style="animation: fadeInUp 0.5s 0.2s both;">
                            <div class="flex justify-between items-center mb-1.5">
                                <label for="password" class="form-label">Senha</label>
                                <a href="esqueci_senha.php" class="text-xs text-primary hover:text-primary-dark font-medium transition-colors">Esqueceu-se da senha?</a>
                            </div>
                            <div class="form-input-container">
                                <svg class="form-input-icon w-5 h-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                </svg>
                                <input type="password" id="password" name="password" class="form-input" placeholder="A sua palavra-passe" required autocomplete="current-password">
                            </div>
                        </div>
                        
                        <div class="pt-2" style="animation: fadeInUp 0.5s 0.3s both;">
                            <button type="submit" class="btn-primary-full">
                                Entrar na Plataforma
                            </button>
                        </div>
                    </form>

                    <div class="mt-8 text-center" style="animation: fadeInUp 0.5s 0.4s both;">
                        <p class="text-sm text-medium-text">
                            Não tem uma conta? 
                            <a href="registrar.php" class="font-semibold text-primary hover:text-primary-dark transition-colors">Crie uma agora</a>
                        </p>
                    </div>
                </div>
                <p class="text-center text-xs text-light-text mt-10 animate-fade-in" style="animation-delay: 0.5s;">
                    &copy; <span id="currentYear"></span> AudioTO. Todos os direitos reservados.
                </p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const currentYearSpan = document.getElementById('currentYear');
            if (currentYearSpan) {
                currentYearSpan.textContent = new Date().getFullYear();
            }
        });
    </script>
</body>
</html>
