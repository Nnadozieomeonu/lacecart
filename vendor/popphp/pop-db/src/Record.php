<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2015 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Db;

/**
 * Record class
 *
 * @category   Pop
 * @package    Pop_Db
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2015 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0
 */
class Record implements \ArrayAccess
{

    /**
     * Database connection(s)
     * @var array
     */
    protected static $db = ['default' => null];

    /**
     * SQL Object
     * @var Sql
     */
    protected static $sql = null;

    /**
     * Table name
     * @var string
     */
    protected static $table = null;

    /**
     * Table prefix
     * @var string
     */
    protected static $prefix = null;

    /**
     * Result rows (an array of arrays)
     * @var array
     */
    protected $rows = [];

    /**
     * Result rows as objects (an array of ArrayObjects)
     * @var array
     */
    protected $rowObjects = [];

    /**
     * Columns of the first result row
     * @var string
     */
    protected $columns = [];

    /**
     * Row gateway
     * @var Gateway\Row
     */
    protected $rowGateway = null;

    /**
     * Table gateway
     * @var Gateway\Table
     */
    protected $tableGateway = null;

    /**
     * Primary keys
     * @var array
     */
    protected $primaryKeys = ['id'];

    /**
     * Is new record flag
     * @var boolean
     */
    protected $isNew = false;

    /**
     * Constructor
     *
     * Instantiate the database record object.
     *
     * @param  array $columns
     * @param  Adapter\AbstractAdapter $db
     * @throws Exception
     * @return Record
     */
    public function __construct(array $columns = null, Adapter\AbstractAdapter $db = null)
    {
        $class = get_class($this);

        if (null !== $db) {
            $class::setDb($db);
        }
        if (!static::hasDb()) {
            throw new Exception('Error: A database connection has not been set for this record class.');
        }
        if (null !== $columns) {
            $this->isNew = true;
            $this->setColumns($columns);
        }

        // Set the table name from the class name
        static::parseTableName(get_called_class());
        static::$sql->setTable(static::$table);

        $this->rowGateway   = new Gateway\Row(static::getSql(), $this->primaryKeys, static::$table);
        $this->tableGateway = new Gateway\Table(static::getSql(), static::$table);
    }

    /**
     * Set DB connection
     *
     * @param  Adapter\AbstractAdapter $db
     * @param  boolean                 $isDefault
     * @return void
     */
    public static function setDb(Adapter\AbstractAdapter $db, $isDefault = false)
    {
        $class = get_called_class();

        static::$db[$class] = $db;
        if (($isDefault) || ($class === __CLASS__)) {
            static::$db['default'] = $db;
        }

        static::setSql($db);
    }

    /**
     * Set SQL object
     *
     * @param  Adapter\AbstractAdapter $db
     * @return void
     */
    public static function setSql(Adapter\AbstractAdapter $db)
    {
        static::parseTableName(get_called_class());
        static::$sql = new Sql(static::getDb(), static::$table);
    }

    /**
     * Get DB connection
     *
     * @throws Exception
     * @return Adapter\AbstractAdapter
     */
    public static function getDb()
    {
        $class = get_called_class();

        if (isset(static::$db[$class])) {
            return static::$db[$class];
        } else if (isset(static::$db['default'])) {
            return static::$db['default'];
        } else {
            throw new Exception('No database adapter was found.');
        }
    }

    /**
     * Get DB connection (alias method)
     *
     * @throws Exception
     * @return Adapter\AbstractAdapter
     */
    public static function db()
    {
        return static::getDb();
    }

    /**
     * Check is the class has any DB connections set
     *
     * @return boolean
     */
    public static function hasDb()
    {
        $result = false;

        if (isset(static::$db[get_called_class()])) {
            $result = true;
        } else if (isset(static::$db['default'])) {
            $result = true;
        }

        return $result;
    }

    /**
     * Get SQL object
     *
     * @return Sql
     */
    public static function getSql()
    {
        static::parseTableName(get_called_class());
        static::$sql->setTable(static::$table);
        return static::$sql;
    }

    /**
     * Get SQL object (alias method)
     *
     * @return Sql
     */
    public static function sql()
    {
        return static::getSql();
    }

    /**
     * Get the table prefix
     *
     * @return string
     */
    public static function getPrefix()
    {
        return static::$prefix;
    }

    /**
     * Get the table name
     *
     * @return string
     */
    public static function getTable()
    {
        return static::$table;
    }

    /**
     * Get table info anf return as an array.
     *
     * @return array
     */
    public static function getTableInfo()
    {
        return (new static())->rg()->getTableInfo();
    }

