"""Burst votes through independent WordPress/DB processes on disposable loopback data."""
import argparse, hashlib, json, os, subprocess, tempfile, time
from pathlib import Path

parser=argparse.ArgumentParser()
parser.add_argument('--php',required=True)
parser.add_argument('--students',type=int,nargs='+',default=[30,100])
args=parser.parse_args()
if any(n<2 or n>200 for n in args.students):raise SystemExit('Use 2–200 synthetic participants')
repo=Path(__file__).resolve().parents[1]
command=[args.php,'-d','extension=mysqli',str(repo/'tests/concurrency-worker.php')]
hidden=getattr(subprocess,'CREATE_NO_WINDOW',0)
def start(operation,fixture=None,**extra):
    p=subprocess.Popen(command,stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE,creationflags=hidden)
    p.stdin.write(json.dumps(dict(operation=operation,fixture=fixture,**extra)).encode());p.stdin.close();p.stdin=None
    return p
def finish(p):
    out,err=p.communicate(timeout=120)
    if p.returncode:raise RuntimeError(err.decode('utf8','replace')+out.decode('utf8','replace'))
    return json.loads(out)
def call(operation,fixture=None,**extra):return finish(start(operation,fixture,**extra))
def percentile(values,p):return sorted(values)[min(len(values)-1,int((len(values)-1)*p))]
results=[]
with tempfile.TemporaryDirectory(prefix='load-',dir=repo/'runtime') as temporary:
    for students in args.students:
        f=call('setup');call('deadline',f,seconds=600)
        root=Path(temporary)/str(students);root.mkdir();workers=[];directories=[]
        try:
            for index in range(students):
                directory=root/str(index);directory.mkdir();directories.append(directory)
                participant=hashlib.sha256(f'synthetic-{students}-{index}'.encode()).hexdigest()[:32]
                workers.append(start('vote',f,participant_id=participant,barrier=dict(point='before_lock',directory=str(directory),timeout=120)))
            deadline=time.monotonic()+100
            while not all((d/'ready').exists() for d in directories):
                completed=[p for p in workers if p.poll() is not None]
                if completed:
                    early=[finish(p) for p in completed]
                    raise RuntimeError('Workers returned before barrier: '+json.dumps([{'status':r.get('status'),'code':r.get('data',{}).get('code')} for r in early]))
                if time.monotonic()>deadline:raise RuntimeError('Load workers failed to reach the start barrier')
                time.sleep(.05)
            began=time.monotonic()
            for d in directories:(d/'release').write_text('1')
            responses=[finish(p) for p in workers];elapsed=time.monotonic()-began
            if any(r['status']!=201 for r in responses):raise AssertionError('Every unique participant must receive 201')
            state=call('inspect',f)
            if state['votes']!=students:raise AssertionError('Lost or duplicated votes in burst')
            if call('vote',f,participant_id=hashlib.sha256(f'synthetic-{students}-0'.encode()).hexdigest()[:32])['status']!=409:raise AssertionError('Duplicate was accepted')
            call('close',f)
            if call('vote',f,participant_id='a'*32)['status']!=409:raise AssertionError('Late vote was accepted')
            if call('inspect',f)['votes']!=students:raise AssertionError('Rejected votes changed stored count')
            durations=[r['duration_ms'] for r in responses]
            results.append(dict(students=students,concurrent_workers=students,status='passed',votes=state['votes'],burst_seconds=round(elapsed,3),handler_p50_ms=round(percentile(durations,.5),1),handler_p95_ms=round(percentile(durations,.95),1),handler_max_ms=round(max(durations),1)))
        finally:
            for p in workers:
                if p.poll() is None:p.terminate();p.wait(timeout=10)
report=dict(status='passed',scenarios=results,production_data_used=False,paid_api_calls=0,transport='independent PHP CLI processes using real REST callbacks and MariaDB',limits='Local burst test; does not measure hosting HTTP workers, network or sustained polling')
print(json.dumps(report))
