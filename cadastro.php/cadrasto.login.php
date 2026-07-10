<?php






session_start();


require_once __DIR__ . '/../env.php';


define('SUPABASE_URL', rtrim(getenv('SUPABASE_URL'), '/'));
define('SUPABASE_SERVICE_KEY', getenv('SUPABASE_SERVICE_KEY'));


function supabase_request(string $method, string $path, ?array $body = null): array{
    $ch = curl_init(SUPABASE_URL . '/rest/v1/' . $path);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey:' .SUPABASE_SERVICE_KEY,
        'Authorization: Bearer' . SUPABASE_SERVICE_KEY,
        'Content-Type: application/json',
        'Prefer: return=representation',
    ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if($body !== null){
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $resposta = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['status' => $status, 'dados' => json_decode($resposta, true)];


}


if(empty ($_SESSION['csrf_token'])){
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


$mensagem = "";
$tipo_mensagem = "";


if($_SERVER['REQUEST_METHOD'] ==='POST'){
    $csrf_valido = hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '');


    if(!$csrf_valido){
        $mensagem = "Requisição inválida (token CSRF ausente ou expirado). Recarregue a página.";
        $tipo_mensagem = "erro";
    }else{
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';


        if($nome === '' || $email ==='' || $senha === ''){
            $mensagem = "Preencha nome, e-mail e senha.";
            $tipo_mensagem = "erro";
        }elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)){
            $mensagem = "Informe um e-mail válido.";
            $tipo_mensagem = "erro";
        }elseif(strlen($senha) <8){
            $mensagem = "A senha deve ter pelo menos 8 caracteres.";
            $tipo_mensagem = "erro";
        }elseif(!preg_match('/[A-Z]/', $senha)){
            $mensagem = "A senha deve conter pelo menos uma letra maiúscula.";
            $tipo_mensagem = "erro";
        }elseif(!preg_match('/[0-9]/', $senha)){
            $mensagem = "A senha deve conter pelo menos um número.";
            $tipo_mensagem = "erro";
        }elseif(!preg_match('/[^A-Za-z0-9]/', $senha)){
            $mensagem = "A senha deve conter pelo menos um caractere especial (ex: ! @ # \$ % ).";
            $tipo_mensagem = "erro";
        }else{
            $senha_hash= password_hash($senha, PASSWORD_DEFAULT);
            $resultado = supabase_request('POST', 'usuarios', [
                'nome' => $nome,
                'email' => $email,
                'senha_hash' => $senha_hash,
            ]);
            if($resultado['status'] ===201){
                $mensagem = "Cadastro realizado com sucesso! Você já pode fazer login.";
                $tipo_mensagem = "sucesso";
            }elseif($resultado['status'] ===409){
                $mensagem = "E-mail já cadastrado. Tente outro.";
                $tipo_mensagem = "erro";
            }else{
                $mensagem = "Erro ao cadastrar usuário. Tente novamente mais tarde. (status " . $resultado['status'] . ")";
                $tipo_mensagem = "erro";
            }
        }


    }


    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro — Autenticação Segura</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, sans-serif; background: #f0f4f8; padding: 2rem; }
        .container { max-width: 420px; margin: 0 auto; }
        h1 { font-size: 1.4rem; margin-bottom: 1.25rem; }
        .card { background: #fff; border-radius: 10px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        form { display: flex; flex-direction: column; gap: 0.75rem; }
        input { padding: 0.55rem 0.75rem; border: 1px solid #cbd5e0; border-radius: 8px; font-size: 0.95rem; }
        button { background: #3182ce; color: #fff; border: none; padding: 0.65rem; border-radius: 8px; font-size: 0.95rem; cursor: pointer; }
        button:hover { background: #2b6cb0; }
        .alerta { padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-weight: 500; font-size: 0.9rem; }
        .sucesso { background: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
        .erro { background: #fed7d7; color: #742a2a; border: 1px solid #feb2b2; }
        a { color: #3182ce; 
        p.rodape { margin-top: 1rem; font-size: 0.9rem; text-align: center; }
        .dica-senha { font-size: 0.8rem; color: #718096; margin-top: -0.35rem; }
        
    </style>
</head>
<body>
    <div class="container">
        <h1>Criar conta</h1>
        <div class="card">
            <?php if ($mensagem): ?>
                <!-- htmlspecialchars() aqui é o único ponto de escape da mensagem (defesa XSS) -->
                <div class="alerta <?= $tipo_mensagem ?>"><?= htmlspecialchars($mensagem) ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="text" name="nome" placeholder="Nome completo" required
                       value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>">
                <input type="email" name="email" placeholder="E-mail" required
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                <!-- type="password" evita que a senha apareça em texto na tela enquanto o usuário digita -->
                <!-- pattern faz o navegador validar antes de enviar (melhora a UX), mas quem garante
                     a regra de verdade é a validação em PHP acima — pattern pode ser burlado -->
                <input type="password" name="senha" placeholder="Senha" required
                       minlength="8" pattern="(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,}"
                       title="Mínimo 8 caracteres, com maiúscula, número e caractere especial">
                <p class="dica-senha">Mínimo 8 caracteres, com 1 maiúscula, 1 número e 1 caractere especial.</p>
                <button type="submit">Cadastrar</button>
            </form>
            <p class="rodape">Já tem conta? <a href="login.php">Entrar</a></p>
        </div>
    </div>
</body>
</html>

