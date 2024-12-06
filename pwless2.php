
<?php
/*
Plugin Name: ZeroPass Login
Description: Login sem complicações. Com o ZeroPass Login, seus usuários acessam sua plataforma com links seguros enviados por e-mail. Sem senhas, sem estresse – apenas segurança e simplicidade.
Version: 3.8.4
Author: Giovani Tureck (<a href="https://projetoalfa.org" target="_blank">projetoalfa.org</a>)
*/

// Inclui a classe do atualizador
require_once plugin_dir_path(__FILE__) . 'class-passwordless-login-updater.php';

// Inicia o atualizador
new PasswordlessLoginUpdater(__FILE__);

// Função para exibir o formulário de login sem senha
function passwordless_login_form() {
    if (is_user_logged_in()) {
        $redirect_url = get_option('pwless_redirect_url', home_url());
        ob_start();
        ?>
        <div class="already-logged-in">
            <p>Você já está logado!</p>
            <p>Você será redirecionado em <span id="countdown">5</span> segundos...</p>
            <a href="<?php echo esc_url($redirect_url); ?>" class="redirect-button">Ir para a página agora</a>
        </div>

        <style>
            .already-logged-in {
                text-align: center;
                padding: 20px;
                background: #f8f8f8;
                border: 1px solid #ddd;
                border-radius: 5px;
                margin: 20px 0;
            }
            .redirect-button {
                display: inline-block;
                padding: 10px 20px;
                background-color: #2271b1;
                color: white;
                text-decoration: none;
                border-radius: 3px;
                margin-top: 10px;
            }
            .redirect-button:hover {
                background-color: #135e96;
                color: white;
            }
        </style>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var count = 5;
                var countdown = document.getElementById('countdown');
                var timer = setInterval(function() {
                    count--;
                    countdown.textContent = count;
                    if (count <= 0) {
                        clearInterval(timer);
                        window.location.href = '<?php echo esc_js($redirect_url); ?>';
                    }
                }, 1000);
            });
        </script>
        <?php
        return ob_get_clean();
    }

    $message = '';
    $email = '';

    if (isset($_POST['passwordless_login']) && check_admin_referer('passwordless_login_nonce', 'passwordless_login_nonce_field')) {
        $email = sanitize_email($_POST['user_email']);
        if (!is_email($email)) {
            $message = "<p class='error'>Email inválido.</p>";
            pwless_log_attempt($email, 'erro_email_invalido');
        } else {
            $user = get_user_by('email', $email);
            if ($user) {
                $token = wp_generate_password(20, false);
                $token_hash = wp_hash_password($token);
                $token_created = time();
                update_user_meta($user->ID, 'passwordless_login_token', $token_hash);
                update_user_meta($user->ID, 'passwordless_login_token_created', $token_created);

                $login_url = add_query_arg(array(
                    'passwordless_login' => urlencode($token),
                    'user' => $user->ID,
                    'nonce' => wp_create_nonce('passwordless_login_' . $user->ID . '_' . $token_created)
                ), site_url());

                $expiry_time = get_option('pwless_link_expiry', 60);
                $email_template = get_option('pwless_email_template');
                $email_content = str_replace(
                    array('{login_url}', '{expiry_time}'),
                    array($login_url, $expiry_time),
                    $email_template
                );

                $headers = array('Content-Type: text/html; charset=UTF-8');
                $subject = get_option('pwless_email_subject', 'Seu link de login');
                
                if (wp_mail($email, $subject, $email_content, $headers)) {
                    $message = "<p class='success'>" . str_replace('{expiry_time}', $expiry_time, get_option('pwless_success_message')) . "</p>";
                    pwless_log_attempt($email, 'email_enviado');
                } else {
                    $message = "<p class='error'>Erro ao enviar email.</p>";
                    pwless_log_attempt($email, 'erro_envio_email');
                }
            } else {
                $message = "<p class='error'>" . get_option('pwless_error_message') . "</p>";
                pwless_log_attempt($email, 'usuario_nao_encontrado');
            }
        }
    }

    ob_start();
    ?>
    <form id="passwordless-login-form" method="post" class="reset-password-form" onsubmit="showLoader()">
        <?php wp_nonce_field('passwordless_login_nonce', 'passwordless_login_nonce_field'); ?>
        <label for="user_email"><?php echo esc_html(get_option('pwless_form_email_label')); ?></label>
        <input type="email" name="user_email" placeholder="" value="<?php echo esc_attr($email); ?>" required>
        <input type="submit" name="passwordless_login" value="<?php echo esc_attr(get_option('pwless_form_button_text')); ?>">
        <p>O link enviado tem validade de <?php echo esc_html(get_option('pwless_link_expiry', 60)); ?> minuto(s).</p>
        <div id="loader" style="display:none;">Enviando... <img src="<?php echo plugins_url('assets/loading.gif', __FILE__); ?>" alt="Carregando"></div>
        <?php echo wp_kses_post($message); ?>
    </form>

    <style>
        .reset-password-form {
            max-width: 400px;
            margin: auto;
            padding: 1em;
            border: 1px solid #ccc;
            border-radius: 1em;
        }
        .reset-password-form label {
            display: block;
            margin-bottom: 8px;
        }
        .reset-password-form input[type="email"], .reset-password-form input[type="submit"] {
            width: 100%;
            padding: 8px;
            margin-bottom: 12px;
        }
        .reset-password-form .error {
            color: red;
        }
        .reset-password-form .success {
            color: green;
        }
        #loader img {
            text-align: center;
            margin-top: 10px;
            height: 25px; /* Ajuste a altura conforme necessário */
        }
    </style>

    <script>
        function showLoader() {
            document.getElementById('loader').style.display = 'block';
        }
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('passwordless_login', 'passwordless_login_form');

