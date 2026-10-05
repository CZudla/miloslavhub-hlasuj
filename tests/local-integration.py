"""Build disposable loopback WordPress/MariaDB, run integrations and stop both servers.

Use unpacked local runtimes. No downloads, production data or paid provider requests.
The destination must not exist; on Windows use a short path outside OneDrive.
"""
import argparse
import json
import os
from pathlib import Path
import secrets
import shutil
import socket
import subprocess
import sys
import time
import urllib.request

parser=argparse.ArgumentParser()
parser.add_argument('--root',type=Path,required=True)
parser.add_argument('--wordpress-source',type=Path,required=True)
parser.add_argument('--mariadb-dir',type=Path,required=True)
parser.add_argument('--php',default='C:/php84/php.exe')
parser.add_argument('--browser',action='store_true')
parser.add_argument('--load',action='store_true')
args=parser.parse_args()
repo=Path(__file__).resolve().parents[1]
root=args.root.resolve();source=args.wordpress_source.resolve();database=args.mariadb_dir.resolve()
if root.exists():raise RuntimeError('Refusing to overwrite or reuse an existing integration directory')
if not (source/'wp-includes/version.php').is_file():raise RuntimeError('Unpacked WordPress required')
if not (database/'bin/mariadbd.exe').is_file():raise RuntimeError('Unpacked Windows MariaDB required')
root.mkdir(parents=True);site=root/'wordpress';data=root/'synthetic-data'
hidden=getattr(subprocess,'CREATE_NO_WINDOW',0)
php=[args.php,'-d','extension=mysqli']
report={'status':'running','production_data_used':False,'paid_api_calls':0,'external_http_blocked':True}
runtime=repo/'runtime';runtime.mkdir(exist_ok=True)
def run(command,**kwargs):
    result=subprocess.run([str(v) for v in command],capture_output=True,creationflags=hidden,**kwargs)
    if result.returncode:raise RuntimeError(result.stdout.decode('utf8','replace')+result.stderr.decode('utf8','replace'))
    return result.stdout.decode('utf8','replace')
def port():
    with socket.socket() as sock:sock.bind(('127.0.0.1',0));return sock.getsockname()[1]
def literal(value):return "'"+str(value).replace('\\','/').replace("'","\\'")+"'"
dbport=port();webport=port();password=secrets.token_hex(24);admin_password=secrets.token_hex(24)
base=f'http://127.0.0.1:{webport}'
# Only WordPress distribution files are copied. Site content and configuration are newly generated.
shutil.copytree(source,site,ignore=shutil.ignore_patterns('wp-config.php','wp-content','.htaccess'))
(site/'wp-content/plugins').mkdir(parents=True)
shutil.copytree(repo/'wordpress/miloslavhub-live',site/'wp-content/plugins/miloslavhub-live')
run([database/'bin/mysql_install_db.exe',f'--datadir={data}',f'--password={password}',f'--port={dbport}'])
client=root/'synthetic-client.ini';client.write_text(f'[client]\nhost=127.0.0.1\nport={dbport}\nuser=root\npassword={password}\n',encoding='utf8')
constants={'DB_NAME':'integration_wp','DB_USER':'root','DB_PASSWORD':password,'DB_HOST':f'127.0.0.1:{dbport}','DB_CHARSET':'utf8mb4','DB_COLLATE':'',
    'MHL_LIVE_DB_NAME':'integration_votes','MHL_LIVE_DB_USER':'root','MHL_LIVE_DB_PASSWORD':password,'MHL_LIVE_DB_HOST':f'127.0.0.1:{dbport}',
    'WP_HOME':base,'WP_SITEURL':base,'MHL_OPENAI_API_KEY':'synthetic-key-never-sent'}
for name in ['AUTH_KEY','SECURE_AUTH_KEY','LOGGED_IN_KEY','NONCE_KEY','AUTH_SALT','SECURE_AUTH_SALT','LOGGED_IN_SALT','NONCE_SALT']:constants[name]=secrets.token_hex(24)
(site/'wp-config.php').write_text("<?php\n"+'\n'.join(f'define({literal(k)},{literal(v)});' for k,v in constants.items())+
    "\ndefine('MHL_AI_ENABLED',true);\ndefine('DISABLE_WP_CRON',true);\ndefine('WP_HTTP_BLOCK_EXTERNAL',true);\ndefine('AUTOMATIC_UPDATER_DISABLED',true);\n$table_prefix='wp_';\nif(!defined('ABSPATH')){define('ABSPATH',__DIR__.'/');}\nrequire_once ABSPATH.'wp-settings.php';\n",encoding='utf8')
