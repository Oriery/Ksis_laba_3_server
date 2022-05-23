<?php

/*
    GET - чтение файла /filename 
    PUT - перезапись файла /filename + BODY
    POST - добавление в конец файла /filename + BODY
    DELETE - удаление файла /filename
    COPY - копирование файла /filename/filename1
    MOVE - перемещение файла /filename/filename1
    FILES - список файлов
*/

define("BUFFER", 10485760);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS,FILES,COPY,MOVE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

$method = $_SERVER['REQUEST_METHOD'];
$input = file_get_contents('php://input');

logMe(["method" => $method, "input" => strlen($input) > 100 ? "### Файл больше 100 байт ###" : $input]);

if($method == "OPTIONS") {  
    header("HTTP/1.1 200 OK OPTIONS");
    exit;
}
 
chdir('files');

if (($method == "COPY" or $method == "MOVE" or $method == "GET" or $method == "DELETE") and isset($_GET['fileFrom'])) {
    $url_fileFrom = str_replace("\\", "/", $_GET['fileFrom']);
    logMe(["url_fileFrom" => $url_fileFrom]);
} 
if (($method == "PUT" or $method == "POST" or $method == "COPY" or $method == "MOVE") and isset($_GET['fileTo'])) {
    $url_fileTo = str_replace("\\", "/", $_GET['fileTo']);
    logMe(["url_fileTo" => $url_fileTo]);
}

// Проверка получения путей к файлам
if (($method == "COPY" or $method == "MOVE") and !isset($url_fileFrom) and !isset($url_fileTo)) {
    header('HTTP/1.1 400 Must Give fileFrom and fileTo');
    exit;
}
if (($method == "PUT" or $method == "POST") and !isset($url_fileTo)) {
    header('HTTP/1.1 400 Must Give fileTo');
    exit;
}
if (($method == "GET" or $method == "DELETE") and !isset($url_fileFrom)) {
    header('HTTP/1.1 400 Must Give fileFrom');
    exit;
}

// TODO Проверка папки (нельзя .. и абсолютный путь)
// TODO снйчас можно get сами папки

// Проверка наличия файла fileFrom
if (isset($url_fileFrom) and !file_exists($url_fileFrom)) {
    header('HTTP/1.1 404 File Not Found');
        exit;
}

// Создание пути к fileTo
if (isset($url_fileTo) and !file_exists($url_fileTo)) {
    $paths = pathinfo($url_fileTo);

    if (!is_dir($paths['dirname'])) {
        mkdir($paths['dirname'], 0777, true);
    }
}

switch ($method) {
    case "FILES":
        header("HTTP/1.1 200 List Given");

        $files = glob_recursive("*");
    
        foreach ($files as $file)
            if (!is_dir($file))
                echo "\"" . $file . "\" ";
        break;

    case 'GET':
        $myfile = fopen($url_fileFrom, "r");

        if (isset($_SERVER['HTTP_CURRENT'])) {
            fseek($myfile, BUFFER * (intval($_SERVER['HTTP_CURRENT'] - 1)));
            echo fread($myfile, BUFFER);
            fseek($myfile, 0);
        } else {
            header('Blocks: ' . ceil(filesize($url_fileFrom) / BUFFER));
            echo fread($myfile, BUFFER);
            fseek($myfile, 0);
        }

        fclose($myfile);
        break;

    case 'PUT':
        $result = file_put_contents($url_fileTo, $input, LOCK_EX);
        if ($result === FALSE) {
            header('HTTP/1.1 500 Rewriting Error');
        }
        else
        {
            header('HTTP/1.1 200 Successfully Rewritten');
        }

        break;

    case 'POST':
        $result = file_put_contents($url_fileTo, $input, FILE_APPEND | LOCK_EX);
        if ($result === FALSE)
            header('HTTP/1.1 500 Post Error');
        else
            header('HTTP/1.1 200 Successfully Added');

        break;

    case 'DELETE':
        if (!unlink($url_fileFrom))
            header('HTTP/1.1 500 Delete Error');
        else
            header('HTTP/1.1 200 Successfully Deleted');

        break;

    case 'COPY':
        $paths = pathinfo($url_fileTo);

        if (!is_dir($paths['dirname']))
            mkdir($paths['dirname'], 0777, true);

        if (!copy($url_fileFrom, $url_fileTo))
            header('HTTP/1.1 500 Copy Error');
        else
            header('HTTP/1.1 200 Successfully Copied');

        break;

    case 'MOVE':
        $paths = pathinfo($url_fileTo);

        if (!is_dir($paths['dirname']))
            mkdir($paths['dirname'], 0777, true);

        if (!copy($url_fileFrom, $url_fileTo))
            header('HTTP/1.1 500 Move Error');
        else
            if (!unlink($url_fileFrom))
                header('HTTP/1.1 500 Cannot Delete Old File');
            else
                header('HTTP/1.1 200 Successfully Moved');

        break;

    default:
        header('HTTP/1.1 400 Bad Request');

        break;
}

function glob_recursive($pattern, $flags = 0)
{
    $files = glob($pattern, $flags);
    foreach (glob(dirname($pattern) . '/*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
        $files = array_merge($files, glob_recursive($dir . '/' . basename($pattern), $flags));
    }

    return $files;
}

function logMe($whatToLog) {
    $log = date('Y-m-d H:i:s') . ' ' . print_r($whatToLog, true);
    
    file_put_contents(__DIR__ . '/log.txt', $log . PHP_EOL, FILE_APPEND);
}