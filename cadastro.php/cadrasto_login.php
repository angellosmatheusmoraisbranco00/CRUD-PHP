<?php // Abre o bloco PHP — precisa ser processado pelo servidor antes do HTML chegar ao navegador


/**
 * ATIVIDADE PRÁTICA — Cadastro de Usuários (slide 4 e 8)
 * Baseado nos slides: "Autenticação e Permissões — Login Seguro com Hash de Senha em PHP"
 * Salva o usuário com a senha JÁ EM HASH (nunca em texto puro) via password_hash().
 *
 * Usa a chave service_role (não a anon) para gravar na tabela usuarios — a mesma tabela
 * é lida pelo login.php também com service_role, porque login precisa comparar a senha
 * digitada com o hash salvo, e a tabela usuarios deve ficar bloqueada para leitura pública
 * (RLS sem política de SELECT para "anon" — ver aviso em setup_usuarios.sql). Se a chave
 * anon pudesse ler essa tabela, qualquer pessoa listaria os hashes de senha pela API REST.
 *
 * TABELA NECESSÁRIA — rode setup_usuarios.sql no SQL Editor do Supabase antes de usar este arquivo.
 */


session_start(); // precisa vir antes de qualquer saída HTML — é onde o token CSRF é guardado


require_once __DIR__ . '/env.php';


define('SUPABASE_URL', rtrim(getenv('SUPABASE_URL'), '/'));
// service_role: só pode ser usada aqui no backend, nunca em código que roda no navegador
define('SUPABASE_SERVICE_KEY', getenv('SUPABASE_SERVICE_KEY'));


/**
 * Faz uma requisição HTTP à API REST do Supabase (PostgREST) e devolve status + dados.
 * Mesmo helper usado em CRUD.php/listar_produtos.php — ver comentários detalhados lá.
 */
function supabase_request(string $method, string $path, ?array $body = null): array {
    $ch = curl_init(SUPABASE_URL . '/rest/v1/' . $path);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_SERVICE_KEY,
        'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
        'Content-Type: application/json',
        'Prefer: return=representation',
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $resposta = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'dados' => json_decode($resposta, true)];
}


// token CSRF (mesma defesa já usada em listar_produtos.php) — protege o formulário de cadastro
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


$mensagem = "";
$tipo_mensagem = "";


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_valido = hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '');


    if (!$csrf_valido) {
        $mensagem = "Requisição inválida (token CSRF ausente ou expirado). Recarregue a página.";
        $tipo_mensagem = "erro";
    } else {
        $nome  = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';


        if ($nome === '' || $email === '' || $senha === '') {
            $mensagem = "Preencha nome, e-mail e senha.";
            $tipo_mensagem = "erro";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // filter_var com FILTER_VALIDATE_EMAIL confere um formato de e-mail minimamente válido
            $mensagem = "Informe um e-mail válido.";
            $tipo_mensagem = "erro";
        } elseif (strlen($senha) < 8) {
            $mensagem = "A senha precisa ter pelo menos 8 caracteres.";
            $tipo_mensagem = "erro";
        } elseif (!preg_match('/[A-Z]/', $senha)) {
            // preg_match retorna 0 (falsy) quando NÃO encontra o padrão — daqui o "!" na frente
            $mensagem = "A senha precisa ter pelo menos uma letra maiúscula.";
            $tipo_mensagem = "erro";
        } elseif (!preg_match('/[0-9]/', $senha)) {
            $mensagem = "A senha precisa ter pelo menos um número.";
            $tipo_mensagem = "erro";
        } elseif (!preg_match('/[^A-Za-z0-9]/', $senha)) {
            // [^A-Za-z0-9] = "qualquer caractere que NÃO seja letra nem número" → um caractere especial
            $mensagem = "A senha precisa ter pelo menos um caractere especial (ex: ! @ # \$ %).";
            $tipo_mensagem = "erro";
        } else {
            // ── DEFESA CENTRAL (slide 3 e 4): password_hash() transforma a senha em texto puro
            // num hash Bcrypt de mão única — o PHP nunca grava a senha original em lugar nenhum.
            // PASSWORD_DEFAULT usa o algoritmo mais recomendado no momento (hoje, Bcrypt) e cuida
            // de gerar um "salt" aleatório automaticamente, embutido no próprio hash resultante
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);


            // POST /rest/v1/usuarios com o corpo {nome, email, senha} = INSERT INTO usuarios(...)
            // repare que $senha_hash (não $senha) é o que vai para o banco
            $resultado = supabase_request('POST', 'usuarios', [
                'nome'  => $nome,
                'email' => $email,
                'senha' => $senha_hash
            ]);


            if ($resultado['status'] === 201) {
                $mensagem = "Cadastro realizado com sucesso! Você já pode fazer login.";
                $tipo_mensagem = "sucesso";
            } elseif ($resultado['status'] === 409) {
                // 409 Conflict = a constraint UNIQUE(email) da tabela rejeitou um e-mail repetido
                $mensagem = "Este e-mail já está cadastrado.";
                $tipo_mensagem = "erro";
            } else {
                $mensagem = "Erro ao cadastrar (status " . $resultado['status'] . ").";
                $tipo_mensagem = "erro";
            }
        }
    }


    // gera um novo token depois de processar o POST — reduz a janela de reutilização
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
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
        a { color: #3182ce; }
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





