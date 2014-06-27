<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 14-6-26
 * Time: 下午5:17
 */
require_once "Base.class.php";

class Source extends \gamepop\Base {
  const VS = '`t_4399_vs_ptbus`';

  static $VS_ptbus = 'ptbusid';
  static $VS_4399 = '4399id';

  public function getTable($fields) {
    return self::VS;
  }
} 