mu=site/'wp-content/mu-plugins';mu.mkdir()
(mu/'disable-outbound.php').write_text("<?php add_filter('pre_http_request',static fn()=>new WP_Error('blocked','All external HTTP disabled.'),1); add_filter('pre_wp_mail','__return_true');",encoding='utf8')
(mu/'browser-fixture.php').write_text("""<?php
if(PHP_SAPI!=='cli-server'){return;}
add_filter('pre_http_request',static function($pre,$args,$url){
    if($url!=='https://api.openai.com/v1/responses'){return new WP_Error('blocked','External HTTP disabled.');}
    update_option('mhl_browser_fixture_calls',1+(int)get_option('mhl_browser_fixture_calls'));
    $payload=json_decode($args['body'],true);$input=json_decode($payload['input'],true);
    $translation=$input['operation']==='translate';
    $output=['title'=>$translation?'Translated synthetic question?':'Upravená syntetická otázka?',
        'options'=>array_map(static fn($text)=>$translation&&$text!==''?'Translated '.$text:$text,$input['options'])];
    return ['headers'=>[],'response'=>['code'=>200],'body'=>wp_json_encode(['status'=>'completed','output'=>[['type'=>'message','content'=>[['type'=>'output_text','text'=>wp_json_encode($output)]]]]])];
},20,3);
""",encoding='utf8')
log=(root/'database.log').open('wb');web_log=None;web=None
server=subprocess.Popen([str(database/'bin/mariadbd.exe'),'--no-defaults',f'--basedir={database}',f'--datadir={data}',f'--port={dbport}','--bind-address=127.0.0.1','--console'],stdout=log,stderr=log,creationflags=hidden)
if args.load:
    # Each WordPress worker opens both WP and voting DB connections; this is local only.
    report['test_database_max_connections']=500
env=dict(os.environ,MHL_TEST_WP_PATH=str(site),MHL_TEST_ADMIN_PASSWORD=admin_password,MHL_TEST_ADMIN_LOGIN='integration_admin')
mysql=[database/'bin/mariadb.exe',f'--defaults-file={client}']
try:
    for _ in range(100):
        try:run(mysql,input=b'SELECT 1;');break
        except RuntimeError:
            if server.poll() is not None:raise RuntimeError('Database did not start; inspect local log')
            time.sleep(.2)
    else:raise RuntimeError('Local DB timed out')
    run(mysql,input=b'CREATE DATABASE integration_wp CHARACTER SET utf8mb4; CREATE DATABASE integration_votes CHARACTER SET utf8mb4;')
    if args.load:run(mysql,input=b'SET GLOBAL max_connections=500;')
    run(mysql,input=b'USE integration_votes;\n'+(repo/'wordpress/miloslavhub-live/database-schema.sql').read_bytes())
    for name in ['wordpress-integration.php','ai-wordpress-integration.php']:
        result=json.loads(run(php+[repo/'tests'/name],env=env));report[name]=result;print(name,json.dumps(result),flush=True)
    result=json.loads(run([sys.executable,repo/'tests/concurrency.py','--php',args.php],env=env))
    report['concurrency']=result;print('concurrency',json.dumps(result),flush=True)
    if args.load:
        result=json.loads(run([sys.executable,repo/'tests/load.py','--php',args.php],env=env))
        report['load']=result;print('load',json.dumps(result),flush=True)
    if args.browser:
        fixture=json.loads(run(php+[repo/'tests/ai-wordpress-browser-setup.php'],env=env))
        web_log=(root/'web.log').open('wb')
        web=subprocess.Popen(php+['-S',f'127.0.0.1:{webport}','-t',str(site)],stdout=web_log,stderr=web_log,creationflags=hidden)
        for _ in range(50):
            try:urllib.request.urlopen(base+'/wp-login.php',timeout=2).close();break
            except OSError:time.sleep(.1)
        result=json.loads(run(['node',repo/'tests/ai-wordpress-browser.cjs',base,str(fixture['question_id'])],env=env))
        report['wordpress_admin_browser']=result;print('wordpress_admin_browser',json.dumps(result),flush=True)
        state=json.loads(run(php+[repo/'tests/ai-wordpress-browser-setup.php','inspect'],env=env))
        if state['title']!='Translated synthetic question?' or state['options']!=['Translated První odpověď','Translated Druhá odpověď'] or not state['disabled'] or state['fixture_calls']!=2:
            raise AssertionError('Persisted browser results do not match approved synthetic changes')
        report['browser_persistence']='passed'
    report['status']='passed'
finally:
    if web is not None:web.terminate();web.wait(timeout=10)
    if web_log is not None:web_log.close()
    try:run([database/'bin/mariadb-admin.exe',f'--defaults-file={client}','shutdown']);server.wait(timeout=20)
    except Exception:server.terminate();server.wait(timeout=10)
    log.close();report['servers_stopped']=True
    (runtime/'local-integration-results.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf8')
    print('Local servers stopped.',flush=True)
