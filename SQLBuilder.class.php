<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 14-7-23
 * Time: 下午2:48
 */

namespace gamepop;

class SQLBuilder {
  const SELECT = "SELECT {{fields}}
    FROM {{tables}}
    WHERE {{conditions}}
    {{havings}}";
  const UPDATE = "UPDATE {{tables}}
    SET {{fields}}
    WHERE {{conditions}}";
  const INSERT = "INSERT INTO {{tables}}
    ({{fields}})
    VALUES ({{values}})";
  const DELETE = "DELETE FROM {{tables}}
    WHERE {{conditions}}";

  public $is_select = false;
  public $args = array();
  private $sql;
  private $fields;
  private $tables;
  private $conditions = array();
  private $havings = array();
  private $orders = array();
  private $key_dict = array();
  private $group_by = '';
  private $limit = '';
  private $template = '';
  private $reg = '/{{(\w+)}}/';

  public function __construct() {
    $this->key_dict = array();
  }

  // --> 生成select
  public function select($fields) {
    $this->sql = null;
    $this->is_select = true;
    $this->template = self::SELECT;
    $this->fields = $fields;
    return $this;
  }
  public function from($table) {
    $this->tables = $table;
    return $this;
  }
  // --> 生成update
  public function update($args) {
    $params = array();
    $conditions = array();
    foreach ($args as $key => $value) {
      $params[] = "`$key`=:$key";
      $conditions[":$key"] = $value;
    }
    $this->sql = null;
    $this->is_select = false;
    $this->fields = implode(", ", $params);
    $this->args = $conditions;
    $this->template = self::UPDATE;
    return $this;
  }
  public function on($table) {
    $this->tables = $table;
    return $this;
  }
  // --> 生成insert
  public function insert($args) {
    $keys = array();
    $values = array();
    $conditions = array();
    foreach ($args as $key => $value) {
      $keys[] = "`$key`";
      $conditions[] = ":$key";
      $values[":$key"] = $value;
    }
    $this->sql = null;
    $this->is_select = false;
    $this->fields = implode(", ", $keys);
    $this->conditions = $conditions;
    $this->args = $values;
    $this->template = self::INSERT;
    return $this;
  }
  public function into($table) {
    $this->tables = $table;
    return $this;
  }
  // --> 生成delete
  public function delete($table) {
    $this->sql = null;
    $this->is_select = false;
    $this->template = self::DELETE;
    $this->tables = $table;
    return $this;
  }
  public function join($table, $from, $to, $dir) {
    $this->tables = $this->tables . " $dir JOIN $table ON " . $this->tables . ".`$from` = $table.`$to`";
    return $this;
  }
  public function where($args, $table = '', $relation = '=', $is_or = false) {
    $this->parse_args($args, $table, $relation, $is_or);
  }
  public function having($args, $relation = '=', $is_or = false) {
    $this->parse_args($args, '', $relation, $is_or, false);
  }
  public function search($args, $is_or = false) {
    if (!$args || count($args) == 0) {
      return $this;
    }
    foreach ($args as $key => $value) {
      $key = $this->get_value_key($key);
      $this->conditions[] = array($is_or, "`$key` LIKE :$key");
      $this->args[$key] = "%$value%";
    }
    return $this;
  }
  public function order($key, $order = "DESC") {
    $this->orders[] = "$key $order";
    return $this;
  }
  public function group($key, $table) {
    if ($key) {
      if (!preg_match('/\([`\w_]+\)/', $key)) {
        $key = "`$key`";
      }
      $this->group_by = "GROUP BY " . ($table ? "$table." : "" ) . "$key\n";
    }
    return $this;
  }
  public function limit($start, $length) {
    $this->limit = "LIMIT $start,$length";
  }
  public function output() {
    // 如果update和delete欠缺条件，就直接抛出异常
    if (preg_match('/update|delete/i', $this->template) && count($this->conditions) == 0) {
      throw new \Exception('UPDATE/DELETE but no where conditions.');
    }
    if ($this->sql) {
      return $this->sql;
    }
    // 四大件
    $sql = preg_replace_callback($this->reg, function ($matches) {
      switch ($matches[1]) {
        case 'conditions':
          if (!$this->conditions) {
            return 1;
          }
          foreach ($this->conditions as $key => $conditions) {
            $this->conditions[$key] = $this->get_condition_string($conditions);
          }
          return implode(' AND ', $this->conditions);
          break;

        case 'fields':
          return $this->fields;
          break;

        case 'tables':
          return $this->tables;
          break;

        case 'values':
          return $this->conditions ? implode(", ", $this->conditions) : '';
          break;

        default:
          return '';
          break;
      }
    }, $this->template);
    // havings
    if (count($this->havings) > 0){
      foreach ($this->havings as $key => $conditions) {
        $this->havings[$key] = $this->get_condition_string($conditions);
      }
      $this->havings = 'HAVING ' . implode(' AND ', $this->havings);
    } else{
      $this->havings = '';
    }
    // orders
    $order_sql = '';
    if (count($this->orders) > 0) {
      $order_sql = "ORDER BY " . implode(', ', $this->orders) . "\n";
    }
    $this->sql = $sql . $this->group_by . $order_sql . $this->havings . $this->limit;
    $this->sql = $this->strip_multi_accent($this->sql);
    return $this->sql;
  }