    /**
     * Find by ID method
     *
     * @param  mixed $id
     * @return Record
     */
    public static function findById($id)
    {
        $record = new static();
        $record->rg()->find($id);
        $record->setColumns($record->rg()->getColumns());

        return $record;
    }

    /**
     * Find by method
     *
     * @param  array $columns
     * @param  array $set
     * @param  array $options
     * @return Record
     */
    public static function findBy(array $columns = null, array $set = null, array $options = [])
    {
        $record = new static();
        $params = null;
        $where  = null;

        if (null !== $columns) {
            $parsedColumns = static::parseColumns($columns, $record->sql()->getPlaceholder());
            $params = $parsedColumns['params'];
            $where  = $parsedColumns['where'];
        }

        $record->tg()->select($set, $where, $params, $options);
        $record->setRows($record->tg()->rows());

        return $record;
    }

    /**
     * Find all method
     *
     * @param  array $set
     * @param  array $options
     * @return Record
     */
    public static function findAll(array $set = null, array $options = [])
    {
        return static::findBy(null, $set, $options);
    }

    /**
     * Execute a custom prepared SQL query.
     *
     * @param  string $sql
     * @param  mixed  $params
     * @return Record
     */
    public static function execute($sql, $params)
    {
        if (!is_array($params)) {
            $params = [$params];
        }

        $db = static::getDb();
        $db->prepare($sql)
           ->bindParams($params)
           ->execute();

        $record = new static();
        if (strtoupper(substr($sql, 0, 6)) == 'SELECT') {
            $rows = $db->fetchResult();
            foreach ($rows as $i => $row) {
                $rows[$i] = $row;
            }
            $record->setRows($rows);
        }

        return $record;
    }

    /**
     * Execute a custom SQL query.
     *
     * @param  string $sql
     * @return Record
     */
    public static function query($sql)
    {
        $db = static::getDb();
        $db->query($sql);

        $record = new static();
        if (strtoupper(substr($sql, 0, 6)) == 'SELECT') {
            $rows = [];
            while (($row = $db->fetch())) {
                $rows[] = $row;
            }
            $record->setRows($rows);
        }

        return $record;
    }

    /**
     * Get the total count of a set from the DB table
     *
     * @param  array $columns
     * @return int
     */
    public static function getTotal(array $columns = null)
    {
        $record = new static();
        $params = null;
        $where  = null;

        if (null !== $columns) {
            $parsedColumns = static::parseColumns($columns, $record->sql()->getPlaceholder());
            $params = $parsedColumns['params'];
            $where  = $parsedColumns['where'];
        }

        $record->tg()->select(['total_count' => 'COUNT(1)'], $where, $params);
        $record->setRows($record->tg()->rows());

        return (int)$record->total_count;
    }

    /**
     * Set all the table column values at once.
     *
     * @param  mixed $columns
     * @throws Exception
     * @return Record
     */
    public function setColumns($columns = null)
    {
        // If null, clear the columns.
        if (null === $columns) {
            $this->columns = [];
            $this->rows    = [];
        // Else, if an array, set the columns.
        } else if ($columns instanceof \ArrayObject) {
            $this->columns = (array)$columns;
            $this->rows[0] = $columns;
        // Else, if an array, set the columns.
        } else if (is_array($columns)) {
            $this->columns       = $columns;
            $this->rows[0]       = $columns;
            $this->rowObjects[0] = new \ArrayObject($columns, \ArrayObject::ARRAY_AS_PROPS);
        // Else, throw an exception.
        } else {
            throw new Exception('The parameter passed must be either an array or null.');
        }

        return $this;
    }

    /**
     * Set all the table rows at once
     *
     * @param  array $rows
     * @return Record
     */
    public function setRows(array $rows = null)
    {
        // If null, clear the rows.
        if (null === $rows) {
            $this->columns    = [];
            $this->rows       = [];
            $this->rowObjects = [];
        } else {
            $this->columns = (isset($rows[0])) ? (array)$rows[0] : [];
            $this->rows    = $rows;
            foreach ($this->rows as $row) {
                $this->rowObjects[] = new \ArrayObject($row, \ArrayObject::ARRAY_AS_PROPS);
            }
        }
    }

    /**
     * Get the primary keys
     *
     * @return array
     */
    public function getPrimaryKeys()
    {
        return $this->primaryKeys;
    }

    /**
     * Get the columns
     *
     * @return array
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * Get the columns as a single array object
     *
     * @return \ArrayObject
     */
    public function getColumnsAsObject()
    {
        return new \ArrayObject($this->columns, \ArrayObject::ARRAY_AS_PROPS);
    }

    /**
     * Get the rows
     *
     * @return array
     */
    public function getRows()
    {
        return $this->rows;
    }

