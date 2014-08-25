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
  const CATEGORY = '`t_article_category`'; // 咳咳，这个和下面那个刚好反过来，请注意
  const CATEGORY_IMAGE = '`t_article_category_image`';
  const ARTICLE_CATEGORY = '`t_category`';
  const TOP = '`t_article_top`';

  const NORMAL = 0;
  const DELETED = 1;
  const DRAFT = 2;
  const FETCHED = 3;

  static $ALL = "`t_article`.`id`, `guide_name`, `source`, `topic`, `author`, `icon_path`,
    `pub_date`, `src_url`, `remark`, `update_time`, `update_editor`, `t_article`.`status`";
  static $DETAIL = "`guide_name`, `source`, `topic`, `author`, `icon_path`,
    `content`, `remark`, `pub_date`, `src_url`, `status`";
  static $ALL_CATEGORY = "`t_article_category`.`id`, `cate`, `label`, `parent`";
  static $CATEGORY = "`aid`, `cid`, `label`";
  static $TOP = "`aid`, `t_article_top`.`id`, `topic`, `start_time`, `end_time`,
    `t_article_top`.`seq`, `icon_path`, `pub_date`, `source`, `author`";

  public function __construct($need_write = false, $need_cache = true, $is_debug = false) {
    parent::__construct($need_write, $need_cache, $is_debug);
  }

  // overrides parent's method
  public function search($args, $is_or = false) {
    if (is_array($args)) {
      $this->builder->search($args, $is_or);
    } else {
      $this->builder->search(array('topic' => $args), $is_or);
    }
    return $this;
  }

  public function add_category($label) {
    $condition = array('label' => $label);
    // 先判断是否存在
    $id = $this->select('id')
      ->from(self::CATEGORY)
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

  public function get_unknown_games($keyword = '') {
    require_once ("Game.class.php");
    return $this->select($this->count(), self::TABLE . '.' . Game::ID)
      ->where(array('status' => self::FETCHED), self::TABLE)
      ->where(array(Game::ID => 'NULL'), Game::TABLE, \gamepop\Base::R_IS)
      ->search($keyword)
      ->having(array('NUM' => 1), \gamepop\Base::R_MORE_EQUAL)
      ->join(Game::TABLE, Game::ID, Game::ID)
      ->group(Game::ID)
      ->fetchALL(PDO::FETCH_ASSOC);
  }

  /**
   * 取给定文章的附加信息
   * @param $articles
   * @param null $options
   * @return mixed
   */
  public function fetch_meta_data($articles, $options = null) {
    $default = array(
      'category' => true,
      'author' => true,
      'game' => true,
      'top' => true,
    );
    $options = $options ? $options : $default;
    // 取出各种数据
    $ids = array();
    $author = array();
    $editors = array();
    $guide_names = array();
    foreach ($articles as $item) {
      $ids[] = isset($item['aid']) ? $item['aid'] : $item['id'];
      $guide_names[] = $item['guide_name'];
      if ($item['update_editor']) {
        $editors[] = $item['update_editor'];
      }
      if (!$item['source'] && is_numeric($item['author'])) {
        $author[] = $item['author'];
      }
    }
    $guide_names = array_unique($guide_names);
    $editors = array_unique(array_merge($editors, $author));

    // 读取分类
    if ($options['category']) {
      $category = $this->select(Article::$CATEGORY)
        ->where(array('aid' => $ids), '', gamepop\Base::R_IN)
        ->fetchAll(PDO::FETCH_ASSOC);
      $cates = array();
      foreach ($category as $item) {
        $item['id'] = $item['cid'];
        if (isset($cates[$item['aid']])) {
          $cates[$item['aid']][] = $item;
        } else {
          $cates[$item['aid']] = array($item);
        }
      }
    }

    // 读取作者，用作者名取代标记
    if ($options['author'] && count($editors)) {
      require_once "Admin.class.php";
      $admin = new Admin();
      $editors = $admin->select(Admin::$BASE)
        ->where(array('id' => $editors), '', \gamepop\Base::R_IN)
        ->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE);
      foreach ($articles as $key => $article) {
        if ($item['update_editor']) {
          $articles[$key]['update_editor'] = $editors[$article['update_editor']];
        }
        if (!$article['source'] && is_numeric($article['author'])) {
          $articles[$key]['author'] = $editors[$article['author']]['nickname'];
        }
      }
    }

    // 读取关联游戏
    if ($options['game'] && count($guide_names)) {
      require_once "Game.class.php";
      $game = new Game();
      $games = $game->select(Game::$ALL)
        ->where(array('guide_name' => $guide_names), '', \gamepop\Base::R_IN)
        ->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE);
    }

    // 读取置顶状态
    if ($options['top']) {
      $now = date('Y-m-d H:i:s');
      $top = $this->select(Article::$TOP)
        ->where(array('aid' => $ids), '', \gamepop\Base::R_IN)
        ->where(array('status' => 0), Article::TOP)
        ->where(array('end_time' => $now), '', \gamepop\Base::R_MORE_EQUAL)
        ->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE);
    }

    // 补全数据
    $unique_category = $options['category_type'] == 'unique';
    $is_pub_date_short = $options['pub_date_type'] == 'short';
    foreach ($articles as $key => $item) {
      $id = isset($item['aid']) ? $item['aid'] : $item['id'];
      $item['category'] = $unique_category ? (int)$cates[$id][0]['id'] : (array)$cates[$id];
      $item['game_name'] = $games[$item['guide_name']]['game_name'];
      $item['is_top'] = (int)$item['is_top'];
      $item['status'] = (int)$item['status'];
      $item['pub_date'] = $is_pub_date_short ? substr($item['pub_date'], 0, 10) : $item['pub_date'];
      $item['top'] = (int)isset($top[$id]);
      $articles[$key] = $item;
    }

    return $articles;
  }

  /**
   * 设置文章置顶
   * @param $id 文章id
   * @param $is_top 是否置顶
   */
  public function set_article_top($id, $is_top) {
    $pub_date = $this->select('pub_date')
      ->where(array('id' => $id))
      ->fetch(PDO::FETCH_COLUMN);
    // 以上线时间和当前时间较晚者为准
    $now = date('Y-m-d H:i:s');
    $start_date = $pub_date > $now ? $pub_date : $now;
    $end_date = date('Y-m-d H:i:s', strtotime($start_date) + 86400 * 7);
    if ($is_top) {
      $array = array(
        'aid' => $id,
        'create_time' => $now,
        'start_time' => $start_date,
        'end_time' => $end_date,
        'by' => $_SESSION['id'],
      );
      $result = $this->insert($array, Article::TOP)
        ->execute()
        ->getResult();
    } else {
      $result = $this->update(array('status' => 1), Article::TOP)
        ->where(array('aid' => $id))
        ->where(array('end_time' => $now), '', \gamepop\Base::R_MORE_EQUAL)
        ->execute();
    }
    $attr = array('top' => (int)$is_top);
    Spokesman::judge($result, '修改成功', '修改失败', $attr);
    exit();
  }

  /**
   * 取置顶文章
   * @param $guide_name
   * @param $category
   * @return array
   */
  public function get_top_article($guide_name, $category, $order = 'start_time', $limit = 10) {
    $guide_name = is_array($guide_name) ? $guide_name : array($guide_name);
    $category = is_array($category) ? $category : array($category);
    $now = date('Y-m-d');
    $articles = $this->select(self::$ALL)
      ->join(self::ARTICLE_CATEGORY, 'id', 'aid')
      ->join(self::TOP, 'id', 'aid')
      ->where(array('guide_name' => $guide_name), '', \gamepop\Base::R_IN)
      ->where(array('cid' => $category), '', \gamepop\Base::R_IN)
      ->where(array('pub_date' => $now), '', \gamepop\Base::R_LESS_EQUAL)
      ->where(array('start_time' => $now), '', \gamepop\Base::R_LESS_EQUAL)
      ->where(array('end_time' => $now), '', \gamepop\Base::R_MORE_EQUAL)
      ->where(array('status' => 0), self::TABLE)
      ->where(array('status' => 0), self::TOP)
      ->order($order)
      ->limit($limit)
      ->fetchAll(PDO::FETCH_ASSOC);
    $articles = $this->fetch_meta_data($articles, array(
      'category' => true,
      'category_type' => 'unique',
    ));
    return $articles;
  }

  public function update_category($id, $category) {
    $new = explode('|', $category);
    $this->delete(Article::ARTICLE_CATEGORY)
      ->where(array('aid' => $id))
      ->execute();
    $categories = array();
    foreach ($new as $category) {
      $categories[] = array(
        'aid' => $id,
        'cid' => $category,
      );
    }
    $check = $this->insert($categories, Article::ARTICLE_CATEGORY)
      ->execute()
      ->getResult();

    if (!$check) {
      Spokesman::say(array(
        'code' => 1,
        'msg' => '修改分类失败',
      ));
    }

    if (is_array($categories)) {
      $categories = $this->select(Article::$ALL_CATEGORY)
        ->where(array('id' => $new), '', \gamepop\Base::R_IN)
        ->fetchAll(PDO::FETCH_ASSOC);
    }
    return $categories;
  }

  protected function getTable($fields) {
    if (is_string($fields)) {
      if ($fields === self::$ALL_CATEGORY) {
        return self::CATEGORY;
      }
      if (strpos($fields, self::$ALL_CATEGORY) !== false) {
        return self::CATEGORY . " RIGHT JOIN " . self::TABLE . " ON " . self::TABLE . ".`category`=" . self::CATEGORY . ".`id`";
      }
      if ($fields === self::$CATEGORY) {
        return self::CATEGORY . " RIGHT JOIN " . self::ARTICLE_CATEGORY . " ON " . self::CATEGORY . ".`id`=" . self::ARTICLE_CATEGORY . ".`cid`";
      }
      if ($fields === self::$TOP) {
        return self::TOP . " LEFT JOIN " . self::TABLE . " ON " . self::TOP . ".`aid`=" . self::TABLE . ".`id`";
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