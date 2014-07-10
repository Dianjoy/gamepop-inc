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

function array_pick($array) {
  $args = array_slice(func_get_args(), 1);
  $result = array();
  foreach ($array as $key => $value) {
    if (in_array($key, $args)) {
      $result[$key] = $value;
    }
  }
  return $result;
}