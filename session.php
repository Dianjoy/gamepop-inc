<?php
defined('OPTIONS') or exit();
session_start();

$string = OPTIONS;
$pos_right = strpos($string, ')');
while ($pos_right) {
  $pos_left = strrpos(substr($string, 0, $pos_right), '(');
  $string = substr($string, 0, $pos_left) . result_without_parenthese(substr($string, $pos_left + 1, $pos_right - $pos_left - 1)) . substr($string, $pos_right + 1);
  $pos_right = strpos($string, ')');
}

if (result_without_parenthese($string) == 'false') {
  require(dirname(__FILE__) . '/Template.class.php');
  // 根据HTTP头Accept的内容判断返回何种错误
  if (strpos($_SERVER['HTTP_ACCEPT'], 'application/json') === false) { // 普通页面
    header('Content-type: text/html;charset=UTF-8');
    $tpl = new Template(dirname(__FILE__) . '/../web/template/permission-error.html');
    $html = $tpl->render();
  } else { // Ajax
    header('Content-type: application/json;charset=UTF-8');
    $html = json_encode(array(
      'code' => 100,
      'msg' => '您需要登录已失效，需要重新登录',
    ));
  }
  header("HTTP/1.1 401 Unauthorized");
  exit($html);
}

function result_without_parenthese($str) {
  $result = explode('|', $str);
  foreach ($result as $sub_str) {
    if (result_without_or($sub_str) == 'true') {
      return 'true';
    }
  }
  return 'false';
}

function result_without_or($str) {
  $result = explode('&', $str);
  foreach ($result as $sub_str) {
    if (!is_array($_SESSION['permission']) || (!in_array($sub_str, $_SESSION['permission']) && $sub_str != 'true' || $sub_str == 'false')) {
      return 'false';
    }
  }
  return 'true';
}
?>