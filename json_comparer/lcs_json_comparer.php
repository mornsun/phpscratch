<?php
/***************************************************************************
 * 
 * Copyright (c) 2015 Chauncey. All Rights Reserved
 * 
 **************************************************************************/



/**
 * @file lcs_json_comparer.php
 * @author Chauncey(https://github.com/mornsun/)
 *
 * @brief A script to diff two JSON-formatted protocol files.
 *       It is a general JSON protocol comparer based on LCS (Longest Common Subsequence)
 *       Just after some simple modification with the nested protocol definition array, it can adapt to almost JSON protocol.
 *       Generally, it aims to verify data modification, to avoid unexpected data errors caused by artificial or unartificial reasons
 *
 *       Utilize the test() function to have an insight.
 *
 **/


/**
 * 得到最长公共子序列
 * Get the Longest Common Subsequence of two arrays
 *
 * @param two arrays $x[], $y[], both of whose indexes are zero-based numbering
 * @return $idx[][], $idx[0] list the indexes of common elements in array $x[], $idx[1] list those in $y[].
 *
 */
function get_lcs($x, $y)
{
   $xSize = sizeof($x);
   $ySize = sizeof($y);
   $memo = array(); //[xSize + 1][ySize + 1];
   for($i = 0; $i <= $xSize; $i++) {
      $memo[$i][0] = 0;
   }
   for($j = 0; $j <= $ySize; $j++) {
      $memo[0][$j] = 0;
   }
   // DP
   for ($i = 0; $i < $xSize; $i++) {
      for ($j = 0; $j < $ySize; $j++) {
         if ($x[$i] === $y[$j]) {
            $memo[$i + 1][$j + 1] = $memo[$i][$j] + 1;
         } elseif ($memo[$i][$j + 1] >= $memo[$i + 1][$j]) {
            $memo[$i + 1][$j + 1] = $memo[$i][$j + 1];
         } else {
            $memo[$i + 1][$j + 1] = $memo[$i + 1][$j];
         }
      }
   }
   // Get result: record indexes of the common elements [memo1 in x, memo1 in y, memo2 in x, memo2 in y, ...]
   $lenLCS = $memo[$xSize][$ySize];
   $idx = array();//[lenLCS << 1];
   $k = $lenLCS - 1;
   $i = $xSize;
   $j = $ySize;
   while ($i != 0 && $j != 0) {
      if ($memo[$i][$j] == $memo[$i - 1][$j]) {
         --$i;
      } elseif ($memo[$i][$j] == $memo[$i][$j - 1]) {
         --$j;
      } else {
         $idx[0][$k] = --$i;
         $idx[1][$k--] = --$j;
      }
   }
   return $idx;
}

/**
 * 取得当前毫秒时间
 * Get currently microtime in type of float
 *
 */
function microtime_float()
{
   list($usec, $sec) = explode(" ", microtime());
   return ((float)$usec + (float)$sec);
}

/**
 * As for certain fields contain an intro('category','group') field but the others not('item'), use this function for adaptation
 *
 */
function get_intro_field($field, $desc_dict)
{
   if (isset($desc_dict['intro'])) {
      return $field->{$desc_dict['intro']};
   } else {
      return $field;
   }
}

/**
 * Get id of a specified object
 *
 */
function get_id_field($object, $desc_dict)
{
   $intro = get_intro_field($object, $desc_dict);
   $id_key = $desc_dict['id'];
   return isset($intro->{$id_key}) ? $intro->{$id_key} : null;
}


/**
 * Get description of a specified object
 *
 */
function get_obj_desc($object, $desc_dict)
{
   if (!is_object($object)) {
      return (string)$object;
   }
   $intro = get_intro_field($object, $desc_dict);
   $id_key = $desc_dict['id'];
   $id =  isset($intro->{$id_key}) ? $intro->{$id_key} : null;
   $name_key = $desc_dict['name'];
   $name = isset($intro->{$name_key}) ? $intro->{$name_key} : null;
   return $desc_dict['lang_desc']."[$id:$name]";
}

