"""Exercise controlled races between real WordPress/MariaDB processes, without API calls."""
import argparse
import json
import os
from pathlib import Path
import subprocess
import tempfile
import time

parser=argparse.ArgumentParser()
parser.add_argument('--php',required=True)
args=parser.parse_args()
repo=Path(__file__).resolve().parents[1]
command=[args.php,'-d','extension=mysqli',str(repo/'tests/concurrency-worker.php')]
hidden=getattr(subprocess,'CREATE_NO_WINDOW',0)
checks=0

def check(value,message):
    global checks
    checks+=1
    if not value: raise AssertionError(message)

def start(operation,fixture=None,barrier=None,**extra):
    p=subprocess.Popen(command,stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE,creationflags=hidden)
    p.stdin.write(json.dumps(dict(operation=operation,fixture=fixture,barrier=barrier,**extra)).encode())
    p.stdin.close();p.stdin=None
    return p

def finish(p):
    out,err=p.communicate(timeout=20)
    if p.returncode: raise RuntimeError(err.decode('utf8','replace')+out.decode('utf8','replace'))
    return json.loads(out)

def call(operation,fixture=None,**extra): return finish(start(operation,fixture,**extra))

def ready(directory):
    deadline=time.monotonic()+10
    while not (directory/'ready').exists():
        if time.monotonic()>deadline: raise RuntimeError('Worker did not reach barrier')
        time.sleep(.02)

def release(directory): (directory/'release').write_text('1')

runtime=repo/'runtime';runtime.mkdir(exist_ok=True)
with tempfile.TemporaryDirectory(prefix='concurrency-',dir=runtime) as temp:
    root=Path(temp)
    sequence=0
    def barrier(point):
        global sequence
        sequence+=1;directory=root/str(sequence);directory.mkdir()
        return dict(point=point,directory=str(directory)),directory

    # Closing, reset, switching questions and finishing must win over a stale vote.
    for action in ['close','reset','open_second','finish','expire_run','delete']:
        f=call('setup',mode='test' if action=='delete' else 'live')
        b,d=barrier('before_lock');vote=start('vote',f,b);ready(d)
        result=call(action,f);release(d)
        check(finish(vote)['status']==409,f'{action}: stale vote rejected')
        state=call('inspect',f)
        check(state['votes']==0,f'{action}: no rejected vote stored')
        if action=='reset':
            check(int(state['latest']['id'])!=f['session_id'] and state['latest']['status']=='waiting','Reset creates a new waiting session')
            check(call('close',f).get('error')=='mhl_stale_session','Stale teacher action rejected')
        if action=='delete': check(state['run'] is None and state['session'] is None,'Delete removed the test run')

    # A vote already holding the run lock commits before the teacher can close.
    f=call('setup');b,d=barrier('after_lock');vote=start('vote',f,b);ready(d)
    b2,d2=barrier('observe_lock');close=start('close',f,b2);ready(d2)
    time.sleep(.3);check(close.poll() is None,'Close waits for the vote holding the run lock')
    release(d);check(finish(vote)['status']==201,'Vote acquired lock first and committed')
    check('error' not in finish(close),'Waiting close succeeds')
    state=call('inspect',f);check(state['votes']==1 and state['session']=='closed','Exactly one vote followed by closed session')

    # Two requests from the same participant compete for the same run.
    f=call('setup');b1,d1=barrier('before_lock');b2,d2=barrier('before_lock')
    one=start('vote',f,b1);two=start('vote',f,b2);ready(d1);ready(d2);release(d1);release(d2)
    check(sorted([finish(one)['status'],finish(two)['status']])==[201,409],'Concurrent duplicate returns one success and one conflict')
    check(call('inspect',f)['votes']==1,'Concurrent duplicate persists exactly one vote')

    # The timer runs out after preliminary validation, before lock acquisition.
    f=call('setup');call('deadline',f,seconds=2);b,d=barrier('before_lock');vote=start('vote',f,b);ready(d)
    time.sleep(2.2);release(d)
    check(finish(vote)['status']==409,'Deadline elapsed before lock acquisition')
    check(call('inspect',f)['votes']==0,'Expired waiting vote not stored')

    # The timer runs out during INSERT. The transaction must roll back.
    f=call('setup');call('deadline',f,seconds=2);b,d=barrier('insert');vote=start('vote',f,b);ready(d)
    b2,d2=barrier('observe_lock');closing=start('auto_close',f,b2)
    time.sleep(2.2);release(d);check(finish(vote)['status']==409,'Deadline elapsed during INSERT')
    finish(closing);state=call('inspect',f)
    check(state['votes']==0,'INSERT was rolled back after deadline')
    call('auto_close',f);check(call('inspect',f)['session']=='closed','Automatic expiry closes session')

    f=call('setup');check(call('delete',f) is False,'Test cleanup refuses a live run')
    check(call('inspect',f)['run']=='active','Rejected test cleanup preserves live run')

print(json.dumps(dict(status='passed',checks=checks,independent_processes=True,production_data_used=False,paid_api_calls=0)))