    /**
     * Get the rows as an array of array objects
     *
     * @return array
     */
    public function getRowObjects()
    {
        return $this->rowObjects;
    }

    /**
     * Get the rows (alias method)
     *
     * @param  boolean $asObjects
     * @return array
     */
    public function rows($asObjects = true)
    {
        return ($asObjects) ? $this->rowObjects : $this->rows;
    }

    /**
     * Get the count of rows returned in the result
     *
     * @return int
     */
    public function count()
    {
        return count($this->rows);
    }

    /**
     * Determine if the result has rows
     *
     * @return boolean
     */
    public function hasRows()
    {
        return (count($this->rows) > 0);
    }

    /**
     * Save the record
     *
     * @param  array $columns
     * @return void
     */
    public function save(array $columns = null)
    {
        // Save or update the record
        if (null === $columns) {
            $this->rg()->setColumns($this->columns);
            $this->rg()->save($this->isNew);
            $this->setRows([$this->rg()->getColumns()]);
        // Else, save multiple rows
        } else {
            $this->tg()->insert($columns);
            $this->setRows($this->tg()->getRows());
        }
    }

    /**
     * Delete the record or rows of records
     *
     * @param  array $columns
     * @return void
     */
    public function delete(array $columns = null)
    {
        // Delete the record
        if (null === $columns) {
            if ((count($this->columns) > 0) && (count($this->rg()->getColumns()) == 0)) {
                $this->rg()->setColumns($this->columns);
            }
            $this->rg()->delete();
        // Delete multiple rows
        } else {
            $parsedColumns = static::parseColumns($columns, $this->sql()->getPlaceholder());
            $this->tg()->delete($parsedColumns['where'], $parsedColumns['params']);
        }
    }

    /**
     * Get the row gateway object
     *
     * @return Gateway\Row
     */
    protected function rg()
    {
        return $this->rowGateway;
    }

    /**
     * Get the table gateway object
     *
     * @return Gateway\Table
     */
    protected function tg()
    {
        return $this->tableGateway;
    }

    /**
     * Method to get the operator from the column name
     *
     * @param string $column
     * @return array
     */
    protected static function getOperator($column)
    {
        $op = '=';

        if (substr($column, -2) == '>=') {
            $op = '>=';
            $column = trim(substr($column, 0, -2));
        } else if (substr($column, -2) == '<=') {
            $op = '<=';
            $column = trim(substr($column, 0, -2));
        } else if (substr($column, -2) == '!=') {
            $op = '!=';
            $column = trim(substr($column, 0, -2));
        } else if (substr($column, -1) == '>') {
            $op = '>';
            $column = trim(substr($column, 0, -1));
        } else if (substr($column, -1) == '<') {
            $op = '<';
            $column = trim(substr($column, 0, -1));
        }

        return ['column' => $column, 'op' => $op];
    }

    /**
     * Method to parse the table name from the class name
     *
     * @param string $class
     * @return string
     */
    protected static function parseTableName($class)
    {
        if ($class != 'Pop\Db\Record') {
            if (strpos($class, '_') !== false) {
                $cls = substr($class, (strrpos($class, '_') + 1));
            } else if (strpos($class, '\\') !== false) {
                $cls = substr($class, (strrpos($class, '\\') + 1));
            } else {
                $cls = $class;
            }
            $cls = static::camelCaseToUnderscore($cls);
            if (static::$prefix . $cls != static::$table) {
                static::$table = static::$prefix . $cls;
            }
        }
    }

