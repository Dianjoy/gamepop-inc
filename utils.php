<?php
/**
 * Created by PhpStorm.
 * User: meathill
 * Date: 14-4-29
 * Time: 下午4:22
 */
function array_omit($array) {
  $args = array_slice(func_get_args(), 1);
  foreach ($args as $key) {
    if (array_key_exists($key, $array)) {
      unset($array[$key]);
    }
  }
  return $array;
}