// Função para exibir o formulário de reset de senha
function pwless_reset_password_form() {
    if (is_user_logged_in()) {
        $redirect_url = get_option('pwless_redirect_url', home_url());
        ob_start();
        ?>
        <div class="already-logged-in">
            <p>Você já está logado!</p>
            <p>Você será redirecionado em <span id="countdown">5</span> segundos...</p>
            <a href="<?php echo esc_url($redirect_url); ?>" class="redirect-button">Ir para a página agora</a>
        </div>

        <style>
            .already-logged-in {
                text-align: center;
                padding: 20px;
                background: #f8f8f8;
                border: 1px solid #ddd;
                border-radius: 5px;
                margin: 20px 0;
            }
            .redirect-button {
                display: inline-block;
                padding: 10px 20px;
                background-color: #2271b1;
                color: white;
                text-decoration: none;
                border-radius: 3px;
                margin-top: 10px;
            }
            .redirect-button:hover {
                background-color: #135e96;
                color: white;
            }
        </style>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var count = 5;
                var countdown = document.getElementById('countdown');
                var timer = setInterval(function() {
                    count--;
                    countdown.textContent = count;
                    if (count <= 0) {
                        clearInterval(timer);
                        window.location.href = '<?php echo esc_js($redirect_url); ?>';
                    }
                }, 1000);
            });
        </script>
        <?php
        return ob_get_clean();
    }

    $message = '';
    $email = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_email']) && check_admin_referer('pwless_reset_nonce', 'pwless_reset_nonce_field')) {
        $email = sanitize_email($_POST['user_email']);
        
        if (!is_email($email)) {
            $message = "<p class='error'>Por favor, insira um endereço de e-mail válido.</p>";
            pwless_log_attempt($email, 'reset_erro_email_invalido');
        } else {
            $user = get_user_by('email', $email);
            if (!$user) {
                $message = "<p class='error'>" . esc_html(get_option('pwless_reset_error_message')) . "</p>";
                pwless_log_attempt($email, 'reset_usuario_nao_encontrado');
            } else {
                $new_password = wp_generate_password(12, false);
                wp_set_password($new_password, $user->ID);

                $login_url = home_url('/area-do-aluno/');
                $email_template = get_option('pwless_reset_email_template');
                $email_content = str_replace(
                    array('{new_password}', '{login_url}'),
                    array($new_password, $login_url),
                    $email_template
                );

                $headers = array('Content-Type: text/html; charset=UTF-8');
                $subject = get_option('pwless_reset_email_subject');
                
                if (wp_mail($email, $subject, $email_content, $headers)) {
                    $message = "<p class='success'>" . esc_html(get_option('pwless_reset_success_message')) . "</p>";
                    pwless_log_attempt($email, 'reset_senha_enviada');
                } else {
                    $message = "<p class='error'>Erro ao enviar email.</p>";
                    pwless_log_attempt($email, 'reset_erro_envio_email');
                }
            }
        }
    }

    ob_start();
    ?>
    <form method="post" class="reset-password-form" onsubmit="showLoader()">
        <?php wp_nonce_field('pwless_reset_nonce', 'pwless_reset_nonce_field'); ?>
        <h3><?php echo esc_html(get_option('pwless_reset_form_title')); ?></h3>
        <label for="user_email">Endereço de e-mail:</label>
        <input type="email" id="user_email" name="user_email" value="<?php echo esc_attr($email); ?>" required>
        <input type="submit" value="<?php echo esc_attr(get_option('pwless_reset_button_text')); ?>">
        <div><?php echo esc_html(get_option('pwless_reset_description')); ?></div>
        <div id="loader" style="display:none;">Enviando... <img src="<?php echo plugins_url('assets/loading.gif', __FILE__); ?>" alt="Carregando"></div>
        <?php echo wp_kses_post($message); ?>
    </form>

    <style>
        .reset-password-form {
            max-width: 400px;
            margin: auto;
            padding: 1em;
            border: 1px solid #ccc;
            border-radius: 1em;
        }
        .reset-password-form label {
            display: block;
            margin-bottom: 8px;
        }
        .reset-password-form input[type="email"], .reset-password-form input[type="submit"] {
            width: 100%;
            padding: 8px;
            margin-bottom: 12px;
        }
        .reset-password-form .error {
            color: red;
        }
        .reset-password-form .success {
            color: green;
        }
        #loader img {
            text-align: center;
            margin-top: 10px;
            height: 25px;
        }
    </style>

    <script>
        function showLoader() {
            document.getElementById('loader').style.display = 'block';
        }
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('passwordless_reset', 'pwless_reset_password_form');

