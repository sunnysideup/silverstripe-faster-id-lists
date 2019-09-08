Use this code like this:

```
//use statements here ..

$idList = [1,2,3,4,5,6,11,12,13,17]l
Injector::inst()->create(
    FasterIDLists::class,
    MyClass:class
    $idLists
)->filteredDatalist();

//or
//use statements here ..

$idList = [1,2,3,4,5,6,11,12,13,17]l
Injector::inst()->create(
    FasterIDLists::class,
    MyClass:class
    $idLists
)->shortenIdList();
