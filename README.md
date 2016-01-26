# Работа с 7z архивами с помощью командной строки.

[![Build Status](https://secure.travis-ci.org/Gemorroj/Archive7z.png?branch=master)](https://travis-ci.org/Gemorroj/Archive7z)


### Функции:

- распаковка всего архива;
- распаковка любой директории или файла в архиве;
- просмотр всех файлов и директорий находящихся в архиве;
- получение содержимого файла в архиве;
- удаление директории или файла из архива;
- добавление файлов в архив;
- проверка корректности архива;


### Требования:

- PHP >= 5.3
- shell
- **7z >= 7.30 (p7zip >= 9.38)**


### Установка через composer:

- Добавьте проект в ваш файл composer.json:

```json
{
    "require": {
        "gemorroj/archive7z": "dev-master"
    }
}
```
- Установите проект:

```bash
$ php composer.phar update gemorroj/archive7z
```


### Пример работы:

```php
<?php
$obj = new Archive7z('./test.7z');

foreach ($obj->getEntries() as $v) {
    if ($v->getPath() === 'test.txt') {
        print_r($v);
        $v->extractTo('./test2');
    }
}

echo $obj->getContent('test/test.txt');

$obj->setOutputDirectory('./test');
$obj->extract();

$obj->addEntry(__FILE__);
$obj->addEntry('Tests/bootstrap.php', false);
```
