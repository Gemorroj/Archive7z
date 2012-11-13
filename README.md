# Работа с 7z архивами с помощью командной строки.

На данный момент, поддерживается распаковка всего архива,
распаковка любой директории или файла в архиве,
просмотр всех файлов и директорий находящихся в архиве,
получение содержимого файла в архиве.

Пример:
```php
<?php
set_include_path(__DIR__);
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
```