/**
 * generate a line of output information, including its hierarchy
 *
 */
function gen_output_line($str, $level)
{
   return [0 => $level, 1 => $str];
}

/**
 * Diff an array field with LCS algorithm, like categories, groups ...
 *
 */
function diff_array_field($narray, $oarray, $lang_dict=null, $desc_dict=null, $level=0)
{
   $output = array();
   if (!isset($desc_dict)) {
      $output[] = gen_output_line('FATAL: 没有描述词典!程序员GG有BUG!', $level);
      return $output;
   }
   $nisset = isset($narray);
   $oisset = isset($oarray);
   if (!$nisset || !$oisset) {
      if ($nisset && !$oisset) {
         $output[] = gen_output_line('WARNING: 新版增加数据', $level);
         return $output;
      } elseif (!$nisset && $oisset) {
         $output[] = gen_output_line('WARNING: 新版删除数据', $level);
         return $output;
      } elseif (!$nisset && !$oisset) {
         //$output[] = gen_output_line('WARNING: 新版旧版均无此数据', $level);
         return $output;
      }
   }
   $oa = array();
   $olen = sizeof($oarray);
   for ($k=0; $k<$olen; ++$k) { //ensure the array is zero-based numbering
      $oa[$k] = get_id_field($oarray[$k], $desc_dict);
   }
   $na = array();
   $nlen = sizeof($narray);
   for ($k=0; $k<$nlen; ++$k) {
      $na[$k] = get_id_field($narray[$k], $desc_dict);
   }
   $i_common = get_lcs($na, $oa);
   $len = sizeof($i_common[0]); //lengths of [0] and [1] are identical
   $oofs = $nofs = 0;
   for ($k=0; $k<$len; ++$k) {
      $oi = $oofs;
      $oofs = $i_common[1][$k];
      $ni = $nofs;
      $nofs = $i_common[0][$k];
      for (; $oi<$oofs; ++$oi) { //record deleted elements
         $output[] = gen_output_line('删除了'.get_obj_desc($oarray[$oi], $desc_dict), $level);
      }
      for (; $ni<$nofs; ++$ni) { //record added elements
         $output[] = gen_output_line('增加了'.get_obj_desc($narray[$ni], $desc_dict), $level);
      }
      //explore the details (child nodes) of this common element
      $out = diff_object_field($narray[$nofs], $oarray[$oofs], $lang_dict, $desc_dict, $level+1);
      if ($out != null) {
         $output[] = gen_output_line('in '.get_obj_desc($narray[$nofs], $desc_dict), $level);
         $output = array_merge($output, $out);
      }
      ++$oofs;
      ++$nofs;
   }
   for (; $oofs<$olen; ++$oofs) {
      $output[] = gen_output_line('删除了'.get_obj_desc($oarray[$oofs], $desc_dict), $level);
   }
   for (; $nofs<$nlen; ++$nofs) {
      $intro = get_intro_field($narray[$nofs], $desc_dict);
      $output[] = gen_output_line('增加了'.get_obj_desc($narray[$nofs], $desc_dict), $level);
   }

   return $output;
}

/**
 * Diff an object field, like categories[n], groups[n] ...
 *
 */
