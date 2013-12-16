# Работа с 7z архивами с помощью командной строки.

[![Build Status](https://secure.travis-ci.org/Gemorroj/Archive7z.png?branch=master)](https://travis-ci.org/Gemorroj/Archive7z)


Поддерживается распаковка всего архива, распаковка любой директории или файла в архиве, просмотр всех файлов и директорий находящихся в архиве, получение содержимого файла в архиве, удаление директории или файла из архива, добавление файлов в архив, проверка корректности архива.

Требования:

- PHP >= 5.3
- shell
- **7z >= 7.30** (т.е. на данный момент не работает под *nix, Игорь Павлов планирует открыть исходный код новой версии в январе 2014г. [ссылка](http://sourceforge.net/p/sevenzip/discussion/45797/thread/207a4f9e/#bb54) )


Пример:
```php
<?php
$obj = new Archive7z('./test.7z');
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