// Função para processar o login via link único
function process_passwordless_login() {
    if (isset($_GET['passwordless_login']) && isset($_GET['user']) && isset($_GET['nonce'])) {
        $user_id = intval($_GET['user']);
        $token = sanitize_text_field($_GET['passwordless_login']);
        $nonce = sanitize_text_field($_GET['nonce']);
        $saved_token = get_user_meta($user_id, 'passwordless_login_token', true);
        $token_created = get_user_meta($user_id, 'passwordless_login_token_created', true);
        $expiry_minutes = get_option('pwless_link_expiry', 60);

        // Verifica se o nonce é válido e o token não expirou
        if (wp_verify_nonce($nonce, 'passwordless_login_' . $user_id . '_' . $token_created) && 
            $token && 
            wp_check_password($token, $saved_token) && 
            (time() - $token_created) < ($expiry_minutes * 60)) {
            
            wp_set_auth_cookie($user_id);
            delete_user_meta($user_id, 'passwordless_login_token');
            delete_user_meta($user_id, 'passwordless_login_token_created');
            
            $user = get_user_by('ID', $user_id);
            pwless_log_attempt($user->user_email, 'login_sucesso');
            
            // Usa a URL de redirecionamento configurada ou a página inicial como fallback
            $redirect_url = get_option('pwless_redirect_url');
            if (empty($redirect_url)) {
                $redirect_url = home_url();
            }
            wp_redirect($redirect_url);
            exit;
        } else {
            $user = get_user_by('ID', $user_id);
            pwless_log_attempt($user ? $user->user_email : 'unknown', 'link_invalido_ou_expirado');
            echo '<p class="error">Link inválido ou expirado.</p>';
        }
    }
}
add_action('init', 'process_passwordless_login');

