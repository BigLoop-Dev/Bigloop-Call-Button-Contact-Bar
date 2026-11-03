<?php
/**
 * Integration test for the visual builder backend (Phase 4): live preview rendering and design presets.
 * Usage: php tests/integration/builder.php /path/to/wordpress   (run widgets.php first so actions exist)
 *
 * @package Bigloop\CallButton
 */
// phpcs:disable
$_SERVER['HTTP_HOST']='localhost';$_SERVER['REQUEST_URI']='/';require ( $argv[1] ?? '' ) . '/wp-load.php';
$fail=0;$pass=0;function ok($c,$l){global $fail,$pass; if($c){$pass++;echo "  ✓ $l\n";}else{$fail++;echo "  ✗ $l\n";}}
wp_set_current_user(1); do_action('rest_api_init', rest_get_server());
function r($m,$p,$b=null){$q=new WP_REST_Request($m,'/bigloop-call-button/v1'.$p); if($b!==null){$q->set_header('content-type','application/json');$q->set_body(wp_json_encode($b));} $res=rest_do_request($q); return [$res->get_status(), rest_get_server()->response_to_data($res,false)];}
$acts=r('GET','/actions')[1]['items']; $ids=array_map(fn($a)=>['id'=>$a['id']],$acts);
[$s,$p]=r('POST','/widgets/preview',['title'=>'Draft','config'=>['type'=>'menu','actions'=>$ids,'appearance'=>['shape'=>'squircle','size'=>60,'size_mobile'=>48,'style'=>'gradient','gradient_to'=>'#ff0000','border_width'=>2],'typography'=>['font'=>'custom','custom_font'=>'Inter','size'=>16,'size_mobile'=>13,'transform'=>'uppercase'],'effects'=>['entrance'=>'bounce','attention'=>'shake'],'position'=>['tablet_custom'=>true,'tablet'=>['anchor'=>'bottom-left','offset_x'=>10,'offset_y'=>10]]]]);
ok(200===$s && false===$p['empty'] && strlen($p['html'])>500 && empty((array)$p['errors']),'draft preview renders');
foreach(['--blcb-sq-fab:path(','linear-gradient(135deg','"Inter"','--blcb-tt:uppercase','inset 0 0 0 2px','--blcb-size:48px','--blcb-fs:13px'] as $n) ok(strpos($p['css'],$n)!==false,"css has $n");
foreach(['blcb-enter--bounce','blcb-attn--shake','blcb-style--gradient','blcb-rotate'] as $n) ok(strpos($p['html'],$n)!==false,"html has $n");
ok((bool)preg_match('/@media \(min-width:768px\) and \(max-width:1024px\)\{#blcb-w-0\{top:auto;bottom:auto;left:auto;right:auto;transform:none;bottom:calc\(10px[^}]*left:calc\(10px/',$p['css']),'tablet custom position');
[$s,$p]=r('POST','/widgets/preview',['config'=>['type'=>'menu','actions'=>[], 'appearance'=>['size'=>999]]]); ok(true===$p['empty'] && isset(((array)$p['errors'])['appearance.size']),'empty draft reports errors without failing');
[$s,$l]=r('GET','/presets'); ok(200===$s && count(array_filter($l,fn($x)=>$x['builtin']))===10,'10 built-in presets listed');
[$s,$c]=r('POST','/presets/builtin:dark-modern/duplicate'); ok(201===$s && 'Dark Modern (copy)'===$c['name'],'built-in preset duplicated');
[$s,$e]=r('POST','/presets',['name'=>'','config'=>['appearance'=>['size'=>5],'evil'=>['x'=>1]]]); ok(400===$s && isset($e['data']['errors']['name'],$e['data']['errors']['appearance.size']),'invalid preset rejected');
[$s,$n]=r('POST','/presets',['name'=>'Mine','config'=>['appearance'=>['shape'=>'pill','bg'=>'#123456'],'position'=>['desktop'=>['anchor'=>'top-left']]]]); ok(201===$s && ['appearance'=>['shape'=>'pill','bg'=>'#123456']]===$n['config'],'custom preset keeps only style keys');
ok(200===r('DELETE','/presets/'.$n['id'])[0] && 200===r('DELETE','/presets/'.$c['id'])[0],'custom presets deleted');
// existing schema-1 widget upgraded on read
$w=r('GET','/widgets')[1]['items'][0]; ok($w['config']['schema']>=2 && isset($w['config']['typography']),'stored widgets read as schema 2');
echo "\n$pass passed, $fail failed\n"; exit($fail?1:0);