    /**
     * Method to parse the columns to create $where and $param arrays
     *
     * @param  array  $columns
     * @param  string $placeholder
     * @return array
     */
    protected static function parseColumns($columns, $placeholder)
    {
        $params = [];
        $where  = [];

        $i = 1;
        foreach ($columns as $column => $value) {
            if (substr($value, -3) == ' OR') {
                $value   = substr($value, 0, -3);
                $combine = ' OR';
            } else {
                $combine = null;
            }

            $operator = static::getOperator($column);
            if ($placeholder == ':') {
                $pHolder = $placeholder . $operator['column'];
            } else if ($placeholder == '$') {
                $pHolder = $placeholder . $i;
            } else {
                $pHolder = $placeholder;
            }

            // IS NULL or IS NOT NULL
            if (null === $value) {
                if (substr($column, -1) == '-') {
                    $column  = substr($column, 0, -1);
                    $where[] = $column . ' IS NOT NULL' . $combine;
                } else {
                    $where[] = $column . ' IS NULL' . $combine;
                }
            // IN or NOT IN
            } else if (is_array($value)) {
                if (substr($column, -1) == '-') {
                    $column  = substr($column, 0, -1);
                    $where[] = $column . ' NOT IN (' . implode(', ', $value) . ')' . $combine;
                } else {
                    $where[] = $column . ' IN (' . implode(', ', $value) . ')' . $combine;
                }
            // BETWEEN or NOT BETWEEN
            } else if ((substr($value, 0, 1) == '(') && (substr($value, -1) == ')') &&
                (strpos($value, ',') !== false)) {
                if (substr($column, -1) == '-') {
                    $column  = substr($column, 0, -1);
                    $where[] = $column . ' NOT BETWEEN ' . $value . $combine;
                } else {
                    $where[] = $column . ' BETWEEN ' . $value . $combine;
                }
            // LIKE or NOT LIKE
            } else if ((substr($value, 0, 2) == '-%') || (substr($value, -2) == '%-') ||
                (substr($value, 0, 1) == '%') || (substr($value, -1) == '%')) {
                $op = ((substr($value, 0, 2) == '-%') || (substr($value, -2) == '%-')) ? 'NOT LIKE' : 'LIKE';

                $where[]  = $column . ' ' . $op . ' ' .  $pHolder . $combine;
                if (substr($value, 0, 2) == '-%') {
                    $value = substr($value, 1);
                }
                if (substr($value, -2) == '%-') {
                    $value = substr($value, 0, -1);
                }
                if (isset($params[$column])) {
                    if (is_array($params[$column])) {
                        if ($placeholder == ':') {
                            $where[count($where) - 1] .= $i;
                        }
                        $params[$column][] = $value;
                    } else {
                        if ($placeholder == ':') {
                            $where[0] .= ($i - 1);
                            $where[1] .= $i;
                        }
                        $params[$column] = [$params[$column], $value];
                    }
                } else {
                    $params[$column] = $value;
                }
            // Standard operators
            } else {
                $column  = $operator['column'];
                $where[] = $column . ' ' . $operator['op'] . ' ' .  $pHolder . $combine;
                if (isset($params[$column])) {
                    if (is_array($params[$column])) {
                        if ($placeholder == ':') {
                            $where[count($where) - 1] .= $i;
                        }
                        $params[$column][] = $value;
                    } else {
                        if ($placeholder == ':') {
                            $where[0] .= ($i - 1);
                            $where[1] .= $i;
                        }
                        $params[$column] = [$params[$column], $value];
                    }
                } else {
                    $params[$column] = $value;
                }
            }

            $i++;
        }

        return ['where' => $where, 'params' => $params];
    }

    /**
     * Method to convert a camelCase string to an under_score string
     *
     * @param string $string
     * @return string
     */
    protected static function camelCaseToUnderscore($string)
    {
        $strAry  = str_split($string);
        $convert = null;
        $i = 0;

        foreach ($strAry as $chr) {
            if ($i == 0) {
                $convert .= strtolower($chr);
            } else {
                $convert .= (ctype_upper($chr)) ? ('_' . strtolower($chr)) : $chr;
            }
            $i++;
        }

        return $convert;
    }

    /**
     * Magic method to set the property to the value of $this->columns[$name].
     *
     * @param  string $name
     * @param  mixed $value
     * @return void
     */
    public function __set($name, $value)
    {
        $this->columns[$name] = $value;
    }

    /**
     * Magic method to return the value of $this->columns[$name].
     *
     * @param  string $name
     * @return mixed
     */
    public function __get($name)
    {
        return (isset($this->columns[$name])) ? $this->columns[$name] : null;
    }

    /**
     * Magic method to return the isset value of $this->columns[$name].
     *
     * @param  string $name
     * @return boolean
     */
    public function __isset($name)
    {
        return isset($this->columns[$name]);
    }

    /**
     * Magic method to unset $this->columns[$name].
     *
     * @param  string $name
     * @return void
     */
    public function __unset($name)
    {
        if (isset($this->columns[$name])) {
            unset($this->columns[$name]);
        }
    }

    /**
     * ArrayAccess offsetExists
     *
     * @param  mixed $offset
     * @return boolean
     */
    public function offsetExists($offset)
    {
        return $this->__isset($offset);
    }

    /**
     * ArrayAccess offsetGet
     *
     * @param  mixed $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->__get($offset);
    }

    /**
     * ArrayAccess offsetSet
     *
     * @param  mixed $offset
     * @param  mixed $value
     * @throws Exception
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->__set($offset, $value);
    }

    /**
     * ArrayAccess offsetUnset
     *
     * @param  mixed $offset
     * @throws Exception
     * @return void
     */
    public function offsetUnset($offset)
    {
        $this->__unset($offset);
    }

}