// Adiciona menu na área administrativa
function pwless_admin_menu() {
    add_submenu_page(
        'tools.php',
        'Configurações de Login Sem Senha',
        'Login Sem Senha',
        'manage_options',
        'pwless-settings',
        'pwless_settings_page'
    );
}
add_action('admin_menu', 'pwless_admin_menu');

// Adiciona link de configurações na lista de plugins
function pwless_add_settings_link($links) {
    $settings_link = '<a href="' . admin_url('tools.php?page=pwless-settings') . '">Configurações</a>';
    array_unshift($links, $settings_link);
    return $links;
}
$plugin = plugin_basename(__FILE__);
add_filter("plugin_action_links_$plugin", 'pwless_add_settings_link');

// Registra as configurações
function pwless_register_settings() {
    register_setting('pwless_options', 'pwless_email_subject');
    register_setting('pwless_options', 'pwless_email_template');
    register_setting('pwless_options', 'pwless_from_email');
    register_setting('pwless_options', 'pwless_from_name');
    register_setting('pwless_options', 'pwless_link_expiry', 'intval');
    register_setting('pwless_options', 'pwless_form_email_label');
    register_setting('pwless_options', 'pwless_form_button_text');
    register_setting('pwless_options', 'pwless_success_message');
    register_setting('pwless_options', 'pwless_error_message');
    register_setting('pwless_options', 'pwless_enable_logging', 'boolval');
    register_setting('pwless_options', 'pwless_redirect_url');

    // Novas configurações para reset de senha
    register_setting('pwless_options', 'pwless_reset_email_subject');
    register_setting('pwless_options', 'pwless_reset_email_template');
    register_setting('pwless_options', 'pwless_reset_success_message');
    register_setting('pwless_options', 'pwless_reset_error_message');
    register_setting('pwless_options', 'pwless_reset_button_text');
    register_setting('pwless_options', 'pwless_reset_form_title');
    register_setting('pwless_options', 'pwless_reset_description');
}
add_action('admin_init', 'pwless_register_settings');

