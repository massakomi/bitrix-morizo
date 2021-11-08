<?php

// Настройки
define('IBLOCK_ID', 4);
define('LOAD_FILENAME', 'load-200000.xml');

// Инит движка
set_time_limit(0);
ini_set('memory_limit', '1024M');

$_SERVER['DOCUMENT_ROOT'] = str_replace('\\', '/', realpath(dirname(__FILE__).'/../../'));
$DOCUMENT_ROOT = $_SERVER['DOCUMENT_ROOT'];

define("NO_KEEP_STATISTIC", true);
define("NO_AGENT_CHECK", true);

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");
while (ob_get_level()) {
    ob_end_flush();
}

CModule::IncludeModule('iblock');
CModule::IncludeModule('sale');


/**
 * Добавляет элемент в инфоблок
 */
function addElement($name, $content) {
    $product = Array( 
        'IBLOCK_ID'         => IBLOCK_ID,
        'NAME'              => $name,
        //'CODE'              => 'test',
        //'PROPERTY_VALUES'   => $props,
        //'IBLOCK_SECTION_ID' => false,
        'TIMESTAMP_X'       => date('Y-m-d H:i:s'),
        'ACTIVE'            => 'Y',
        'PREVIEW_TEXT'      => $content, 
    );
     
    $el = new CIBlockElement;
    $id = $el->Add($product);
    return $id;
}

/**
 * Загрузка xml файла в базу
 */
function loadXmlFile($filename)
{
    $filename = dirname(__FILE__).'/'.$filename;
    if (!file_exists($filename)) {
        throw new Exception('Файл не найден');
    }

    $xml = simplexml_load_file($filename);

    $added = 0;
    foreach ($xml as $k => $v) {

        $id = addElement($v->Name, $v->Content);
        if ($id) {
            $added ++;
        }

        if ($v->Id % 1000 == 0) {
            $f = fopen('loadxml.txt', 'w');
            fwrite($f, "\n".date('Y-m-d H:i').' '.$v->Id);
            fclose($f);
            echo 'Текущий ID: '.$v->Id."\n";
        }
    }

    $event = new Bitrix\Main\Event("basic", "CustomMorizoTestTaskEvent", array($added));
    $event->send();
}

/**
 * Генератор рандомной строки
 */
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

/**
 * Создает xml элемент
 */
function node($root, $tag, $text='', $attrs=[])
{
    global $xml;
    $element = $root->appendChild($xml->createElement($tag));
    if ($attrs) {
        foreach ($attrs as $k => $v) {
            $attr = $xml->createAttribute($k);
            $attr->value = $v;
            $element->appendChild($attr);
        }
    }
    if ($text) {
      $element->appendChild($xml->createTextNode($text));
    }
    return $element;
}

/**
 * Генератор xml файла
 */
function createBigXml($max = 20) {
    global $xml;
    $xml = new DOMDocument('1.0', 'utf-8');

    $rlf = node($xml, 'Ads', $text='', ['formatVersion' => 3, 'target' => 'Avito.ru']);

    ;
    for ($i = 0; $i < $max; $i++) {
        $ad = $rlf->appendChild($xml->createElement('Ad'));
        node($ad, 'Id', $i);
        node($ad, 'Name', generateRandomString(100));
        node($ad, 'Content', generateRandomString(1000));
    }

    $xml->formatOutput = true;
    $xml->save('load-'.$max.'.xml');
}



// Проверка существования инфоблока
$count = \Bitrix\Iblock\IblockTable::getCount(['ID' => IBLOCK_ID]);
if (!$count) {
    throw new Exception('iblock not found: '.IBLOCK_ID);
}

# Проверка наличия свойства заказа
$res = \Bitrix\Sale\Property::getList(array(
    'filter' => ["NAME" => 'UTM_SOURCE']
));
if (!$res->fetch()) {
    throw new Exception('not found property: UTM_SOURCE, please add UTM_SOURCE order Property');
}

// Создать большой отладочный файл при отсутствии
if (!file_exists(LOAD_FILENAME)) {
    if ($_GET['create_xml']) {
        createBigXml(200000);
        echo 'Файл создан!';
    } else {
        echo 'Отсутствует файл "'.LOAD_FILENAME.'" - <a href="?create_xml=1">создать файл?</a>';
    }
}

// Добавим обработчик события CustomMorizoTestTaskEvent, чтобы записать инфо в лог
\Bitrix\Main\EventManager::getInstance()->addEventHandler(
    'basic',
    'CustomMorizoTestTaskEvent',
    function($event) {
        $count = $event->getParameter(0);
        echo 'Количество записей: '.$count;
        Bitrix\Main\Diag\Debug::writeToFile($count, 'Количество записей', 'log-path-from-root.txt');
    }
);

# php -f local/console/loadxml.php load
if (PHP_SAPI == 'cli') {
    if ($argv[1] == 'load') {
        loadXmlFile(LOAD_FILENAME);
    }
    exit;
} else {
    echo 'Запуск скрита: php -f local/console/loadxml.php load';
}

