<?php
if(!isset($_GET['map'])) exit;

$_GET['map'] = htmlspecialchars($_GET['map'] , ENT_QUOTES , "UTF-8");
$info = explode(',',$_GET['map']);

?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0,maximum-scale=1.0,user-scalable=0" />
<title>GoogleMap</title>
<style>
      html, body, #map-canvas {
        height: 100%;
        margin: 0px;
        padding: 0px
      }

</style>
<script src="//maps.googleapis.com/maps/api/js?v=3.exp&sensor=false&libraries=places"></script>
<script>
var map;
function initialize() {

//緯度経度を設定する
  var myLatlng = new google.maps.LatLng(<?php echo $info[0]; ?>,<?php echo $info[1]; ?>);

//オプションを設定
  var mapOptions = {
    zoom: 18, //拡大率
    center: myLatlng //緯度経度
  };
  
//GoogleMapを描写
  map = new google.maps.Map(document.getElementById('map-canvas'),mapOptions); 
  
//インフォメーションウインドを設定
  var infowindow = new google.maps.InfoWindow({
      //content: '苫小牧市',
      maxWidth: 200
  });

//緯度経度の位置にマーカーを表示する
  var marker = new google.maps.Marker({
    position: myLatlng,
    map: map,
  });
  
  infowindow.open(map,marker); //インフォウィンドをデフォで表示 *クリックで表示させる場合はコメントアウト
  
//マーカークリックでインフォウィンドを表示
  google.maps.event.addListener(marker, 'click', function() {
    infowindow.open(map,marker);
  });
  
}
google.maps.event.addDomListener(window, 'load', initialize); //ページをロードしたらマップを表示
</script>
</head>

<body>
  <div id="map-canvas"></div>
</body>
</html>
