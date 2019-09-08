# Usage

```php
//use statements here ..

$idList = [1,2,3,4,5,6,11,12,13,17];
$myDataList = Injector::inst()->create(
    FasterIDLists::class,
    MyClass:class
    $idLists
)->filteredDatalist();
```
//or

```php
//use statements here ..

$idList = [1,2,3,4,5,6,11,12,13,17];
$whereStatement = Injector::inst()->create(
    FasterIDLists::class,
    MyClass:class
    $idLists
)->shortenIdList();

$myDataList = MyClass::get()->where($whereStatement);
```