// Configurações padrão para reset de senha
function pwless_set_default_reset_options() {
    if (false === get_option('pwless_reset_email_subject')) {
        update_option('pwless_reset_email_subject', 'Sua nova senha');
        update_option('pwless_reset_email_template', 'Sua nova senha é: {new_password}<br><br>
            <a href="{login_url}" target="_blank">Clique aqui para fazer o login</a><br><br>
            Essa é uma senha temporária, após fazer o login por favor, altere sua senha assim que possível.');
        update_option('pwless_reset_success_message', 'Uma nova senha foi enviada para seu endereço de e-mail.');
        update_option('pwless_reset_error_message', 'Endereço de e-mail não encontrado.');
        update_option('pwless_reset_button_text', 'Enviar nova senha');
        update_option('pwless_reset_form_title', 'Resetar Senha');
        update_option('pwless_reset_description', 'Sua senha será alterada e enviada para seu email.');
    }
}
register_activation_hook(__FILE__, 'pwless_set_default_reset_options');

// Página de configurações
function pwless_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Salva as configurações padrão se não existirem
    if (false === get_option('pwless_email_subject')) {
        update_option('pwless_email_subject', 'Seu link de login');
        update_option('pwless_email_template', '<a href="{login_url}">Clique aqui para fazer login</a><br><br>O link tem validade de {expiry_time} minuto(s).');
        update_option('pwless_link_expiry', 60); // 60 minutos (1 hora) como padrão
        update_option('pwless_form_email_label', 'Digite seu email:');
        update_option('pwless_form_button_text', 'Enviar link');
        update_option('pwless_success_message', 'Link enviado para o email. O link tem validade de {expiry_time} minuto(s).');
        update_option('pwless_error_message', 'Usuário não encontrado.');
        update_option('pwless_enable_logging', true);
        update_option('pwless_redirect_url', home_url());
    }

    if (isset($_GET['settings-updated'])) {
        add_settings_error('pwless_messages', 'pwless_message', 'Configurações salvas', 'updated');
    }

    settings_errors('pwless_messages');
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('pwless_options');
            ?>
            <div class="nav-tab-wrapper">
                <a href="#" class="nav-tab nav-tab-active" data-tab="email">Email</a>
                <a href="#" class="nav-tab" data-tab="form">Formulário</a>
                <a href="#" class="nav-tab" data-tab="security">Segurança</a>
                <a href="#" class="nav-tab" data-tab="reset">Reset de Senha</a>
                <a href="#" class="nav-tab" data-tab="shortcode">Shortcodes</a>
                <a href="#" class="nav-tab" data-tab="logs">Logs</a>
                <a href="#" class="nav-tab" data-tab="sobre">Sobre</a>
            </div>

            <div class="tab-content" id="email">
                <h2>Configurações de Email</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Assunto do Email</th>
                        <td>
                            <input type="text" name="pwless_email_subject" value="<?php echo esc_attr(get_option('pwless_email_subject')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Modelo do Email</th>
                        <td>
                            <?php
                            wp_editor(
                                get_option('pwless_email_template'),
                                'pwless_email_template',
                                array(
                                    'textarea_name' => 'pwless_email_template',
                                    'textarea_rows' => 10,
                                    'media_buttons' => false
                                )
                            );
                            ?>
                            <p class="description">
                                Variáveis disponíveis: {login_url}, {expiry_time}
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="tab-content" id="form" style="display: none;">
                <h2>Personalização do Formulário</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Label do Campo Email</th>
                        <td>
                            <input type="text" name="pwless_form_email_label" value="<?php echo esc_attr(get_option('pwless_form_email_label')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Texto do Botão</th>
                        <td>
                            <input type="text" name="pwless_form_button_text" value="<?php echo esc_attr(get_option('pwless_form_button_text')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Mensagem de Sucesso</th>
                        <td>
                            <input type="text" name="pwless_success_message" value="<?php echo esc_attr(get_option('pwless_success_message')); ?>" class="regular-text">
                            <p class="description">Use {expiry_time} para mostrar o tempo de expiração</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Mensagem de Erro</th>
                        <td>
                            <input type="text" name="pwless_error_message" value="<?php echo esc_attr(get_option('pwless_error_message')); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>
            </div>

            <div class="tab-content" id="security" style="display: none;">
                <h2>Configurações de Segurança</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Tempo de Expiração do Link (minutos)</th>
                        <td>
                            <input type="number" name="pwless_link_expiry" value="<?php echo esc_attr(get_option('pwless_link_expiry')); ?>" min="1" max="1440" class="small-text">
                            <p class="description">Tempo em minutos (entre 1 e 1440 minutos, ou seja, até 24 horas)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">URL de Redirecionamento após Login</th>
                        <td>
                            <input type="url" name="pwless_redirect_url" value="<?php echo esc_url(get_option('pwless_redirect_url', home_url())); ?>" class="regular-text">
                            <p class="description">URL para onde o usuário será redirecionado após fazer login. Deixe em branco para usar a página inicial.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Habilitar Logs</th>
                        <td>
                            <label>
                                <input type="checkbox" name="pwless_enable_logging" value="1" <?php checked(1, get_option('pwless_enable_logging'), true); ?>>
                                Registrar tentativas de login
                            </label>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="tab-content" id="reset" style="display: none;">
                <h2>Configurações de Reset de Senha</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Assunto do Email de Reset</th>
                        <td>
                            <input type="text" name="pwless_reset_email_subject" value="<?php echo esc_attr(get_option('pwless_reset_email_subject')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Template do Email de Reset</th>
                        <td>
                            <?php
                            wp_editor(
                                get_option('pwless_reset_email_template'),
                                'pwless_reset_email_template',
                                array(
                                    'textarea_name' => 'pwless_reset_email_template',
                                    'textarea_rows' => 10,
                                    'media_buttons' => false
                                )
                            );
                            ?>
                            <p class="description">
                                Variáveis disponíveis: {new_password}, {login_url}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Título do Formulário</th>
                        <td>
                            <input type="text" name="pwless_reset_form_title" value="<?php echo esc_attr(get_option('pwless_reset_form_title')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Descrição do Formulário</th>
                        <td>
                            <input type="text" name="pwless_reset_description" value="<?php echo esc_attr(get_option('pwless_reset_description')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Texto do Botão</th>
                        <td>
                            <input type="text" name="pwless_reset_button_text" value="<?php echo esc_attr(get_option('pwless_reset_button_text')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Mensagem de Sucesso</th>
                        <td>
                            <input type="text" name="pwless_reset_success_message" value="<?php echo esc_attr(get_option('pwless_reset_success_message')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Mensagem de Erro</th>
                        <td>
                            <input type="text" name="pwless_reset_error_message" value="<?php echo esc_attr(get_option('pwless_reset_error_message')); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>
            </div>

            <div class="tab-content" id="shortcode" style="display: none;">
                <h2>Shortcodes Disponíveis</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Login Sem Senha</th>
                        <td>
                            <code>[passwordless_login]</code>
                            <p class="description">Use este shortcode para exibir o formulário de login sem senha.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Reset de Senha</th>
                        <td>
                            <code>[passwordless_reset]</code>
                            <p class="description">Use este shortcode para exibir o formulário de reset de senha.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="tab-content" id="logs" style="display: none;">
                <h2>Logs de Login</h2>
                <?php
                global $wpdb;
                $table_name = $wpdb->prefix . 'pwless_logs';
                if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
                    $logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 10");
                    if ($logs) {
                        echo '<table class="wp-list-table widefat fixed striped">';
                        echo '<thead><tr><th>Data</th><th>Email</th><th>Status</th><th>IP</th></tr></thead>';
                        echo '<tbody>';
                        foreach ($logs as $log) {
                            echo '<tr>';
                            echo '<td>' . esc_html($log->created_at) . '</td>';
                            echo '<td>' . esc_html($log->email) . '</td>';
                            echo '<td>' . esc_html($log->status) . '</td>';
                            echo '<td>' . esc_html($log->ip_address) . '</td>';
                            echo '</tr>';
                        }
                        echo '</tbody></table>';
                    } else {
                        echo '<p>Nenhum log encontrado.</p>';
                    }
                } else {
                    echo '<p>Tabela de logs não encontrada. Ative o logging nas configurações de segurança.</p>';
                }
                ?>
            </div>

            <div class="tab-content" id="sobre" style="display: none;">
                <h2>Sobre o Plugin</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Versão</th>
                        <td>
                            <strong>3.8.4</strong>
                        </td>
                    </tr>
                </table>
            </div>

            <?php submit_button('Salvar Configurações'); ?>
        </form>
    </div>

    <style>
        .tab-content {
            margin-top: 20px;
        }
        .nav-tab-wrapper {
            margin-bottom: 20px;
        }
    </style>

    <script>
        jQuery(document).ready(function($) {
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                var tab = $(this).data('tab');
                
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                $('.tab-content').hide();
                $('#' + tab).show();
            });
        });
    </script>
    <?php
}

// Criar tabela de logs na ativação do plugin
function pwless_create_log_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'pwless_logs';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            email varchar(100) NOT NULL,
            status varchar(50) NOT NULL,
            ip_address varchar(45) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
register_activation_hook(__FILE__, 'pwless_create_log_table');

// Função para registrar logs
function pwless_log_attempt($email, $status) {
    if (!get_option('pwless_enable_logging')) {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'pwless_logs';
    
    $wpdb->insert(
        $table_name,
        array(
            'email' => $email,
            'status' => $status,
            'ip_address' => $_SERVER['REMOTE_ADDR']
        ),
        array('%s', '%s', '%s')
    );
}

?>