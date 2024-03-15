<?php
// Подключение файла с классом Gigachat
require_once 'gigaclass.php';

// Проверяем, была ли отправлена форма
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Получаем сообщение из формы
    $user_message = $_POST['message'];
    // Получаем ответ с помощью метода answer() из класса Gigachat
    $answer = gigachat\Gigachat::answer($user_message);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GigaChat Integration</title>
</head>
<body>
<h1>GigaChat Integration</h1>
<form method="POST" action="">
    <label for="user_message">Введите сообщение:</label><br>
    <textarea name="message"></textarea><br>
    <button type="submit">Отправить</button>
</form>
    <div><?php echo "Вопрос: ".$_POST['message'];?></div>
    <div><?php if(isset($answer)) echo 'Ответ('.date("h:i:s m.d.Y").'): ' . $answer; ?></div>
</body>
<footer>
    <hr>
</footer>
</html>
