<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 14-6-6
 * Time: 下午5:45
 */
require_once "Base.class.php";

class App extends \gamepop\Base {
  const HOMEPAGE = '`t_app_homepage`';

  const NORMAL = 0;

  static $HOMEPAGE = '`id`, `guide_name`, `link`, `big_pic`, `logo`, `logo_width`,
    `create_time`, `online_time`, `seq`, `status`';

  protected function getTable($field = '') {
    if ($field === self::$HOMEPAGE) {
      return self::HOMEPAGE;
    }
    return self::HOMEPAGE;
  }
} 