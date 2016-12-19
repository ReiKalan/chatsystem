<?php
/*
 * jQuery CHAT v.1.00
 * 
 * Copyright(C)2014 STUDIO KEY Allright reserved.
 * http://studio-key.com
 * MIT License
 */


mb_language("Japanese");
mb_internal_encoding("UTF-8");
session_start();
error_reporting(0);


/*
 * 管理人の名前
 */
define('ADMIN_NAME','管理人');

/*
 * SQLiteの場所
 * 相対パスで書かれていますが、出来るだけWEB公開ディレクトリを避けて設置し
 * 絶対パスで指定するなどセキュリティに配慮して下さい。
 */
define('SQLITE','../../db/chat.sqlite');

/*
 * 発言制限(秒)
 * ここで指定した秒数の間は次の発言が出来ません。
 */
define('write_limit',3); //0で無制限


/*
 * 初回に読み込むログ数
 * 過去ログ機能が有りますので、あまり多くせず30～50が良いと思います。
 */
define('LEN',30);

// 設定ここまで -------------------------------------------------------------------------------
require_once('omikuzi.php'); //おみくじ設定ファイルを読み込む *内容を変更したい場合は参照して下さい





/******************************************
 * 変数定義とサニタイズ
 ******************************************/
/*
 * POSTされている＆配列であるか確認
 */
if(isset($_POST) AND is_array($_POST)){
  foreach($_POST AS $key=>$str){
    $post[$key] = htmlspecialchars($str , ENT_QUOTES , "UTF-8");
  }
}else{
  return; //POSTされていなければ停止
}

/*
 * IPと送信日時を足してmd5でハッシュにして発言ごとのユニークを作る
 */
  $hash = md5($_SERVER["REMOTE_ADDR"].time());
  
/*
 * 発言色を定義
 */
  if($post['c']){
    $name_color = '#'.$post['c'];
  }else{
    $name_color = '#000000';
  }
  if($post['l']){
    $log_color = '#'.$post['l'];
  }else{
    $log_color = '#000000';
  }


