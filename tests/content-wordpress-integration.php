<?php
declare(strict_types=1);
$root = getenv('MHL_TEST_WP_PATH') ?: '';
$config = $root.'/wp-config.php';
if (!is_file($config) || !str_contains(file_get_contents($config), "'integration_wp'") || !str_contains(file_get_contents($config), "'127.0.0.1:")) {
    throw new RuntimeException('Refusing non-isolated WordPress.');
}
$_SERVER['HTTP_HOST']='127.0.0.1'; $_SERVER['REQUEST_METHOD']='GET'; $_SERVER['REQUEST_URI']='/';
require $root.'/wp-load.php';
require_once $root.'/wp-content/plugins/miloslavhub-live/miloslavhub-live.php';
MHL_Core::init(); MHL_Core::register_content_types();
$checks=0; $created=array();
function transfer_check(bool $ok, string $message): void { $GLOBALS['checks']++; if (!$ok) { throw new RuntimeException($message); } }
function transfer_reject(callable $fn, string $message): void {
    try { $fn(); } catch (RuntimeException $e) { transfer_check(true,$message); return; }
    throw new RuntimeException('Accepted invalid input: '.$message);
}
function transfer_count(): int { global $wpdb; return (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('mhl_subject','mhl_lecture','mhl_question')"); }
$admin=get_user_by('login','integration_admin'); wp_set_current_user($admin->ID);
$sid=wp_insert_post(array('post_type'=>'mhl_subject','post_status'=>'publish','post_title'=>'Přenos: syntetický předmět')); $created[]=$sid;
update_post_meta($sid,'_mhl_teachers',array(array('name'=>'PRIVATE-NAME','email'=>'private@example.invalid')));
update_post_meta($sid,'_mhl_projection_token','PRIVATE-TOKEN'); update_post_meta($sid,'_mhl_custom_logo_url','https://example.invalid/?token=PRIVATE-URL');
$qids=array();
foreach (array('Kvíz s lomítkem \\ a apostrofem \'?', 'Anonymní anketa?') as $i=>$title) {
    $qid=wp_insert_post(wp_slash(array('post_type'=>'mhl_question','post_status'=>'publish','post_title'=>$title))); $created[]=$qid; $qids[]=$qid;
    update_post_meta($qid,'_mhl_options',wp_slash(array('Česká odpověď \\', 'English answer')));
    if (!$i) { update_post_meta($qid,'_mhl_correct_index',0); }
    update_post_meta($qid,'_mhl_multiplier',1.5); update_post_meta($qid,'_mhl_time_limit',35);
    update_post_meta($qid,'_mhl_async_enabled',1); update_post_meta($qid,'_mhl_async_end','2099-01-01T00:00');
    update_post_meta($qid,'_mhl_unrecognized_secret','PRIVATE-META');
}
$lids=array();
foreach (array(array($qids[1],$qids[0]),array($qids[0])) as $i=>$refs) {
    $lid=wp_insert_post(array('post_type'=>'mhl_lecture','post_status'=>'publish','post_title'=>'Přednáška '.($i+1))); $created[]=$lid; $lids[]=$lid;
    update_post_meta($lid,'_mhl_subject_id',$sid); update_post_meta($lid,'_mhl_question_ids',$refs); update_post_meta($lid,'_mhl_gamification',1);
}
try {
    $bundle=MHL_Content_Transfer::export_subject($sid); $json=wp_json_encode($bundle,JSON_UNESCAPED_UNICODE);
    transfer_check(count($bundle['questions'])===2 && count($bundle['lectures'])===2,'Shared question exported once');
    transfer_check($bundle['lectures'][0]['question_ids']===array('q1','q2') && $bundle['lectures'][1]['question_ids']===array('q2'),'Question order and shared reference preserved');
    transfer_check(!str_contains($json,'PRIVATE-') && !str_contains($json,'private@example') && !str_contains($json,'2099'),'No people, tokens, links, extra metadata or deadline');
    transfer_check(!str_contains($json,'permanent_slug') && !str_contains($json,'subject_id'),'No production identifiers');
    transfer_check(MHL_Content_Transfer::validate_json($json)===$bundle,'Portable validation round trip');
    wp_set_current_user(0); transfer_reject(fn()=>MHL_Content_Transfer::export_subject($sid),'Anonymous export denied');
    transfer_reject(fn()=>MHL_Content_Transfer::preview($json),'Anonymous preview denied');
    $subscriber=wp_create_user('content_subscriber',bin2hex(random_bytes(12)),'content@example.invalid');
    wp_set_current_user($subscriber); transfer_reject(fn()=>MHL_Content_Transfer::export_subject($sid),'Subscriber export denied');
    transfer_reject(fn()=>MHL_Content_Transfer::preview($json),'Subscriber import denied');
    wp_set_current_user($admin->ID);
    $cap_filter=static function($caps,$cap,$user,$args) use($qids) { return $cap==='edit_post' && ($args[0]??0)===$qids[0] ? array('do_not_allow') : $caps; };
    add_filter('map_meta_cap',$cap_filter,20,4); transfer_reject(fn()=>MHL_Content_Transfer::export_subject($sid),'Question edit permission checked'); remove_filter('map_meta_cap',$cap_filter,20);
    $before=transfer_count(); $preview=MHL_Content_Transfer::preview($json);
    transfer_check(transfer_count()===$before,'Preview never creates posts');
    transfer_reject(fn()=>MHL_Content_Transfer::confirm('wrong-token'),'Invalid confirmation rejected');
    $admin2=wp_create_user('content_admin2',bin2hex(random_bytes(12)),'content2@example.invalid'); (new WP_User($admin2))->set_role('administrator');
    wp_set_current_user($admin2); transfer_reject(fn()=>MHL_Content_Transfer::confirm($preview['token']),'Preview is bound to current user'); wp_set_current_user($admin->ID);
    $old_slug=MHL_Core::permanent_slug($sid); $result=MHL_Content_Transfer::confirm($preview['token']); $created=array_merge($created,$result['created_ids']);
    transfer_check(count($result['created_ids'])===5 && transfer_count()===$before+5,'Exactly one new subject, two questions and two lectures');
    foreach ($result['created_ids'] as $id) { transfer_check(get_post($id)->post_status==='draft','Imported posts remain private drafts'); }
    transfer_check(MHL_Core::permanent_slug($sid)===$old_slug && MHL_Core::permanent_slug($result['subject_id'])!==$old_slug,'Source QR unchanged, copy uses new QR');
    $copy=MHL_Content_Transfer::export_subject($result['subject_id']); transfer_check($copy===$bundle,'Re-export equals original portable content');
    $copyl=array_values(array_filter($result['created_ids'],static fn($id)=>get_post_type($id)==='mhl_lecture'));
    ob_start(); MHL_Admin::lecture_meta_box(get_post($copyl[0])); $editor=ob_get_clean();
    transfer_check(str_contains($editor,'value="'.$result['subject_id'].'"  selected=') || str_contains($editor,'value="'.$result['subject_id'].'" selected='),'Draft subject remains selectable in imported lecture');
    foreach (MHL_Core::get_lecture_question_ids($copyl[0]) as $qid) { transfer_check(str_contains($editor,'value="'.$qid.'" checked'),'Draft question stays checked when reviewing lecture'); }
    $copyq=array_values(array_filter($result['created_ids'],static fn($id)=>get_post_type($id)==='mhl_question'));
    transfer_check(get_the_title($copyq[1])===get_the_title($qids[0]),'Backslashes and apostrophes survive WordPress writes');
    transfer_check(!get_post_meta($copyq[0],'_mhl_async_enabled',true),'Imported async poll stays off');
    transfer_check(get_post_meta($result['subject_id'],'_mhl_teachers',true)==='' && get_post_meta($result['subject_id'],'_mhl_projection_token',true)==='','Private subject metadata not imported');
    transfer_reject(fn()=>MHL_Content_Transfer::confirm($preview['token']),'Repeated confirmation cannot duplicate content');
    $preview=MHL_Content_Transfer::preview($json); delete_transient('mhl_content_pending_'.$admin->ID); transfer_reject(fn()=>MHL_Content_Transfer::confirm($preview['token']),'Expired preview rejected');
    add_option('mhl_content_lock_'.$admin->ID,time(),'',false); transfer_reject(fn()=>MHL_Content_Transfer::preview($json),'Concurrent operation locked'); delete_option('mhl_content_lock_'.$admin->ID);
    foreach (array('version','unknown','duplicate','dangling','duplicate_ref','index','type','deep','large','unreferenced','html','id','range','options') as $case) {
        $bad=$bundle;
        switch($case) {
            case 'version': $bad['format_version']=2; break;
            case 'unknown': $bad['subject']['settings']['projection_token']='secret'; break;
            case 'duplicate': $bad['questions'][]=$bad['questions'][0]; break;
            case 'dangling': $bad['lectures'][0]['question_ids'][]='q999'; break;
            case 'duplicate_ref': $bad['lectures'][0]['question_ids'][]='q1'; break;
            case 'index': $bad['questions'][0]['correct_index']=100; break;
            case 'type': $bad['questions'][0]['settings']['poll_points']='1'; break;
            case 'id': $bad['questions'][0]['id']='../../evil'; break;
            case 'range': $bad['questions'][0]['settings']['time_limit']=2; break;
            case 'options': $bad['questions'][0]['options']=array('only'); break;
            case 'unreferenced': $bad['questions'][]=array_replace($bad['questions'][0],array('id'=>'q99')); break;
            case 'html': $bad['questions'][0]['title']='<script></script>'; break;
        }
        $badjson=$case==='large'?str_repeat(' ',MHL_Content_Transfer::MAX_BYTES+1):($case==='deep'?str_repeat('[',40).'0'.str_repeat(']',40):wp_json_encode($bad));
        transfer_reject(fn()=>MHL_Content_Transfer::validate_json($badjson),'Strict validation: '.$case);
    }
    transfer_reject(fn()=>MHL_Content_Transfer::validate_json('{broken'),'Malformed JSON rejected');
    $bad=$bundle; $bad['questions'][0]['title']='<img src=x onerror=alert(1)>Safe title';
    transfer_check(MHL_Content_Transfer::validate_json(wp_json_encode($bad))['questions'][0]['title']==='Safe title','Imported markup sanitized');
    $before=transfer_count(); $preview=MHL_Content_Transfer::preview($json);
    $fail_meta=static fn($check,$id,$key)=>$key==='_mhl_options'?false:$check;
    add_filter('add_post_metadata',$fail_meta,20,3); transfer_reject(fn()=>MHL_Content_Transfer::confirm($preview['token']),'Metadata failure rolls back'); remove_filter('add_post_metadata',$fail_meta,20);
    transfer_check(transfer_count()===$before && get_post($sid)->post_status==='publish','Rollback removes only new posts');
    $preview=MHL_Content_Transfer::preview($json);
    $fail_post=static fn($empty,$post)=>($post['post_type']??'')==='mhl_lecture'?true:$empty;
    add_filter('wp_insert_post_empty_content',$fail_post,20,2); transfer_reject(fn()=>MHL_Content_Transfer::confirm($preview['token']),'Insert failure rolls back'); remove_filter('wp_insert_post_empty_content',$fail_post,20);
    transfer_check(transfer_count()===$before,'Insert rollback leaves no partial content');
    update_option('mhl_content_browser_fixture',array('subject_id'=>$sid,'bundle'=>$bundle),false);
    // The browser uses this synthetic subject. Retain it, remove imported test copies only.
    foreach ($result['created_ids'] as $id) { wp_delete_post($id,true); }
    echo wp_json_encode(array('status'=>'passed','checks'=>$checks,'production_data_used'=>false,'paid_api_calls'=>0));
} finally {
    delete_option('mhl_content_lock_'.$admin->ID); delete_transient('mhl_content_pending_'.$admin->ID);
}
