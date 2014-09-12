<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 14-3-11
 * Time: 上午11:24
 */
include_once 'Base.class.php';

class Game extends \gamepop\Base {
  const TABLE = '`t_game`';
  const MIDDLE = '`m_pack_guide`';
  const APK_INFO = '`t_app_info`';
  const SLIDE = '`t_game_slide`';
  const HOMEPAGE_NAV = '`t_article_category_image`';
  const OUTSIDE = '`o_game_user`';
  const TAGS = '`m_game_tags`';
  const PACK = '`m_pack_guide`';
  const POSTER = '`t_game_poster`';

  // t_game.status
  const NORMAL = 0;
  const DELETED = 1;
  const FETCHED = 3;

  const ID = 'guide_name';

  static $BASE = "`guide_name`, `game_name`, `icon_path`";
  static $ALL = "`guide_name`, `game_name`, `game_desc`, `update_time`, `icon_path`,
   `os_android`, `os_ios`, `hot`, `tags`";
  static $SLIDE = "`id`, `image`, `link`, `seq`";
  static $HOMEPAGE_NAV = "`category`, `id`, `guide_name`, `image`, `seq`, `status`, `order_by`";
  static $OUTSIDE = "`id`, `guide_name`, `user_id`, `score`";
  static $LATEST = "`t_game`.`guide_name`, `game_name`, `game_desc`, `t_game`.`icon_path`,
   `os_android`, `os_ios`";
  static $TAGS = "`id`, `tag`";
  static $POSTER = "`t_game_poster`.`id`, `t_game`.`guide_name`, `game_name`, `icon_path`, `poster`";

  static $ORDER_HOT = "hot";

  public function __construct($need_write = false, $need_cache = true, $is_debug = false) {
    parent::__construct($need_write, $need_cache, $is_debug);
  }

  public function search($args, $is_or = false) {
    if (is_array($args)) {
      $this->builder->search($args, $is_or);
    } else {
      $this->builder->search(array('game_name' => $args));
    }
    return $this;
  }

  public function get_hot_games($keyword, $start, $pagesize) {
    require_once "Admin.class.php";

    $number = $this->select($this->count())
      ->join(self::OUTSIDE, self::ID, self::ID)
      ->join(self::POSTER, self::ID, self::ID)
      ->where(array(
        'user_id' => Admin::$VIP,
        'status' => 0,
      ))
      ->search($keyword)
      ->fetch(PDO::FETCH_COLUMN);

    $games = $this->select(Game::$POSTER)
      ->join(self::OUTSIDE, self::ID, self::ID)
      ->join(self::POSTER, self::ID, self::ID)
      ->where(array(
        'user_id' => Admin::$VIP,
        'status' => 0,
      ))
      ->search($keyword)
      ->order('hot')
      ->limit($start, $pagesize)
      ->fetchAll(PDO::FETCH_ASSOC);

    return array(
      'total' => $number,
      'list' => $games,
    );
  }


  protected function getTable($fields) {
    if ($fields === self::$ALL) {
      return self::TABLE;
    }
    if ($fields === self::$SLIDE) {
      return self::SLIDE;
    }
    if ($fields === self::$HOMEPAGE_NAV) {
      return self::HOMEPAGE_NAV;
    }
    if ($fields === self::$OUTSIDE) {
      return self::OUTSIDE;
    }
    if ($fields === self::$TAGS) {
      return self::TAGS;
    }
    if (is_array($fields)) {
      foreach ($fields as $key => $value) {
        if ($key === 'link' || $key === 'image') {
          return self::SLIDE;
        }
        if ($key === 'user_id') {
          return self::OUTSIDE;
        }
      }
    }
    return self::TABLE;
  }
}