/******************************************
 * modeで処理を分岐
 ******************************************/
  switch($post['mode']){
    case 'db_check':
      $db_error = null;
      $db = dbClass::connect();
      if($db === 'error'){
        $db_error = 'データベースに接続出来ません';
      }else{
        try { 
          $stmt = $db->prepare("SELECT COUNT(*) AS count FROM sqlite_master WHERE TYPE='table' AND name='chat_log'");
          $stmt->execute();
          $check = $stmt->fetch(PDO::FETCH_ASSOC); 
          if(!$check['count']){
              $sql = "CREATE TABLE 'chat_log' ( 'id' INTEGER NOT NULL PRIMARY KEY, 'room_id' TEXT , 'chat_unique' TEXT, 'hash' TEXT, 'time' TEXT, 'chat_name' TEXT, 'str' TEXT, 'remoote_addr' TEXT, 'name_color' TEXT, 'log_color' TEXT, 'chat_type' TEXT, 'log_sort' TEXT, 'created' DATE );";
              $sql .= "CREATE INDEX chat_log_room_id ON 'chat_log'('room_id'); ";
              $stmt = $db->prepare($sql); 
              $stmt->execute();
          }
          $stmt = $db->prepare("SELECT COUNT(*) AS count FROM chat_log WHERE room_id=:room_id "); //この部屋のログ数を確認
          $stmt->execute(array(':room_id'=>$post['room']));
          $row = $stmt->fetch(PDO::FETCH_ASSOC);
          $row['count'] = $row['count']*1;

        } catch (PDOException $err) { 
          $db_error = 'データベーステーブルが不正です';
        }
      } 

      if($db_error !== null){

       header("Content-type: application/xml");
       echo '<?xml version="1.0" encoding="UTF-8" ?> ' . "\n";

       echo '<xml>'."\n"; 
       echo '      <error>'.$db_error.'</error>'."\n"; 
       echo '</xml>'."\n"; 
       exit;
       
      }else{
        
        /*
         * データベース接続とテーブルの確認を終えたら、この部屋のログ数を確認
         * ログがゼロの場合は初期設定OKのログをインサート
         * この処理を行わないと、非同期→リロードが無限ループに陥る可能性有
         * *log_write()は使わない
         */
          if($row['count'] < 1){
              $data = array(
                   ':room_id'      => $post['room']
                  ,':chat_unique'  => 'ADMIN'
                  ,':hash'         => $hash
                  ,':time'         => time()
                  ,':chat_name'    => 'システム'
                  ,':str'          => 'チャットルームが有効になりました'
                  ,':log_sort'     => 'li3'
                  ,':created'      => date('Y-m-d')
              );
              
              $sql = "INSERT INTO 'chat_log' ( 'id' , 'room_id' , 'chat_unique' , 'hash' , 'time' , 'chat_name' , 'str' ,'log_sort' , 'created' )
              VALUES ( NULL , :room_id , :chat_unique , :hash , :time , :chat_name , :str ,:log_sort , :created ) ";
              try { 
                $stmt = $db->prepare($sql);
                $stmt->execute($data);
              } catch (PDOException $err) {
                //return $err->getMessage();
              }
          }
      }
    break;
    
/*
 * ログイン
 */
    case 'login':
      
      $log_str = $post['str'].$post['mes'];
      $data = array(
           ':room_id'      => $post['room']
          ,':chat_unique'  => 'ADMIN'
          ,':hash'         => $hash
          ,':time'         => time()
          ,':chat_name'    => ADMIN_NAME
          ,':str'          => $log_str
          ,':remoote_addr' => $_SERVER["REMOTE_ADDR"]
          ,':name_color'   => $name_color
          ,':log_color'    => $log_color
          ,':chat_type'    => ''
          ,':created'      => date('Y-m-d')
      );
      
        log_write($data,$post['room']);
    break;
/*
 * 発言
 */
    case 'send':
    //発言制限の時間
      if(!$_SESSION['write_limit']) $_SESSION['write_limit'] = time();
      if(time() < $_SESSION['write_limit']){
        write_stop();
        return ;
      }
      $_SESSION['write_limit'] = mktime(date('H'),date('i'),date('s')+write_limit,date('m'),date('d'),date('Y'));
      
      $log_str = $post['str'];
      $data = array(
           ':room_id'      => $post['room']
          ,':chat_unique'  => $_COOKIE['jquery_chat_unique'.$post['room']]
          ,':hash'         => $hash
          ,':time'         => time()
          ,':chat_name'    => $_COOKIE['jquery_chat_name'.$post['room']]
          ,':str'          => $log_str
          ,':remoote_addr' => $_SERVER["REMOTE_ADDR"]
          ,':name_color'   => $name_color
          ,':log_color'    => $log_color
          ,':chat_type'    => ''
          ,':created'      => date('Y-m-d')
      );
      
      
    /*
     * おみくじ発動！
     */
      if($post['kuzi']){
        $data['kuzi'] = 'kuzi';
        $omikuzi = new Omikuzu;
        
        switch($log_str){
          case 'おみくじ':
            $data[':str']  = '['.$_COOKIE['jquery_chat_name'.$post['room']].'さん] ';
            $data[':str'] .= $omikuzi->Nomal();
          break;
          case 'けんこう':
            $data[':str']  = '['.$_COOKIE['jquery_chat_name'.$post['room']].'さん] ';
            $data[':str'] .= $omikuzi->Kenko();
          break;
          case 'れんあい':
            $data[':str']  = '['.$_COOKIE['jquery_chat_name'.$post['room']].'さん] ';
            $data[':str'] .= $omikuzi->Renai();
          break;
        }
        
      }
      
    //発言が空じゃなければ
      if($post['str']){
        log_write($data,$post['room']);
      }
    break; 
    case 'readLog':
      readLog($post['room'],$post['append'],$post['lasthash'],$post['len']);
    break; 
  
/*
 * ログアウト
 */
    case 'logout':
      $log_str = $post['name'].$post['mes'];
      $data = array(
           ':room_id'      => $post['room']
          ,':chat_unique'  => 'LOGOUT'
          ,':hash'         => $hash
          ,':time'         => time()
          ,':chat_name'    => ADMIN_NAME
          ,':str'          => $log_str
          ,':remoote_addr' => $_SERVER["REMOTE_ADDR"]
          ,':name_color'   => ''
          ,':log_color'    => ''
          ,':chat_type'    => ''
          ,':created'      => date('Y-m-d')
      );
      
      
        log_write($data,$post['room']);
    break; 
/*
 * スタンプ
 */
    case 'gostump':
    //発言制限の時間
      if(!$_SESSION['write_limit']) $_SESSION['write_limit'] = time();
      if(time() < $_SESSION['write_limit']){
        write_stop();
        return ;
      }
      $_SESSION['write_limit'] = mktime(date('H'),date('i'),date('s')+write_limit,date('m'),date('d'),date('Y'));
      
      $data = array(
           ':room_id'      => $post['room']
          ,':chat_unique'  => $_COOKIE['jquery_chat_unique'.$post['room']]
          ,':hash'         => $hash
          ,':time'         => time()
          ,':chat_name'    => $_COOKIE['jquery_chat_name'.$post['room']]
          ,':str'          => $post['stump']
          ,':remoote_addr' => $_SERVER["REMOTE_ADDR"]
          ,':name_color'   => $name_color
          ,':log_color'    => $log_color
          ,':chat_type'    => 'STUMP'
          ,':created'      => date('Y-m-d')
      );
      
        log_write($data,$post['room']);
    break; 
/*
 * Googlemap
 */
    case 'gmap':
      $data = array(
           ':room_id'      => $post['room']
          ,':chat_unique'  => $_COOKIE['jquery_chat_unique'.$post['room']]
          ,':hash'         => $hash
          ,':time'         => time()
          ,':chat_name'    => $_COOKIE['jquery_chat_name'.$post['room']]
          ,':str'          => $post['val']
          ,':remoote_addr' => $_SERVER["REMOTE_ADDR"]
          ,':name_color'   => $name_color
          ,':log_color'    => $log_color
          ,':chat_type'    => 'GMAP'
          ,':created'      => date('Y-m-d')
      );

        log_write($data,$post['room']);
    break; 
/*
 * Image file
 */
    case 'file':
      if($post['file']):
        
        $data = array(
             ':room_id'      => $post['room']
            ,':chat_unique'  => $_COOKIE['jquery_chat_unique'.$post['room']]
            ,':hash'         => $hash
            ,':time'         => time()
            ,':chat_name'    => $_COOKIE['jquery_chat_name'.$post['room']]
            ,':str'          => $post['file']
            ,':remoote_addr' => $_SERVER["REMOTE_ADDR"]
            ,':name_color'   => $name_color
            ,':log_color'    => $log_color
            ,':chat_type'    => 'IMG'
            ,':created'      => date('Y-m-d')
        );

        log_write($data,$post['room']);
      endif;
    break; 
    
    
/*
 * おえかき
 */
    case 'draw':
      if($post['file']):
        
        $data = array(
             ':room_id'      => $post['room']
            ,':chat_unique'  => $_COOKIE['jquery_chat_unique'.$post['room']]
            ,':hash'         => $hash
            ,':time'         => time()
            ,':chat_name'    => $_COOKIE['jquery_chat_name'.$post['room']]
            ,':str'          => $post['file']
            ,':remoote_addr' => $_SERVER["REMOTE_ADDR"]
            ,':name_color'   => $name_color
            ,':log_color'    => $log_color
            ,':chat_type'    => 'DRAW'
            ,':created'      => date('Y-m-d')
        );

        log_write($data,$post['room']);
      endif;
    break; 
    
    
    
    case 'newLog':
      newLog();
    break; 
/*
 * stumpのサムネイルを得る
 */
    case 'stump':
      header("Content-type: application/xml");
      echo '<?xml version="1.0" encoding="UTF-8" ?> ' . "\n";
      echo '  <xml>'."\n"; 
      $res_dir = opendir('../../stump/thumbnail/');
        while( $file_name = readdir( $res_dir ) ){
          if($file_name != '.' AND $file_name != '..'){
           echo '  <item>'."\n"; 
           echo '      <stp>'.$file_name.'</stp>'."\n"; 
           echo '  </item>'."\n"; 
          }
        }
        closedir( $res_dir );
        echo '  </xml>'."\n"; 
    break; 
/*
 * リロード
 */
    case 'reload':
      $db = dbClass::connect();
      $reload[':room_id'] = $post['room'];
      $sql = "SELECT 
               MAX(id) AS id  
              ,hash
              ,chat_unique
              FROM chat_log WHERE room_id=:room_id ";
      try { 
        $stmt =  $db->prepare($sql);
        $stmt -> execute($reload);
        $row  =  $stmt->fetch(PDO::FETCH_ASSOC);
      } catch (PDOException $err) {
        //return $err->getMessage();
      }
      
      $flag = false;
      if($_SESSION['new_v_sqlite'] != $row['hash']){
        $flag = true;
      }
      

 header("Content-type: application/xml");
 echo '<?xml version="1.0" encoding="UTF-8" ?> ' . "\n";
 echo '  <xml>'."\n"; 
 echo '      <flag>'.$flag.'</flag>'."\n"; 
 echo '  </xml>'."\n"; 

    break; 
  }

  
