<?php
namespace gigachat;

class Gigachat
{
    // Константа для авторизации клиента
    protected const CLIENT_AUTH = "<YOUR_API_KEY>";

    // Статическое свойство для хранения единственного экземпляра класса
    protected static $instance;
    // Статические переменные для хранения токена и его срока действия
    private static $token = false;
    private static $tokenExp = false;
    // Статическое свойство для хранения истории сообщений
    private static $messages = [];

    // Метод для получения единственного экземпляра класса
    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self;
            self::$token = file_get_contents('token.txt');
            self::$tokenExp = file_get_contents('token_ext.txt');
        }
        return self::$instance;
    }

    // Метод для получения истории сообщений
    public static function getHistory()
    {
        return self::$messages;
    }
    // Метод для очистки истории сообщений
    public static function clearHistory()
    {
        self::$messages = [];
    }
    // Метод для обновления истории сообщений
    public static function updateHistory($messages)
    {
        self::$messages = $messages;
    }

    // Приватный метод для выполнения HTTP GET запроса
    private static function get($url, $headers, $data)
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_POST => 1,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => 1,
        ]);
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }

        $result = curl_exec($curl);
        return json_decode($result, true);
    }

    // Приватный метод для получения изображения по его идентификатору
    private static function get_image($token, $image)
    {
        $curl = curl_init();// Инициализация нового сеанса cURL

        curl_setopt_array($curl, array(// Установка опций для сеанса cURL
            CURLOPT_URL => 'https://gigachat.devices.sberbank.ru/api/v1/files/' . $image . '/content',// URL, на который будет отправлен запрос
            CURLOPT_SSL_VERIFYPEER => 0,// Отключение проверки SSL
            CURLOPT_RETURNTRANSFER => true,// Возврат результата вместо вывода его в браузер
            CURLOPT_ENCODING => '',// Кодировка, которую следует использовать в выводе
            CURLOPT_MAXREDIRS => 10,// Максимальное количество HTTP-перенаправлений, которое следует следовать
            CURLOPT_TIMEOUT => 0,// Максимальное время выполнения cURL-функций в секундах
            CURLOPT_FOLLOWLOCATION => true,// Следование любому заголовку "Location: ", отправленному сервером в своем ответе
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,// Указание версии HTTP для использования в запросе
            CURLOPT_CUSTOMREQUEST => 'GET',// Установка HTTP-метода в GET
            CURLOPT_HTTPHEADER => array(// Установка HTTP-заголовков для запроса
                'Accept: application/jpg',// Принимаемый тип контента
                'Authorization: Bearer ' . $token// Токен авторизации
            ),
        ));

        $result = curl_exec($curl);// Выполнение cURL-запроса и сохранение результата в переменную

        if ($result) {// Если результат не пустой
            // Запись результата в файл
            file_put_contents(dirname($_SERVER['SCRIPT_FILENAME']) . "/img/" . $image . ".jpg", $result);//фактический путь
            $img = "img/" . $image . ".jpg";// путь в ответе
        } else {
            $img = false;// Если результат пустой, установка переменной изображения в false
        }

        return $img;// Возврат имени файла изображения или false
    }

    // Приватный метод для генерации уникального идентификатора в формате UUIDv4
    private static function guidv4($data = null)
    {
        $data = $data ?? random_bytes(16);
        assert(strlen($data) == 16);

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    // Метод для получения токена авторизации
    public static function getToken($force = false)
    {
        if (!self::$token || !self::$tokenExp || self::$tokenExp < time() || $force === true) {
            $url = "https://ngw.devices.sberbank.ru:9443/api/v2/oauth";
            $headers = [
                'Authorization: Bearer ' . self::CLIENT_AUTH,
                'RqUID: ' . self::guidv4(),
                'Content-Type: application/x-www-form-urlencoded'
            ];
            $data = [
                'scope' => 'GIGACHAT_API_PERS'
            ];
            $result = self::get($url, $headers, http_build_query($data));

            if (!empty($result["access_token"])) {
                self::$token = $result["access_token"];
                file_put_contents('token.txt', self::$token);
                self::$tokenExp = round($result["expires_at"] / 1000);
                file_put_contents('token_ext.txt', self::$tokenExp);
            } else {
                self::$token = false;
                file_put_contents('token.txt', '');
                self::$tokenExp = false;
                file_put_contents('token_ext.txt', '');
            }
        }
        return self::$token;
    }

    // Метод для получения ответа на вопрос
    public static function answer($question, $temperature = 0.7)
    {
        $answer = "";
        if (!empty($question)) {
            $tok = self::getToken();

            $no_history = false;
            // Проверяем, содержит ли вопрос ключевые слова для нарисования или изображения
            if (preg_match('/нарисуй|изобрази/uis', $question, $matches)) {
                $no_history = true;
                $temperature = 1;
            }

            if ($tok) {
                $url = "https://gigachat.devices.sberbank.ru/api/v1/chat/completions";
                $headers = [
                    'Authorization: Bearer ' . $tok,
                    'Content-Type: application/json',
                    'Accept: application/json'
                ];
                // Если необходимо сохранять историю сообщений, берем ее из свойства класса
                if ($no_history === false) {
                    $messages = self::$messages;
                }
                // Добавляем текущее сообщение в историю
                $messages[] = [
                    "role" => "user",
                    "content" => $question
                ];
                $data = [
                    "model" => "GigaChat:latest",
                    "temperature" => $temperature,
                    "max_tokens" => 1024,
                    "messages" => $messages
                ];
                $result = self::get($url, $headers, json_encode($data));

                // Получаем ответ
                $answer = $result["choices"][0]["message"]["content"];

                if (!empty($answer)) {
                    // Если необходимо сохранять историю сообщений, добавляем в нее ответ
                    if ($no_history === false) {
                        $messages[] = [
                            "role" => "assistant",
                            "content" => $answer
                        ];

                        self::$messages = $messages;
                    }

                    // Поиск изображений в ответе
                    preg_match_all('/<img[^>]*?src=\"(.*)\"/iU', $answer, $imageSearch);
                    if (isset($imageSearch[1][0])) {
                        // Удаляем теги изображений из ответа и получаем ссылку на изображение
                        $answer = preg_replace('/<img[^>]*>/i', '', $answer);
                        $image = self::get_image($tok, $imageSearch[1][0]);
                        // Формируем ссылку на изображение
                        $answer .= '<img src="https://' . $_SERVER['SERVER_NAME'].dirname($_SERVER['PHP_SELF']) . '/' . $image . '">';
                    }
                }
            }
        }
        return $answer;
    }
}
?>
