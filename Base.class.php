<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 14-3-20
 * Time: 下午1:23
 */
namespace gamepop;

require_once "SQLBuilder.class.php";

class Base {
  static $READ;
  static $WRITE;
  static $MEMCACHE;

  const TABLE = '`table`';

  const R_EQUAL = '=';
  const R_NOT_EQUAL = '!=';
  const R_IN = 'IN';
  const R_NOT_IN = 'NOT IN';
  const R_IS = 'IS';
  const R_IS_NOT = 'IS NOT';
  const R_LESS = '<';
  const R_LESS_EQUAL = '<=';
  const R_MORE = '>';
  const R_MORE_EQUAL = '>=';

  const LEFT = 'LEFT';
  const RIGHT = 'RIGHT';

  protected $builder;
  protected $sql;
  protected $sth;
  protected $result;
  protected $cache;
  protected $is_debug;
  protected $has_cache;
  protected $union;
  protected $union_args;

  public function __construct($need_write = false, $need_cache = true, $is_debug = false) {
    $this->is_debug = $is_debug;
    if (!self::$READ) {
      self::$READ = require_once(dirname(__FILE__) . '/pdo_read.php');
    }
    $this->has_cache = $need_cache;
    if ($need_cache && !self::$MEMCACHE) {
      self::$MEMCACHE = require_once(dirname(__FILE__) . '/memcache.php');
    }
    if ($need_write) {
      $this->init_write();
    }
  }

  public function getResult() {
    return $this->result;
  }

  public function init_write() {
    if (!self::$WRITE) {
      self::$WRITE = require_once(dirname(__FILE__) . '/pdo_write.php');
    }
  }

  public function select() {
    $vars = func_get_args();
    $fields = implode(",", array_filter($vars));
    $this->builder = new SQLBuilder(self::$READ);
    $this->builder->select($fields)->from($this->getTable($fields));
    $this->sth = null;
    return $this;
  }
  /**
   * 更新数据
   * @param array $args 下标对应表的字段，值对应值
   * @param string $table 目标表格
   * @return $this
   */
  public function update($args, $table = '') {
    self::init_write();
    $this->builder = new SQLBuilder(self::$WRITE);
    $this->builder->update($args)->on($table ? $table : $this->getTable($args));
    $this->sth = null;
    return $this;
  }

  /**
   * 插入一条数据
   * @param array $args 下标对应字段，值对应值
   * @param string $table 目标表格
   * @return $this
   */
  public function insert($args, $table = '') {
    self::init_write();
    $this->builder = new SQLBuilder(self::$WRITE);
    $this->builder->insert($args)->into($table ? $table : $this->getTable($args));
    $this->sth = null;
    return $this;
  }
  public function delete($table) {
    self::init_write();
    $this->builder = new SQLBuilder(self::$WRITE);
    $this->builder->delete($table);
    $this->sth = null;
    return $this;
  }
  public function from($table) {
    $this->builder->from($table);
    return $this;
  }
  public function where($args, $table = '', $relation = '=', $is_or = false) {
    $this->builder->where($args, $table, $relation, $is_or);
    return $this;
  }
  public function having($args, $relation = '=', $is_or = false) {
    $this->builder->having($args, $relation, $is_or);
    return $this;
  }
  public function search($args, $is_or = false) {
    $this->builder->search($args, $is_or);
    return $this;
  }
  public function group($key, $table = "") {
    $this->builder->group($key, $table);
    return $this;
  }
  public function order($key, $order = 'DESC') {
    $this->builder->order($key, $order);
    return $this;
  }
  public function limit($start, $length = 0) {
    if ($start > 0) {
      $this->builder->limit($start, $length);
    }
    return $this;
  }
  public function join($table, $from, $to, $dir = 'LEFT') {
    $this->builder->join($table, $from, $to, $dir);
    return $this;
  }
  public function union() {
    if (!$this->union) {
      $this->union = array();
      $this->union_args = array();
    }
    $this->union[] = $this->builder->output();
    $this->union_args[] = $this->builder->args;
    $this->builder = null;
  }
  public function execute($debug = false) {
    // 如果是union的话，执行联查的函数
    if ($this->union) {
      $this->execute_union($debug);
      return $this;
    }
    $sql = $this->builder->output();
    $args = $this->builder->args;
    $this->_execute($sql, $args, $this->builder->is_select, $debug);
    return $this;
  }
  public function execute_union($debug = false) {
    // 更新所有的脚标
    $all_args = array();
    foreach ($this->union_args as $index => $args) {
      $sql = $this->union[$index];
      $moved = array();
      foreach ($args as $key => $value) {
        $moved["{$key}__{$index}"] = $value;
        $sql = preg_replace("/$key(?!_)/", "{$key}__{$index}", $sql);
      }
      $this->union[$index] = $sql;
      $all_args = array_merge($all_args, $moved);
    }
    $sql = implode("UNION\n", $this->union);
    $this->_execute($sql, $all_args, true, $debug);
  }
  public function fetch($method, $is_all = false) {
    if (!$this->sth) {
      $this->execute($this->is_debug);
    }
    if ($this->has_cache && $this->cache) {
      header('From: Memcache');
      $cache = $this->cache;
      // 只能保留一次
      $this->cache = null;
      return $cache;
    }
    $result = $is_all ? $this->sth->fetchAll($method) : $this->sth->fetch($method);
    if (!$result && $this->is_debug) {
      $this->debug_info();
    }
    if ($this->has_cache) {
      self::$MEMCACHE->set($this->getCacheKey($this->sql), $result);
    }
    return $result;
  }
  public function fetchAll($method) {
    return $this->fetch($method, true);
  }
  public function lastInsertId() {
    return self::$WRITE->lastInsertId();
  }
  public function debug_info() {
    var_dump($this->sth->errorInfo());
  }

  public function count($key = '', $table = '', $is_distinct = false) {
    return "COUNT(" . ($is_distinct ? 'DISTINCT(' : '') . ($table ? "`$table`." : "") . ($key ? "`$key`" : "'X'") . ($is_distinct ? ')' : '') . ") AS NUM";
  }

  protected function _execute($sql, $args, $is_select, $debug) {
    if ($debug) {
      var_dump($sql);
    }
    // 读取缓存
    if ($this->has_cache && $this->builder->is_select) {
      $cache = self::$MEMCACHE->get($this->getCacheKey($sql));
      if ($cache) {
        $this->cache = $cache;
        return $this;
      }
    }

    $this->sql = $sql;
    $this->sth = $is_select ? self::$READ->prepare($sql) : self::$WRITE->prepare($sql);
    try {
      $this->result = $this->sth->execute($args);
    } catch (\Exception $e) {
      var_dump($this->sth->errorInfo);
      var_dump($e->getMessage());
    }

    if ($this->is_debug || $debug) {
      var_dump($args);
    }
  }

  protected function getTable($fields) {
    return self::TABLE;
  }
  protected function getCacheKey($sql) {
    return $sql . json_encode($this->builder->args);
  }
}