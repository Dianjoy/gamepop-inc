<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 14-7-11
 * Time: 上午10:43
 */

class MockRedis {
  public function set($key, $value, $expire) {

  }
  public function exists($key) {
    return false;
  }
  public function get($key) {
    return null;
  }
} 