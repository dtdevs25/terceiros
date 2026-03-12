<?php

// Arquivo de teste de conexão com o banco de dados
// Coloque este arquivo na raiz do seu projeto e acesse-o via navegador.

// --- ATENÇÃO: Configure suas credenciais do banco de dados aqui ---
$db_host = 'localhost';
$db_name = 'dani7103_terceiros'; // Nome completo do seu banco de dados
$db_user = 'dani7103_gestaoterceiros'; // Nome completo do seu usuário do banco de dados
$db_pass = 'nova@2025'; // Sua senha do banco de dados
// ------------------------------------------------------------------

echo "<h1>Teste de Conexão com o Banco de Dados</h1>";

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    echo "<p style=\"color: green; font-weight: bold;\">Conexão com o banco de dados estabelecida com sucesso!</p>";

    // Testar se a tabela 'usuarios' existe e tem dados
    echo "<h2>Verificando Tabela 'usuarios'</h2>";
    $stmt = $pdo->query("SELECT COUNT(*) as total_usuarios FROM usuarios");
    $result = $stmt->fetch();
    echo "<p>Total de usuários na tabela: <span style=\"font-weight: bold;\">" . $result["total_usuarios"] . "</span></p>";

    if ($result["total_usuarios"] > 0) {
        echo "<p style=\"color: green;\">A tabela 'usuarios' existe e contém dados.</p>";
        echo "<p>Tentando buscar o usuário admin@sistema.com...</p>";
        $stmt = $pdo->prepare("SELECT id, nome, email, hierarquia FROM usuarios WHERE email = ?");
        $stmt->execute(['admin@sistema.com']);
        $admin_user = $stmt->fetch();

        if ($admin_user) {
            echo "<p style=\"color: green;\">Usuário 'admin@sistema.com' encontrado:</p>";
            echo "<pre>";
            print_r($admin_user);
            echo "</pre>";
        } else {
            echo "<p style=\"color: orange;\">Aviso: Usuário 'admin@sistema.com' NÃO encontrado na tabela 'usuarios'.</p>";
            echo "<p>Isso pode indicar que a importação do schema.sql não incluiu o usuário padrão, ou que ele foi removido.</p>";
        }

    } else {
        echo "<p style=\"color: red;\">Erro: A tabela 'usuarios' está vazia ou não existe. O schema.sql pode não ter sido importado corretamente.</p>";
    }

} catch (PDOException $e) {
    echo "<p style=\"color: red; font-weight: bold;\">Erro na conexão com o banco de dados:</p>";
    echo "<p style=\"color: red;\">" . $e->getMessage() . "</p>";
    echo "<p>Verifique as credenciais do banco de dados (host, nome do banco, usuário, senha) no script e no seu arquivo config/database.php.</p>";
} catch (Exception $e) {
    echo "<p style=\"color: red; font-weight: bold;\">Ocorreu um erro inesperado:</p>";
    echo "<p style=\"color: red;\">" . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p>Fim do teste.</p>";

?>
