# Работа с 7z архивами с помощью командной строки.

[![Build Status](https://secure.travis-ci.org/Gemorroj/Archive_7z.png?branch=master)](https://travis-ci.org/Gemorroj/Archive_7z)


На данный момент, поддерживается распаковка всего архива,
распаковка любой директории или файла в архиве,
просмотр всех файлов и директорий находящихся в архиве,
получение содержимого файла в архиве,
удаление директории или файла из архива,
добавление файлов в архив.

Требования:

- PHP >= 5.2
- shell
- 7z


Пример:
```php
<?php
set_include_path(dirname(__FILE__));
require 'Archive/7z.php';

$obj = new Archive_7z('./test.7z');
$obj->setOutputDirectory('./test');

foreach ($obj->getEntries() as $v) {
    if ($v->getName() === 'test.txt') {
        print_r($v);
        $v->extractTo('./test2');
    }
}

echo $obj->getContent('test.txt');

$obj->extract();

$obj->addEntry(__FILE__);
$obj->addEntry('Tests/bootstrap.php', false);
```