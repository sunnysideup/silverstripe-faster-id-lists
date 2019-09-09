# Usage

```php
use SilverStripe\Core\Injector\Injector;
use Sunnysideup\FasterIdLists\FasterIDLists;

$idList = [1,2,3,4,5,6,11,12,13,17];
$myDataList = Injector::inst()->create(
    FasterIDLists::class,
    MyClass:class
    $idLists
)->filteredDatalist();
```
//or

```php
use SilverStripe\Core\Injector\Injector;
use Sunnysideup\FasterIdLists\FasterIDLists;

$idList = [1,2,3,4,5,6,11,12,13,17];
$whereStatement = Injector::inst()->create(
    FasterIDLists::class,
    MyClass:class
    $idLists
)->shortenIdList();

$myDataList = MyClass::get()->where($whereStatement);
```