function diff_object_field($ndata, $odata, $lang_dict=null, $desc_dict=null, $level=0)
{
   $output = array();
   $nisset = isset($ndata);
   $oisset = isset($odata);
   if (!$nisset || !$oisset) {
      if ($nisset && !$oisset) {
         $output[] = gen_output_line('WARNING: 新版增加数据', $level);
         return $output;
      } elseif (!$nisset && $oisset) {
         $output[] = gen_output_line('WARNING: 新版删除数据', $level);
         return $output;
      } elseif (!$nisset && !$oisset) {
         //$output[] = gen_output_line('FATAL: 新版旧版均无此数据!程序员GG有BUG!', $level);
         return $output;
      }
   }
   foreach ($odata as $k => $nv) { //scan the earlier data and report disappeared fields
      $nv = $ndata->{$k};
      if (isset($ov) && !isset($nv)) {
         $output[] = gen_output_line('WARNING: 字段变化!新版无['.$k.']字段,速速联系管理员确认!', $level);
      }
   }
   foreach ($ndata as $k => $nv) { //scan the newer data and simutaneously compare with the last version
      $ov = $odata->{$k};
      if (isset($nv) && !isset($ov)) {
         $output[] = gen_output_line('WARNING: 字段变化!新版增加['.$k.']字段,速速联系管理员确认!', $level);
         continue;
      }
      if (is_array($nv)) {
         if (!is_array($ov)) {
            $output[] = gen_output_line('WARNING: 字段变化!字段['.$k.']类型由'.gettype($ov).'修改为array,速速联系管理员确认!', $level);
         } elseif (isset($desc_dict) && isset($desc_dict['array'][$k])) { //arrays need to be further inspected
            $out = diff_array_field($nv, $ov, $lang_dict, $desc_dict['array'][$k], $level+1);
            if ($out != null) { //has diff
               $output = array_merge($output, $out);
            }
         } else { //normally for main fields, this circumstance will not occur, so simply judge with equal operation
            if ($nv !== $ov) {
               $output[] = gen_output_line('修改了列表['.$k.']', $level);
            }
         }
      } elseif (is_object($nv)) {
         if (!is_object($ov)) {
            $output[] = gen_output_line('WARNING: 字段变化!字段['.$k.']类型由'.gettype($ov).'修改为object,速速联系管理员确认!', $level);
         } elseif (isset($desc_dict) && isset($desc_dict['objects'][$k])) {
            $out = diff_object_field($nv, $ov, $lang_dict, $desc_dict['objects'][$k], $level+1);
            if ($out != null) { //has diff
               $output = array_merge($output, $out);
            }
         } else { //normally for main fields, this circumstance will not occur, so simply judge with equal operation
            if ($nv !== $ov) {
               $output[] = gen_output_line('修改了对象['.$k.']', $level);
            }
         }
      } else {
         if (is_array($ov)) {
            $output[] = gen_output_line('WARNING: 字段变化!字段['.$k.']类型由array修改为'.gettype($ov).',速速联系管理员确认!', $level);
         } elseif (is_object($ov)) {
            $output[] = gen_output_line('WARNING: 字段变化!字段['.$k.']类型由object修改为'.gettype($ov).',速速联系管理员确认!', $level);
         } elseif ($nv !== $ov) {
            if (isset($lang_dict) && array_key_exists($k, $lang_dict)) {
               if (!empty($lang_dict[$k])) {
                  $output[] = gen_output_line('修改了['.$lang_dict[$k].']原值['.$ov.']修改为['.$nv.']', $level);
               } //else $lang_dict[$k] exists but is empty, ignore it. (ex. volatile field, like version)
            } else {
               //have not been configured, just indicate its raw key
               $output[] = gen_output_line('修改了['.$k.']原值['.$ov.']修改为['.$nv.']', $level);
            }
         }
      }
   }

   return $output;
}

/**
 * Diff two JSON files
 *
 */
function diff_json_files($nfile, $ofile, $lang_dict=null, $desc_dict=null)
{
   $njson = file_get_contents($nfile);
   $ndata = json_decode($njson);
   $ojson = file_get_contents($ofile);
   $odata = json_decode($ojson);

   return diff_object_field($ndata, $odata, $lang_dict, $desc_dict);
}

