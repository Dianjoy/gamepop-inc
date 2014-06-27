<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 14-3-14
 * Time: 下午2:41
 */
include_once 'Base.class.php';

class Article extends \gamepop\Base {
  const TABLE = '`t_article`';
  const CATEGORY = '`t_article_category`';
  const CATEGORY_IMAGE = '`t_article_category_image`';

  const NORMAL = 0;
  const DELETED = 1;
  const DRAFT = 2;
  const FETCHED = 3;

  static $ALL = "`t_article`.`id`, `guide_name`, `category`, `label`, `source`,
    `topic`, `author`, `t_article`.`icon_path`, `pub_date`, `src_url`, `seq`, `remark`,
    `update_time`, `update_editor`, `is_top`, `is_index`, `t_article`.`status`,
    `content`";
  static $TOP = "`id`, `topic`, `update_time`, `seq`, `is_top`, `icon_path`, `source`, `author`";
  static $DETAIL = "`guide_name`, `category`, `label`, `source`,
    `topic`, `author`, `icon_path`, `content`, `remark`, `pub_date`, `src_url`,
     `seq`, `update_time`, `update_editor`, `t_article`.`status`, `is_top`";
  static $ALL_CATEGORY = "`t_article_category`.`id`, `cate`, `label`";

  public function __construct($need_write = false, $need_cache = true, $is_debug = false) {
    parent::__construct($need_write, $need_cache, $is_debug);
  }

  // overrides parent's method
  public function search($keyword) {
    $this->builder->search('topic', $keyword);
    return $this;
  }

  public function add_category($label) {
    $condition = array('label' => $label);
    // 先判断是否存在
    $id = $this->select('id')
      ->where($condition)
      ->fetch(PDO::FETCH_COLUMN);
    if ($id) {
      return $id;
    }
    // 不存在再创建
    $id = $this->insert($condition)
      ->execute()
      ->lastInsertId();
    return $id;
  }

  public function get_latest_fetched_article_number() {
    require_once ("Game.class.php");
    return $this->select($this->count())
      ->where(array('status' => self::FETCHED), self::TABLE)
      ->join(Game::TABLE, Game::ID, Game::ID, \gamepop\Base::RIGHT)
      ->fetch(PDO::FETCH_COLUMN);
  }

  public function get_unknown_games() {
    require_once ("Game.class.php");
    return $this->select($this->count(Game::ID, self::TABLE), self::TABLE . '.' . Game::ID)
      ->where(array('status' => self::FETCHED), self::TABLE)
      ->having(array('NUM' => 10), \gamepop\Base::R_MORE_EQUAL)
      ->join(Game::TABLE, Game::ID, Game::ID)
      ->group(Game::ID)
      ->fetchALL(PDO::FETCH_COLUMN);
  }

  protected function getTable($fields) {
    if (is_string($fields)) {
      if ($fields == self::$ALL || $fields == self::$DETAIL) {
        return self::TABLE . " LEFT JOIN " . self::CATEGORY . " ON " . self::TABLE . ".`category`=" . self::CATEGORY . ".`id`";
      }
      if ($fields === self::$ALL_CATEGORY) {
        return self::CATEGORY;
      }
      if (strpos($fields, self::$ALL_CATEGORY) !== false) {
        return self::CATEGORY . " RIGHT JOIN " . self::TABLE . " ON " . self::TABLE . ".`category`=" . self::CATEGORY . ".`id`";
      }
    }
    if (is_array($fields)) {
      foreach ($fields as $key => $value) {
        if ($key === 'label' || $key === 'cate') {
          return self::CATEGORY;
        }
      }
    }
    return self::TABLE;
  }
} 