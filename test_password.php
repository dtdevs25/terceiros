<?php
$password = 'admin123';
$hashed_password_from_db = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'; // A hash do seu banco de dados

echo "<h1>Teste de Senha</h1>";
echo "<p>Senha digitada (plain): " . $password . "</p>";
echo "<p>Hash do banco de dados: " . $hashed_password_from_db . "</p>";

if (password_verify($password, $hashed_password_from_db)) {
    echo "<p style=\"color: green; font-weight: bold;\">password_verify() retornou TRUE. A senha corresponde à hash.</p>";
} else {
    echo "<p style=\"color: red; font-weight: bold;\">password_verify() retornou FALSE. A senha NÃO corresponde à hash.</p>";
}

echo "<hr>";
echo "<p>Gerando nova hash para 'admin123': " . password_hash('admin123', PASSWORD_DEFAULT) . "</p>";
?>
