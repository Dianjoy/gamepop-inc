<?php
/**
 * Created by PhpStorm.
 * Date: 14-4-1
 * Time: 上午8:50
 * @overview 统一内容输出类
 * @author Meatill <lujia.zhai@dianjoy.com>
 * @since 
 */

class Spokesman {
  public static function say($args, $extra = null) {
    // 判断下是否需要给图片加绝对路径
    if (isset($args['list']) && is_array($args['list'])) {
      foreach ($args['list'] as $key => $item) {
        $args['list'][$key] = self::checkImageUrl($item, $extra);
      }
    }
    $args = self::checkImageUrl($args, $extra);
    header("Content-Type:application/json;charset=UTF-8");
    exit(json_encode($args));
  }

  public static function judge($result, $success, $error, $args = null, $extra = null) {
    header("Content-Type:application/json;charset=UTF-8");
    if ($args) {
      $args = self::checkImageUrl($args, $extra);
    }
    if ($result) {
      echo json_encode(array_merge(array(
        'code' => 0,
        'msg' => $success,
      ), (array)$args));
    } else {
      header("HTTP/1.1 400 Bad Request");
      echo json_encode(array_merge(array(
        'code' => 1,
        'msg' => $error,
      ), (array)$args));
    }
  }

  /**
   * @param bool $is_game 是否读游戏相关，游戏的id是guide_name
   * @return array
   */
  public static function extract($is_game = false) {
    $param = array();
    $url = $_SERVER['PATH_INFO'];
    $arr = array_values(array_filter(explode('/', $url)));
    foreach ($arr as $key => $value) {
      preg_match('/^(category|game|author)(\w+)$/', $value, $matches);
      if (count($matches) > 0) {
        $param[$matches[1] === 'game' ? 'guide_name' : $matches[1]] = $matches[2];
      } else {
        $param[$is_game && $key === 0 ? 'guide_name' : 'id'] = $value;
      }
    }
    return $param;
  }

  public static function toHTML($data, $tpl) {
    require_once "mustache.php";
    $mustache = new Mustache_Engine(array('cache' => '/var/tmp'));
    $html = file_get_contents($tpl);

    header("Content-type: text/html; charset=UTF-8");
    if (isset($data['content'])) {
      $data['content'] = preg_replace('/ src="(?!http)(\/?\w+)/', " src=\"img/image.gif\" class=\"ph\" data-src=\"http://fast-cdn.dianjoy.com/gamepop/$1", $data['content']);
    }
    $html = $mustache->render($html, $data);
    echo $html;
  }

  private static function checkImageUrl($item, $extra = null) {
    $keys = array_merge(array('image', 'icon_path'), (array)$extra);
    foreach ($keys as $key) {
      if (isset($item[$key]) && !file_exists('../../' . $item[$key])) {
        $item[$key] = self::addDomain($item[$key]);
      }
    }
    return $item;
  }
  private static function addDomain($url) {
    $prefix = 'http://r.yxpopo.com/';
    $url = preg_replace('/(\.\/)?upload\//', $prefix, $url);
    return strpos($url, $prefix) === false ? $prefix . $url : $url;
  }
} 