  /**
   * @param $conditions
   * @return string
   */
  function get_condition_string($conditions) {
    $is_or = $conditions[0];
    $connect = $is_or ? ' OR ' : ' AND ';
    $conditions = $conditions[1];
    $conditions = is_array($conditions) ? implode(" $connect ", $conditions) : $conditions;
    $conditions = $is_or ? "($conditions)" : $conditions;
    return $conditions;
  }

  function get_value_key($key) {
    $new_key = $key;
    $index = 0;
    while (array_key_exists(":$key", $this->args)) {
      $key = "{$new_key}_{$index}";
      $index++;
    }
    return $key;
  }
  /**
   * 用来生成sql中的条件，可以使用多次，后面的条件会覆盖前面的同名条件
   * `key` in (value1, value2 ...) 类型的sql，在prepare的时候，必须对每个值创建单独的占位符
   * @param array $args
   * @param string $table 条件属于哪个表
   * @param bool $relation key与值的关系
   * @param bool $is_or 是or还是and
   * @param bool $is_where 存在于where里还是having里
   */
  private function parse_args($args, $table, $relation = '=', $is_or = false, $is_where = true) {
    if (!is_array($args)) {
      return;
    }
    $conditions = array();
    $values = array();
    foreach ($args as $key => $value) {
      if ($value === null) {
        continue;
      }
      $value_key = $this->get_value_key($this->strip($key));
      if (is_array($value)) {
        if (count($value) == 0) {
          continue;
        }
        $keys = array();
        $count = 0;
        $value = array_unique($value);
        foreach ($value as $single) {
          $keys[] = ":{$value_key}_{$count}";
          $values[":{$value_key}_{$count}"] = $single;
          $count++;
        }
        $keys = implode(",", $keys);
        $conditions[] = ($table ? "`$table`." : "") . "`$key` $relation ($keys)";
      } else if ($relation === Base::R_IS || $relation === Base::R_IS_NOT) {
        $conditions[] = ($table ? "`$table`." : "") . "`$key` $relation NULL";
      } else {
        $conditions[] = ($table ? "`$table`." : "") . "`$key`$relation:$value_key";
        $values[':' . $value_key] = $value;
      }
    }
    if (count($conditions) === 0) {
      return;
    }
    $this->args = array_merge($this->args, $values);
    if ($is_where) {
      $this->conditions[] = array($is_or, $conditions);
    } else {
      $this->havings[] = array($is_or, $conditions);
    }
  }
  private function strip_multi_accent($string) {
    return preg_replace('/`{2,}/', '`', $string);
  }
  private function strip($string) {
    return preg_replace('/[`\.]+/', '', $string);
  }
}