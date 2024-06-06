<?php
/*
Plugin Name: Passwordless Login
Description: Permite login sem senha usando um link enviado por email.
Version: 1.0
Author: Seu Nome
*/

// Função para exibir o formulário de login sem senha
function passwordless_login_form() {
    if (!is_user_logged_in()) {
        $message = '';
        $email = '';

        if (isset($_POST['passwordless_login']) && check_admin_referer('passwordless_login_nonce', 'passwordless_login_nonce_field')) {
            $email = sanitize_email($_POST['user_email']);
            if (!is_email($email)) {
                $message = "<p class='error'>Email inválido.</p>";
            } else {
                $user = get_user_by('email', $email);
                if ($user) {
                    $token = wp_generate_password(20, false);
                    update_user_meta($user->ID, 'passwordless_login_token', $token);
                    update_user_meta($user->ID, 'passwordless_login_token_created', time());
                    $login_url = site_url() . '?passwordless_login=' . $token . '&user=' . $user->ID;
                    $email_content = '<a href="' . esc_url($login_url) . '">Clique aqui para fazer login</a><br><br>';
                    $email_content .= 'O link tem validade de uma hora.';

                    // Cabeçalhos para enviar o email em HTML
                    $headers = array('Content-Type: text/html; charset=UTF-8');
                    
                    wp_mail($email, 'Seu link de login', $email_content, $headers);
                    $message = "<p class='success'>Link enviado para o email. O link tem validade de uma hora.</p>";
                } else {
                    $message = "<p class='error'>Usuário não encontrado.</p>";
                }
            }
        }

        ob_start();
        ?>
        <form id="passwordless-login-form" method="post" class="reset-password-form" onsubmit="showLoader()">
            <?php wp_nonce_field('passwordless_login_nonce', 'passwordless_login_nonce_field'); ?>
            <label for="user_email">Digite seu email:</label>
            <input type="email" name="user_email" placeholder="Digite seu email" value="<?php echo esc_attr($email); ?>" required>
            <input type="submit" name="passwordless_login" value="Enviar link">
            <p>O link enviado tem validade de uma hora.</p>
            <div id="loader" style="display:none;">Enviando... <img src="https://example.com/loading.gif" alt="Carregando"></div>
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
}
add_shortcode('passwordless_login', 'passwordless_login_form');

// Função para processar o login via link único
function process_passwordless_login() {
    if (isset($_GET['passwordless_login']) && isset($_GET['user'])) {
        $user_id = intval($_GET['user']);
        $token = sanitize_text_field($_GET['passwordless_login']);
        $saved_token = get_user_meta($user_id, 'passwordless_login_token', true);
        $token_created = get_user_meta($user_id, 'passwordless_login_token_created', true);

        // Verifica se o token não expirou (1 hora de validade)
        if ($token && hash_equals($token, $saved_token) && (time() - $token_created) < 3600) {
            wp_set_auth_cookie($user_id);
            delete_user_meta($user_id, 'passwordless_login_token');
            delete_user_meta($user_id, 'passwordless_login_token_created');
            wp_redirect(home_url());
            exit;
        } else {
            echo '<p class="error">Link inválido ou expirado.</p>';
        }
    }
}
add_action('init', 'process_passwordless_login');
?>