function test($oldfile, $newfile)
{
   $LANG_DICT = array(
      "category_id" => '分类ID',
      "seq" => '顺序',
      "title" => '名称',
      "category_img_url" => '点击前图片',
      "category_img_select_url" => '点击后图片',
      "focus" => '红点',
      "tie_version" => '支持版本',
      "tie_max_version" => '最大支持版本',
      "channel" => '渠道',
      "version" => null,

      "group_id" => '贴包ID',
      'group_type' => '贴包类型',
      'group_seq' => '贴包顺序',
      'group_name' => '贴包名称',
      'group_author' => '贴包作者',
      'group_desc' => '贴包描述',
      'group_cover' => '贴包封面',
      'group_cover_small' => '贴包图标',
      'group_cover_small_select' => '贴包图标(点击后)',
      'group_top_image' => '贴包顶部图片',
      'group_price' => '贴包价格',
      'group_native' => '贴包是否内置',
      'group_downloaded_status' => '贴包下载状态',
      'group_auto_download' => '贴包自动下载',
      'group_tie_version' => '贴包支持版本',
      'group_tie_max_version' => '贴包最大支持版本',
      'group_channel' => '贴包渠道',
      'group_version' => null,

      'item_id' => '贴贴ID',
      'item_class_id' => '贴贴所属磁力贴贴分类',
      'item_name' => '贴贴名称',
      'item_cover' => '贴贴封面',
      'item_url' => '贴贴地址(图片和播放配置)',
      'item_downloaded_status' => '贴贴下载状态',
      'item_tie_version' => '贴贴支持版本',
      'item_tie_max_version' => '贴贴最大支持版本',
      'item_channel' => '贴贴渠道',
      'item_version' => null,
      'item_hot' => '贴贴红点',
      'item_seq' => '贴贴顺序',

      'class_id' => '磁力贴贴分类分类ID',
      'class_name' => '磁力贴贴分类名称',
      'class_cover_small' => '磁力贴贴分类图标',
      'class_cover_small_select' => '磁力贴贴分类图标(点击后)',
      'class_level_mutex' => '磁力贴贴分类层级',
      'class_anchor' => '磁力贴贴分类默认锚点',
      'class_tie_version' => '磁力贴贴分类支持版本',
      'class_tie_max_version' => '磁力贴贴分类最大支持版本',
      'class_channel' => '磁力贴贴分类渠道',
      'class_seq' => '磁力贴贴分类顺序',
      'class_version' => null,
   );

   $DESC_DICT_MAP = array( 'categories'=> array('lang_desc' => '分类', 'intro'=>'category', 'id'=>'category_id', 'name'=>'title', 'version'=>'version'),
                     'groups'=> array('lang_desc' => '贴包', 'intro'=>'group', 'id'=>'group_id', 'name'=>'group_name', 'version'=>'group_version'),
                     'items'=> array('lang_desc' => '贴贴', 'intro'=>null, 'id'=>'item_id', 'name'=>'item_name', 'version'=>'item_version'),
                     'class'=> array('lang_desc' => '贴贴分类', 'intro'=>null, 'id'=>'class_id', 'name'=>'class_name', 'class_version'=>'item_version'),
                     );

   $HIERARCHICAL_DESC_DICT = array(
         'array' => array(
            'categories'=> array('lang_desc' => '分类', 'intro'=>'category', 'id'=>'category_id', 'name'=>'title', 'version'=>'version',
            'objects' => array('category' => array()),
            'array' => array(
               'groups' => array('lang_desc' => '贴包', 'intro'=>'group', 'id'=>'group_id', 'name'=>'group_name', 'version'=>'group_version',
               'objects' => array('group' => array()),
               'array' => array('items'=> array('lang_desc' => '贴贴', 'intro'=>null, 'id'=>'item_id', 'name'=>'item_name', 'version'=>'item_version'))))),
            'class'=> array('lang_desc' => '贴贴分类', 'intro'=>null, 'id'=>'class_id', 'name'=>'class_name', 'class_version'=>'item_version'),
         ));

   $ojson = file_get_contents($oldfile);
   $odata = json_decode($ojson);
   $njson = file_get_contents($newfile);
   $ndata = json_decode($njson);

   //var_dump($odata);

   $out = diff_object_field($ndata, $odata, $LANG_DICT, $HIERARCHICAL_DESC_DICT, null, 0);
   var_dump($out);
}

test($argv[1], $argv[2]);

?>