/******************************************
 * 定義関数
 ******************************************/
function write_stop(){
  $message = '次の発言は'.write_limit.'秒経過するまで出来ません';
  header("Content-type: application/xml");
  echo '<?xml version="1.0" encoding="UTF-8" ?> ' . "\n";
  echo '<xml>'."\n"; 
  echo '      <limit>'.$message.'</limit>'."\n"; 
  echo '</xml>'."\n";
  exit;
}
  
  
/*
 * 全ログの取得
 */
function all_Log($roomid,$len=null){

  $db = dbClass::connect();
  $data[':room_id'] = $roomid;

  if($len == null){
    $data[':limit']   = LEN;
  }else{
    $data[':limit']   = $len;
  }
  
  $sql = "SELECT * FROM chat_log WHERE room_id=:room_id ORDER BY id DESC LIMIT 0,:limit";
  try { 
    $stmt =  $db->prepare($sql);
    $stmt -> execute($data);
    return  $stmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (PDOException $err) {
    //return $err->getMessage();
  }
}

/*
 * 最新ログだけを取得
 */
function new_Log($roomid,$lasthash){

  $db = dbClass::connect();
  
 // $sql ="SELECT * FROM  chat_log WHERE  room_id = '001' AND  id > '18' ORDER BY id DESC";
  $data[':hash']    = $lasthash;
  $data[':room_id'] = $roomid;
  $sql ="SELECT * FROM  chat_log
          WHERE 
            room_id = :room_id
            AND  id > (SELECT id FROM chat_log WHERE hash = :hash )
             ORDER BY id DESC ";
  try { 
    $stmt =  $db->prepare($sql);
    $stmt -> execute($data);
    return  $stmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (PDOException $err) {
    //return $err->getMessage();
  }
  
  
}


/*
 * ログをXMLに
 */
function readLog($roomid,$append,$lasthash,$len){
  
  $row = array();
  
// 最新ログだけを得る
  if($append){
    if(!$lasthash) return;
    $row = new_Log($roomid,$lasthash);
    $row = array_reverse($row);
  }
// 全てのログを得る
  else{
    $row = all_Log($roomid,$len);
    $row = array_reverse($row);
  }
  
  
  
  
  /*
   * $_SESSION['last_hash']
   * 自分が見た一番新しいログ以降のデータを得る
   */
  
 header("Content-type: application/xml");
 echo '<?xml version="1.0" encoding="UTF-8" ?> ' . "\n";
 echo '<xml>'."\n"; 
 

   foreach($row AS $log){
      if(date('Ymd',$log['time']) === date('Ymd')){
        $date = date('H:i',$log['time']);
      }else{
        $date = date('Y/m/d H:i',$log['time']);
      }
         echo '  <item xml:space="preserve">'."\n"; 
         echo '      <hash>'.$log['hash'].'</hash>'."\n"; 
         echo '      <cls>'.$log['log_sort'].'</cls>'."\n"; 
         echo '      <name>'.$log['chat_name'].'</name>'."\n"; 
         echo '      <log>'.$log['str'].'</log>'."\n"; 
         echo '      <date>'.$date.'</date>'."\n"; 
         echo '      <col1>'.$log['name_color'].'</col1>'."\n"; 
         echo '      <col2>'.$log['log_color'].'</col2>'."\n"; 
         echo '      <img>'.$log['chat_type'].'</img>'."\n"; 
         echo '  </item>'."\n"; 
   }

 
 echo '</xml>'."\n"; 
 
 $db = null;
 
 $last_key = count($row)-1;
 $_SESSION['new_v_sqlite'] = $row[$last_key]['hash'];
 
}


/*
 * ログを書き込む
 */
function log_write($data,$roomid){
  $db = dbClass::connect();
  $all_Log = all_Log($roomid);
  
  //$_SESSION['my_hash'] = $data[':hash']; //自分のチャット
  

  // 管理人発言の場合は li3
    if($data[':chat_unique'] === 'ADMIN' OR $data[':chat_unique'] === 'LOGOUT'){
      $data[':log_sort'] = 'li3';
    }else{
      
     $checkLog = array();
      foreach($all_Log AS $row){
        if($row['chat_unique'] != 'ADMIN') {
          $checkLog[] = $row;
        }
      }

      //管理人発言以外が無ければ li1
        if(!$checkLog){
          $data[':log_sort'] = 'li1';
        }else{
          if($data[':chat_unique'] === $checkLog[0]['chat_unique']){ //前の発言と自分の発言が一緒ならば
            $data[':log_sort'] = $checkLog[0]['log_sort'];
          }else{
            if($checkLog[0]['log_sort'] === 'li1'){
              $data[':log_sort'] = 'li2';
            }else{
              $data[':log_sort'] = 'li1';
            }
          }
        }
    }
    
    if($data['kuzi']){
      $data[':log_sort']    = 'li4';
      $data[':chat_unique'] = 'ADMIN';
      unset($data['kuzi']);
    }
    
    
    $sql = "INSERT INTO 'chat_log' ( 'id' , 'room_id' , 'chat_unique' , 'hash' , 'time' , 'chat_name' , 'str' , 'remoote_addr' , 'name_color' , 'log_color' , 'chat_type' ,'log_sort' , 'created' )
    VALUES ( NULL , :room_id , :chat_unique , :hash , :time , :chat_name , :str , :remoote_addr , :name_color , :log_color , :chat_type , :log_sort , :created ) ";
    try { 
      $stmt = $db->prepare($sql);
      $stmt->execute($data);
    } catch (PDOException $err) {
      //return $err->getMessage();
    }

}

/******************************************
 * データベース接続
 ******************************************/
class dbClass{
    static function connect(){
        try {
            $conn = new PDO("sqlite:".SQLITE);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
          return 'error';
            //return $err->getMessage();
        }
        return $conn; 
    }
}
