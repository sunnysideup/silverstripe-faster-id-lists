<?php

namespace Sunnysideup\FasterIdLists;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DB;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;

/**
 * turns a query statement of select from MyTable where ID IN (1,,2,3.......999999)
 * into something like:
 * - select from MyTable where ID between 0 and 99 or between 200 and 433
 * OR
 * - select from MyTable where ID NOT IN (4543)
 *

 */

class FasterIDLists
{
    use Configurable;

    protected static $table_count_cache = [];

    protected static $table_name_cache = [];

    /**
     *
     * @var int
     */
    private static $acceptable_max_number_of_select_statements = 1000;

    /**
     *
     * @var bool
     */
    private static $include_exclude_option = true;

    /**
     *
     * @var array
     */
    protected $idList = [];

    /**
     *
     * @var string
     */
    protected $className = '';

    /**
     *
     * @var string
     */
    protected $field = 'ID';

    /**
     *
     * @var bool
     */
    protected $isNumber = true;

    /**
     *
     * @param string  $className class name of Data Object being queried
     * @param array   $idList array of ids (or other field) to be selected from class name
     * @param string  $field usually the ID field, but could be another field
     * @param boolean $isNumber is the field a number type (so that we can do ranges OR something else)
     */
    public function __construct(string $className, array $idList, $field = 'ID', $isNumber = true)
    {
        $this->className = $className;
        $this->idList = $idList;
        $this->field = $field;
        $this->isNumber = $isNumber;
    }

    public function setIdList(array $idList) : FasterIDLists
    {
        $this->idList = $idList;

        return $this;
    }

    public function setField(string $field) : FasterIDLists
    {
        $this->field = $field;

        return $this;
    }

    public function setIsNumber(bool $isNumber) : FasterIDLists
    {
        $this->isNumber = $isNumber;

        return $this;
    }

    public function setTableName(string $tableName) : FasterIDLists
    {
        self::$table_name_cache[$this->className] = $tableName;

        return $this;
    }

    public function filteredDatalist(): DataList
    {
        $className = $this->className;
        if(count($this->idList) <= $this->Config()->acceptable_max_number_of_select_statements) {
            return $className::get()->filter([$this->field => $this->idList]);
        } else {
            $whereStatement = $this->shortenIdList();
            if($whereStatement) {
                return $className::get()->where($whereStatement);
            }
        }

        //default option ...
        return $className::get()->filter([$this->field => $this->idList]);

    }

    public function shortenIdList() : string
    {
        $finalArray = [];
        $operator = '';
        $glue = 'OR';
        $myIDList = $this->idList;
        $countMyIDList = count($myIDList);
        if($countMyIDList > 0) {
            if($this->isNumber) {
                $otherArray = [];

                //simplify select statement
                $ranges = $this->findRanges($myIDList);
                $rangesCount = count($ranges);

                //if it is long, then see if exclude is a better solution ...
                if($this->Config()->include_exclude_option) {
                    $excludeList = $this->excludeList();
                    if($excludeList) {
                        $excludeRanges = $this->findRanges($excludeList);
                        if(count($excludeRanges) < $rangesCount) {
                            $ranges = $excludeRanges;
                            $glue = 'AND';
                            $operator = 'NOT';
                        }
                    }
                }
                foreach($ranges as $range) {
                    $min = min($range);
                    $max = max($range);
                    if($min === $max) {
                        $otherArray[$min] = $min;
                    } else {
                        $finalArray[] = '"'.$this->getTableName().'"."'.$this->field.'" '.$operator.' BETWEEN '.$min.' AND '.$max;
                    }
                }
                if(count($otherArray)) {
                    $finalArray[] = '"'.$this->getTableName().'"."'.$this->field.'" '.$operator.'  IN('.implode(',', $otherArray).')';
                }
            } else {
                //if it is long, then see if exclude is a better solution ...
                if($this->Config()->include_exclude_option) {
                    $excludeList = $this->excludeList();
                    if($excludeList) {
                        if(count($excludeList) < $countMyIDList) {
                            $myIDList = $excludeList;
                            $glue = 'AND';
                            $operator = 'NOT';
                        }
                    }
                    $finalArray[] = '"'.$this->getTableName().'"."'.$this->field.'" '.$operator.'  IN(\''.implode('\',\'', $myIDList).'\')';
                }
            }
        }
        if(count($finalArray) === 0) {
            $finalArray[] = '"'.$this->getTableName().'"."'.$this->field.'" '.$operator.'  IN(-1)';
        }

        return '('.implode(') '.$glue.' (', $finalArray).')';
    }

    public function excludeList() : ?array
    {
        $className = $this->className;
        $countOfList = count($this->idList);
        //only run exclude if there is clear win
        $tableCount = $this->getTableCount();
        //there is more items in the list then
        if($countOfList > ($tableCount / 2)) {
            $fullList = $className::get()->column($this->field);

            return array_diff($fullList, $this->idList);
        }
        return null;
    }

    /**
     * get table name for query
     * @return string
     */
    protected function getTableName() : string
    {
        if(! isset(self::$table_name_cache[$this->className])) {
            self::$table_name_cache[$this->className] = Config::inst()->get($this->className, 'table_name');
        }
        return self::$table_name_cache[$this->className];
    }

    protected function getTableCount() : int
    {
        $tableName = $this->getTableName();
        if(! isset(self::$table_count_cache[$tableName])) {
            self::$table_count_cache[$tableName] = DB::query('
                SELECT COUNT(*)
                FROM "'.$this->getTableName().'"
                WHERE "'.$this->getTableName().'"."ID" IS NOT NULL'
            )->value();
        }

        return self::$table_count_cache[$tableName];
    }

    /**
     * return value looks like this:
     *      [
     *          0: 3,4,5,6,
     *          1: 8,9,10,
     *          2: 91
     *          3: 100,101
     *          etc...
     *      ]
     *
     *
     * @return array
     */
    protected function findRanges($idList) : array
    {
        $ranges = [];
        $lastOne = 0;
        $currentRangeKey = 0;
        sort($idList);
        foreach($idList as $key => $id) {
            //important
            $id = intval($id);
            if($id === ($lastOne + 1)) {
                // do nothing
            } else {
                $currentRangeKey++;

            }
            if(! isset($ranges[$currentRangeKey])) {
                $ranges[$currentRangeKey] = [];
            }
            $ranges[$currentRangeKey][$id] = $id;
            $lastOne = $id;
        }

        return $ranges;
    }

}
