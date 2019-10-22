<?php
$mydb = mysqli_connect('p:localhost', 'login', 'password', 'books');
mysqli_set_charset($mydb, 'utf8');

function detect_encoding($string, $pattern_size = 50)
{
    $list = array('cp1251', 'utf-8', 'ascii', '855', 'KOI8R', 'ISO-IR-111', 'CP866', 'KOI8U');
    $c = strlen($string);
    if ($c > $pattern_size) {
        $string = substr($string, floor(($c - $pattern_size) / 2), $pattern_size);
        $c = $pattern_size;
    }

    $reg1 = '/(\xE0|\xE5|\xE8|\xEE|\xF3|\xFB|\xFD|\xFE|\xFF)/i';
    $reg2 = '/(\xE1|\xE2|\xE3|\xE4|\xE6|\xE7|\xE9|\xEA|\xEB|\xEC|\xED|\xEF|\xF0|\xF1|\xF2|\xF4|\xF5|\xF6|\xF7|\xF8|\xF9|\xFA|\xFC)/i';

    $mk = 10000;
    $enc = 'ascii';
    foreach ($list as $item) {
        $sample1 = @iconv($item, 'cp1251', $string);
        $gl = @preg_match_all($reg1, $sample1, $arr);
        $sl = @preg_match_all($reg2, $sample1, $arr);
        if (!$gl || !$sl)
            continue;
        $k = abs(3 - ($sl / $gl));
        $k += $c - $gl - $sl;
        if ($k < $mk) {
            $enc = $item;
            $mk = $k;
        }
    }
    return $enc;
}

function endThis($exc)
{
    global $json;
    $json['errorMessage'] = $exc->getMessage();
    $json['errorCode'] = $exc->getCode();
    echo json_encode($json, JSON_UNESCAPED_UNICODE);
    exit();
}

function countLetters($letters, $string)
{
    $count = False;
    foreach ($letters as $letter) {
        $count[$letter] = substr_count($string, $letter);
    }
    return $count;
}

function try_get_path($path)
{
    if ($path = @realpath($path)) {
        return $path;
    } else {
        return false;
    }
}

$json['errorCode'] = 0;
try {
    if (php_sapi_name() == "cli") {
        // In cli-mode
        $options = getopt('f:i:');
        if (isset($options['f'])) {
            $filename = '/tmp/' . $options['f'];
        } elseif (isset($options['i'])) {
            $id = $options['i'];
        } else {
            throw new Exception('Вкажіть id або ім`я файлу');
        }
    } else {
        // Not in cli-mode
        $id = isset($_POST['id']) ? filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) :
            filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    }
    if ($id) {
        $query = mysqli_query($mydb, 'SELECT `filename` FROM `books` WHERE `id`="' . $id . '";');
        $filename = './books/' . mysqli_fetch_row($query)[0];
    }
    if (!try_get_path($filename)) {
        throw new Exception('Файл не вдалося знайти');
    }
    $file = file_get_contents($filename);
    if (!$file) {
        throw new Exception('Файл не вдалося розпізнати', 1);
    }

} catch (Exception $exc) {
    endThis($exc);
}

$letters['ukr'] = ['А', 'Б', 'В', 'Г', 'Ґ', 'Д', 'Е', 'Є', 'Ж', 'З', 'И', 'І', 'Ї', 'Й', 'К', 'Л', 'М', 'Н', 'О', 'П', 'Р', 'С', 'Т', 'У', 'Ф', 'Х', 'Ц', 'Ч', 'Ш', 'Щ', 'Ь', 'Ю', 'Я'];
$letters['ru'] = ['Ы', 'Э', 'Ё', 'Ъ'];
foreach (range('A', 'Z') as $letter) {
    $letters['eng'][] = $letter;
}

try {
    //Если кодировка не utf-8 - пробуем опознать и перекодировать
    if (!mb_check_encoding($file, 'utf-8')) {
        $encoding = detect_encoding($file);
        $file = iconv($encoding, 'utf-8', $file);
    }
    //Всё в большие буквы и считаем
    $string = mb_strtoupper($file, 'UTF-8');
    $json['all_letters'] = 0;
    foreach ($letters as $lang => $lang_letters) {
        $json[$lang] = countLetters($lang_letters, $string);
        $json[$lang . '_letters'] = array_sum($json[$lang]);
        $json['all_letters'] += $json[$lang . '_letters'];
    }

} catch (Exception $exc) {
    endThis($exc);
}
$json['execution_time'] = (microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"]);
echo json_encode($json, JSON_UNESCAPED_UNICODE);

