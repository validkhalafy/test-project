<?php
define('ABSPATH','/'); define('AET_URL','/'); define('AET_VERSION','1'); define('HOUR_IN_SECONDS',3600);
function esc_attr($s){return htmlspecialchars((string)$s,ENT_QUOTES);} function esc_html($s){return htmlspecialchars((string)$s,ENT_QUOTES);} function esc_url($s){return htmlspecialchars((string)$s,ENT_QUOTES);}
function esc_url_raw($u){return preg_match('#^https?://#i',$u)?$u:'';} function sanitize_text_field($s){return trim(strip_tags((string)$s));} function wp_json_encode($v,$f=0){return json_encode($v,$f);} function wp_parse_url($u){return parse_url($u);}
function add_action(...$a){} function add_shortcode(...$a){}
require dirname(__DIR__, 2) . '/includes/class-media-gallery.php';
$items=Artiey_AET_Media_Gallery::normalize_items([
 ['type'=>'image','src'=>'https://e.test/a.jpg','title'=>'اثر اول','caption'=>'توضیح اول'],
 ['type'=>'video','video_url'=>'https://youtu.be/dQw4w9WgXcQ','title'=>'ویدیو یوتیوب'],
 ['type'=>'video','video_url'=>'https://example.test/f.mp4','poster'=>'https://e.test/p.jpg','title'=>'ویدیو فایل'],
 ['type'=>'image','src'=>'https://e.test/b.jpg','title'=>'اثر چهارم'],
]);
echo Artiey_AET_Media_Gallery::render($items,['layout'=>'scroller','lightbox'=>true,'arrows'=>true,'dots'=>true,'autoplay'=>false,'loop'=>true,'id'=>'t']);
