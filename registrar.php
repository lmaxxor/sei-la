<?php
// registrar.php
require_once __DIR__ . '/sessao/session_handler.php';

// Frases de impacto para registro
$register_phrases = [
    "O saber da Terapia Ocupacional em áudio: evolução e atualização na palma da sua mão.",
    "Dê voz ao seu aprendizado: Terapia Ocupacional em áudio para o seu futuro.",
    "Amplie seus horizontes na Terapia Ocupacional: conteúdo em áudio feito sob medida para você.",
    "Conhecimento em Terapia Ocupacional que se encaixa na sua rotina, em formato de áudio."
];
$selected_register_phrase = $register_phrases[array_rand($register_phrases)];

// Obter mensagens de erro e dados de formulário da sessão, se existirem
$register_errors = $_SESSION['register_errors'] ?? [];
$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['register_errors'], $_SESSION['form_data']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Conta - AudioTO</title>
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
                        'register-hero': "url('https://placehold.co/1200x800/8B5CF6/FFFFFF?text=Junte-se+a+Nós&font=raleway&font-size=80')",
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
        .form-input-icon-wrapper { position: absolute; left: .75rem; top: 50%; transform: translateY(-50%); pointer-events: none; display: flex; align-items: center; }
        .input-with-icon { padding-left: 2.5rem; }
        .input-without-icon { padding-left: 1rem; }
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
        .form-checkbox { height: 1rem; width: 1rem; color: #007AFF; border-radius: .25rem; border: 1px solid #d1d5db; }
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
        <div class="hidden md:flex md:w-1/2 lg:w-3/5 bg-register-hero bg-cover bg-center items-center justify-center p-12 text-white relative animate-fade-in">
            <div class="absolute inset-0 bg-secondary opacity-80"></div>
            <div class="relative z-10 text-center animate-fade-in-up" style="animation-delay: 0.2s;">
                <h1 class="text-6xl lg:text-7xl font-bold mb-6 font-raleway">AudioTO</h1>
                <p class="text-xl lg:text-2xl font-light max-w-lg mx-auto">
                    <?php echo htmlspecialchars($selected_register_phrase); ?>
                </p>
            </div>
        </div>

        <div class="w-full md:w-1/2 lg:w-2/5 flex items-center justify-center p-4 sm:p-6 md:p-8 bg-light-bg">
            <div class="w-full max-w-md">
                <div class="text-center mb-6 md:hidden animate-fade-in">
                    <h1 class="text-5xl font-bold text-primary font-raleway">AudioTO</h1>
                    <p class="text-base text-medium-text mt-2">
                        <?php echo htmlspecialchars($selected_register_phrase); ?>
                    </p>
                </div>

                <div class="bg-white p-6 md:p-8 rounded-xl shadow-2xl form-element-animated" style="animation-name: fadeIn; animation-duration: 0.5s; animation-delay: 0.1s; opacity:1;">
                    <div class="form-element-animated" style="animation-name: fadeInUp; animation-duration: 0.5s; animation-delay: 0.2s; opacity:1;">
                        <h2 class="text-2xl sm:text-3xl font-semibold text-dark-text text-center mb-1 sm:mb-2">Crie a sua Conta</h2>
                        <p class="text-center text-medium-text mb-4 sm:mb-6">É rápido e fácil. Comece já!</p>
                    </div>
                    
                    <div id="messageContainer" class="mb-4">
                        <?php if (!empty($register_errors)): ?>
                            <div class="message-box error-message">
                                <ul class="list-disc list-inside pl-2">
                                    <?php foreach ($register_errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>

                    <form id="registerForm" action="register_handler.php" method="POST" class="space-y-4">
                        <div class="form-element-animated" style="animation-name: fadeInUp; animation-duration: 0.5s; animation-delay: 0.3s; opacity:1;">
                            <label for="fullName" class="form-label">Nome Completo</label>
                            <div class="form-input-container">
                                <div class="form-input-icon-wrapper">
                                    <svg class="w-5 h-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>
                                </div>
                                <input type="text" id="fullName" name="fullName" class="form-input input-with-icon" placeholder="O seu nome completo" value="<?php echo htmlspecialchars($form_data['fullName'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="form-element-animated" style="animation-name: fadeInUp; animation-duration: 0.5s; animation-delay: 0.4s; opacity:1;">
                            <label for="email" class="form-label">Endereço de Email</label>
                            <div class="form-input-container">
                                <div class="form-input-icon-wrapper">
                                    <svg class="w-5 h-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" /></svg>
                                </div>
                                <input type="email" id="email" name="email" class="form-input input-with-icon" placeholder="seu.email@exemplo.com" value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>" required>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-4">
                            <div class="form-element-animated" style="animation-name: fadeInUp; animation-duration: 0.5s; animation-delay: 0.5s; opacity:1;">
                                <label for="password" class="form-label">Senha</label>
                                <div class="form-input-container">
                                    <div class="form-input-icon-wrapper">
                                        <svg class="w-5 h-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>
                                    </div>
                                    <input type="password" id="password" name="password" class="form-input input-with-icon" placeholder="Mínimo 8 caracteres" required>
                                </div>
                            </div>
                            <div class="form-element-animated" style="animation-name: fadeInUp; animation-duration: 0.5s; animation-delay: 0.55s; opacity:1;">
                                <label for="confirmPassword" class="form-label">Confirmar Senha</label>
                                <div class="form-input-container">
                                    <div class="form-input-icon-wrapper">
                                         <svg class="w-5 h-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>
                                    </div>
                                    <input type="password" id="confirmPassword" name="confirmPassword" class="form-input input-with-icon" placeholder="Repita a palavra-passe" required>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-4">
                            <div class="form-element-animated" style="animation-name: fadeInUp; animation-duration: 0.5s; animation-delay: 0.6s; opacity:1;">
                                <label for="profession" class="form-label">Profissão <span class="text-xs text-light-text">(Opcional)</span></label>
                                <input type="text" id="profession" name="profession" class="form-input input-without-icon" placeholder="Ex: Terapeuta Ocupacional" value="<?php echo htmlspecialchars($form_data['profession'] ?? ''); ?>">
                            </div>
                            <div class="form-element-animated" style="animation-name: fadeInUp; animation-duration: 0.5s; animation-delay: 0.65s; opacity:1;">
                                <label for="crefito" class="form-label">CREFITO / Registo <span class="text-xs text-light-text">(Opcional)</span></label>
                                <input type="text" id="crefito" name="crefito" class="form-input input-without-icon" placeholder="O seu número de registo" value="<?php echo htmlspecialchars($form_data['crefito'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-element-animated" style="animation-name: fadeInUp; animation-duration: 0.5s; animation-delay: 0.7s; opacity:1;">
                            <label class="flex items-center space-x-2 cursor-pointer pt-1">
                                <input type="checkbox" id="terms" name="terms" class="form-checkbox" required>
                                <span class="text-sm text-medium-text">Eu li e aceito os <a href="#" class="text-primary hover:underline">Termos e Condições</a> e a <a href="#" class="text-primary hover:underline">Política de Privacidade</a>.</span>
                            </label>
                        </div>
                        
                        <div class="pt-2 form-element-animated" style="animation-name: fadeInUp; animation-duration: 0.5s; animation-delay: 0.75s; opacity:1;">
                            <button type="submit" class="btn-primary-full">
                                Criar Conta
                            </button>
                        </div>
                    </form>

                    <div class="mt-6 text-center form-element-animated" style="animation-name: fadeInUp; animation-duration: 0.5s; animation-delay: 0.8s; opacity:1;">
                        <p class="text-sm text-medium-text">
                            Já tem uma conta? 
                            <a href="login.php" class="font-semibold text-primary hover:text-primary-dark transition-colors">Faça login aqui</a>
                        </p>
                    </div>
                </div>

                <p class="text-center text-xs text-light-text mt-8 animate-fade-in" style="animation-delay: 0.9s;">
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
