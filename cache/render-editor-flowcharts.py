from pathlib import Path
from html import escape
import textwrap

OUT = Path(__file__).resolve().parents[1] / 'docs' / 'content-editor-visuals'
OUT.mkdir(exist_ok=True)
colors = {'step': ('#ffffff', '#b6ccc3'), 'decision': ('#fff4d8', '#d6a344'), 'end': ('#dff3e8', '#398763'), 'error': ('#ffebe7', '#ce7566'), 'store': ('#e8f0fc', '#789bd0')}

def render(name, title, subtitle, nodes, edges, height):
    parts = [f'''<svg xmlns="http://www.w3.org/2000/svg" width="1100" height="{height}" viewBox="0 0 1100 {height}" role="img" aria-labelledby="title desc">
<title id="title">{escape(title)}</title><desc id="desc">{escape(subtitle)}</desc>
<defs><marker id="arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M 0 0 L 10 5 L 0 10 z" fill="#577469"/></marker></defs>
<rect width="1100" height="{height}" rx="20" fill="#f3f7f5"/>
<rect width="1100" height="130" rx="20" fill="#174e3b"/>
<text x="42" y="56" fill="white" font-family="Segoe UI,Arial,sans-serif" font-size="29" font-weight="700">{escape(title)}</text>
<text x="42" y="95" fill="#d4e7dd" font-family="Segoe UI,Arial,sans-serif" font-size="17">{escape(subtitle)}</text>''']
    byid = {n[0]:n for n in nodes}
    for source, target, label, *custom in edges:
        s, t = byid[source], byid[target]
        sx, sy, tx, ty = s[1], s[2], t[1], t[2]
        if custom:
            pts = custom[0]
        elif sx == tx:
            pts = [(sx,sy+43),(tx,ty-43)] if ty > sy else [(sx,sy-43),(tx,ty+43)]
        elif sy == ty:
            sign = 1 if tx > sx else -1
            pts = [(sx+sign*145,sy),(tx-sign*145,ty)]
        else:
            sign = 1 if tx > sx else -1
            pts = [(sx+sign*145,sy),(tx,sy),(tx,ty-43)]
        d = 'M ' + ' L '.join(f'{x} {y}' for x,y in pts)
        parts.append(f'<path d="{d}" fill="none" stroke="#577469" stroke-width="2" stroke-linejoin="round" marker-end="url(#arrow)"/>')
        if label:
            x,y=pts[0]; x2,y2=pts[1]
            lx,ly=(x+x2)/2,(y+y2)/2
            w=len(label)*8+18
            parts.append(f'<rect x="{lx-w/2}" y="{ly-13}" width="{w}" height="24" rx="7" fill="#f3f7f5"/><text x="{lx}" y="{ly+4}" text-anchor="middle" fill="#385c4d" font-family="Segoe UI,Arial,sans-serif" font-size="14" font-weight="600">{escape(label)}</text>')
    for key,x,y,label,kind in nodes:
        fill,stroke=colors[kind]
        if kind=='decision':
            parts.append(f'<path d="M {x} {y-48} L {x+145} {y} L {x} {y+48} L {x-145} {y} Z" fill="{fill}" stroke="{stroke}" stroke-width="1.5"/>')
        else:
            radius=35 if kind=='end' else 12
            parts.append(f'<rect x="{x-145}" y="{y-43}" width="290" height="86" rx="{radius}" fill="{fill}" stroke="{stroke}" stroke-width="1.5"/>')
        lines=label.split('|')
        for i,line in enumerate(lines):
            parts.append(f'<text x="{x}" y="{y+(i-(len(lines)-1)/2)*21+5}" text-anchor="middle" fill="#173c2e" font-family="Segoe UI,Arial,sans-serif" font-size="16" font-weight="{600 if kind in ("end","decision") else 400}">{escape(line)}</text>')
    parts.append('</svg>')
    svg=''.join(parts)
    (OUT / (name+'.svg')).write_text(svg, encoding='utf-8')
    return name,title,subtitle,svg

charts=[]
charts.append(render('01-access-and-editing','01 / Access and editing','Who can enter, how content loads, and what stays in the working preview.',[
('a',360,205,'Open Content Editor','end'),('b',360,340,'Refresh account identity|and initialize session','step'),
('c',360,485,'Account allowed?','decision'),('deny',850,485,'Sign in, change password,|or reject protected route','error'),
('d',360,630,'Load permitted pages|and select content','step'),('e',360,775,'Valid page / route?','decision'),('bad',850,775,'Return to content hub|or reject request','error'),
('f',360,920,'Capture live-content baseline|before loading preview','store'),('g',360,1060,'Load real website page|and page-specific adapter','step'),
('h',360,1205,'Saved state exists?','decision'),('saved',850,1205,'Restore own draft or|authorized review snapshot|with its original baseline','store'),
('i',360,1350,'Edit text, media, PDFs,|ordering, and layout','step'),('j',360,1490,'Preview desktop / tablet / mobile|Undo or redo local changes','step'),
('k',360,1630,'Choose next action','decision'),('draft',850,1530,'Save draft|Go to diagram 02','store'),('publish',850,1670,'Publish or submit|Go to diagram 03','end'),
('presence',850,920,'Presence every 5 seconds|shows viewers / editors|Other-editor warning is advisory','step'),
('note',360,1790,'Local edits are not public|Undo cannot reverse server saves','end')],
[('a','b',''),('b','c',''),('c','deny','No'),('c','d','Yes'),('d','e',''),('e','bad','No'),('e','f','Yes'),('f','g',''),('g','h',''),('h','saved','Yes'),('h','i','No'),('saved','i','',[(850,1248),(850,1350),(505,1350)]),('i','j',''),('j','k',''),('k','draft','Draft',[(505,1630),(650,1630),(650,1530),(705,1530)]),('k','publish','Publish',[(505,1630),(650,1630),(650,1670),(705,1670)]),('k','note','')],1880))
charts.append(render('02-drafts-and-conflicts','02 / Private drafts and conflicts','Checkpoints belong to one account and route. There is no automatic save.',[
('a',350,205,'Save draft / Ctrl or Cmd + S','end'),('b',350,345,'Serialize preview, pending state,|selected media, and baseline','step'),
('c',350,485,'Send authenticated POST|CSRF + route + draft revision','step'),('d',350,630,'Authorized and valid?','decision'),('err',850,630,'Reject request|Keep pending browser work','error'),
('e',350,785,'Acquire lock; check live|version and publishing lease','step'),('f',350,935,'Current and unlocked?','decision'),('conf',850,935,'HTTP 409 conflict|Keep local changes','error'),
('g',350,1085,'Validate snapshot size, shape,|and own checkpoint revision','step'),('h',350,1235,'Draft revision current?','decision'),('stale',850,1235,'Reject stale checkpoint|Do not overwrite another tab','error'),
('i',350,1385,'Save chunked private snapshot|and return next revision','store'),('j',350,1535,'Draft saved|Public website stays unchanged','end'),
('r',850,1085,'For newer live content:|explicit Save recovery draft|retains the old baseline','store'),('n',850,1385,'Reopen current page and|reconcile before publishing|No merge or overwrite bypass','step')],
[('a','b',''),('b','c',''),('c','d',''),('d','err','No'),('d','e','Yes'),('e','f',''),('f','conf','No'),('f','g','Yes'),('g','h',''),('h','stale','No'),('h','i','Yes'),('i','j',''),('conf','r',''),('r','n','',[(995,1085),(1030,1085),(1030,1385),(995,1385)])],1640))
charts.append(render('03-publish-and-approval','03 / Publish and approval','Only Super Admin publishes directly. Content Editors submit proposals.',[
('a',350,205,'Choose Publish / Submit','end'),('role',350,350,'Super Admin?','decision'),('ed',850,350,'Content Editor:|confirm Submit for approval','step'),
('save',850,490,'Save private checkpoint|Checks from diagram 02 apply','store'),('sub',850,630,'Validate saved revision;|create pending snapshot','store'),
('review',850,775,'Super Admin reviews|proposed text and media','step'),('decision',850,925,'Approve?','decision'),
('revise',850,1070,'Reject / request revision|Record feedback; author|revises and resubmits','error'),
('check',350,630,'Inspect content completeness','step'),('missing',350,775,'Missing content?','decision'),
('warning',350,925,'Keep Editing or|Publish Anyway?','decision'),('keep',350,1070,'Keep editing|No publication begins','end'),
('confirm',350,1210,'Confirm publication; provide|current Super Admin password','step'),('valid',350,1360,'Confirmation valid?','decision'),('retry',850,1360,'Retry under rate limits|or cancel and keep editing','error'),
('send',350,1510,'Run existing page save requests|with confirmation token','step'),('guard',350,1660,'Server checks access, CSRF,|input, versions, lock, and lease','step'),
('result',350,1810,'All writes succeed?','decision'),('fail',850,1810,'Report error; keep remaining work|Earlier writes may be committed|Release lease / confirmation','error'),
('done',350,1960,'Complete review if applicable;|record publication summary;|clear completed checkpoint','store'),('end',350,2110,'Release operation protection|Published changes are public','end')],
[('a','role',''),('role','ed','No'),('role','check','Yes'),('ed','save',''),('save','sub',''),('sub','review',''),('review','decision',''),('decision','revise','No'),('decision','check','Yes',[(705,925),(640,925),(640,630),(495,630)]),('check','missing',''),('missing','warning','Yes'),('missing','confirm','No',[(205,775),(95,775),(95,1210),(205,1210)]),('warning','keep','Keep editing'),('warning','confirm','Publish anyway',[(495,925),(565,925),(565,1210),(495,1210)]),('confirm','valid',''),('valid','retry','No'),('valid','send','Yes'),('send','guard',''),('guard','result',''),('result','fail','No'),('result','done','Yes'),('done','end','')],2210))
charts.append(render('04-independent-forms','04 / Independent forms and managers','News, Announcements, Events, and other existing management actions.',[
('a',350,205,'Open existing embedded form','end'),('b',350,350,'Enter changes; submit existing|create / edit / delete action','step'),('c',350,495,'Validate session, role, CSRF,|ownership, and action','step'),('d',350,645,'Super Admin?','decision'),
('direct',850,645,'Run applicable confirmation|and conflict protection','step'),('apply',850,790,'Execute original handler|Return result and audit','end'),
('stage',350,790,'Stage proposal fields|and validated uploads privately','store'),('draft',350,940,'Draft intent?','decision'),('private',850,940,'Keep proposal Draft|Author revises and submits','store'),
('pending',350,1090,'Pending proposal|Super Admin reviews','store'),('decision',350,1240,'Approve?','decision'),('reject',850,1240,'Record reason / feedback|Return to author for revision','error'),
('worker',350,1390,'Confirm; mark Processing|One-use reviewer session|replays allowlisted handler','step'),('checks',350,1540,'Original handler revalidates|permissions, CSRF, uploads,|inputs, and conflicts','step'),
('result',350,1690,'Confirmed success?','decision'),('uncertain',850,1690,'Failure / uncertain / processing|Inspect actual content|No automatic retry','error'),('end',350,1840,'Record Approved and audit','end')],
[('a','b',''),('b','c',''),('c','d',''),('d','direct','Yes'),('direct','apply',''),('d','stage','No'),('stage','draft',''),('draft','private','Yes'),('draft','pending','No'),('private','pending','Submit',[(850,983),(850,1090),(495,1090)]),('pending','decision',''),('decision','reject','No'),('decision','worker','Yes'),('worker','checks',''),('checks','result',''),('result','uncertain','No'),('result','end','Yes')],1940))

charts = [render('system-flowchart', 'Content Editor / System flowchart', 'Follow the arrows: actions, decisions, repeat paths, and termination.', [
('start',350,205,'START','end'),
('open',350,340,'Open Content Editor','step'),
('access',350,475,'Session and role|allowed?','decision'),
('denied',850,475,'Require sign-in or deny access','step'),
('stop',850,610,'END — access not granted','end'),
('select',350,620,'Select a page to edit','step'),
('load',350,755,'Display page; restore saved|draft if one is available','step'),
('edit',350,890,'Edit page content','step'),
('preview',350,1025,'Preview changes; use Undo / Redo|and device views as needed','step'),
('leave',350,1160,'Finish this session?','decision'),
('guard',850,1160,'Resolve pending-change prompt|before leaving, if shown','step'),
('end',850,1295,'END — leave editor','end'),
('draft',350,1430,'Choose Save Draft?','decision'),
('save',850,1430,'Validate and save private draft','step'),
('saved',850,1565,'Save successful?','decision'),
('saveerror',850,1700,'Show error; retain work|Resolve conflict if reported','step'),
('proceed',350,1835,'Choose Publish /|Submit for approval?','decision'),
('role',350,1970,'Super Admin?','decision'),
('submit',850,1970,'Confirm submission;|save and submit checkpoint','step'),
('submitted',850,2105,'Submission|successful?','decision'),
('suberror',850,2240,'Show error; keep pending work','step'),
('review',850,2375,'Super Admin opens proposal|and reviews changes','step'),
('approve',850,2510,'Approve changes?','decision'),
('revise',850,2645,'Record rejection / feedback;|author revises the proposal','step'),
('pub',350,2510,'Run publication procedure|See detailed flowchart below','step'),
('pubok',350,2780,'Publication|completed?','decision'),
('fix',850,2780,'Inspect error and actual content;|retain and correct remaining work','step'),
('done',350,2915,'Display publication success','step'),
('finish',350,3050,'END — changes published','end')], [
('start','open',''),('open','access',''),('access','denied','No'),('denied','stop',''),('access','select','Yes'),('select','load',''),('load','edit',''),('edit','preview',''),('preview','leave',''),('leave','guard','Yes'),('guard','end',''),
('leave','draft','No'),('draft','save','Yes'),('save','saved',''),
('saved','edit','Yes',[(995,1565),(1060,1565),(1060,890),(495,890)]),
('saved','saveerror','No'),('saveerror','edit','',[(995,1700),(1080,1700),(1080,890),(495,890)]),
('draft','proceed','No'),('proceed','edit','No',[(205,1835),(80,1835),(80,890),(205,890)]),('proceed','role','Yes'),
('role','submit','No'),('submit','submitted',''),('submitted','suberror','No'),
('suberror','edit','',[(995,2240),(1090,2240),(1090,890),(495,890)]),
('submitted','review','Yes',[(995,2105),(1030,2105),(1030,2375),(995,2375)]),('review','approve',''),('approve','revise','No'),
('revise','edit','',[(995,2645),(1090,2645),(1090,890),(495,890)]),
('role','pub','Yes'),('approve','pub','Yes'),('pub','pubok',''),('pubok','fix','No'),
('fix','edit','',[(995,2780),(1090,2780),(1090,890),(495,890)]),('pubok','done','Yes'),('done','finish','')
],3150), render('publication-system-flowchart', 'Publication / Detailed system flow', 'Subprocess used by direct publication and approved visual-editor proposals.', [
('start',350,205,'START — publication requested','end'),
('inspect',350,340,'Inspect page completeness','step'),
('missing',350,475,'Content missing?','decision'),
('warn',850,475,'Display completeness warning','step'),
('anyway',850,610,'Publish Anyway?','decision'),
('cancel',850,745,'RETURN — continue editing','end'),
('confirm',350,880,'Confirm publication and enter|current Super Admin password','step'),
('ok',350,1015,'Confirmation valid?','decision'),
('retry',850,1015,'Show error or cancellation;|retry confirmation or return','step'),
('checks',350,1150,'Check permissions, request|validity, and content version','step'),
('valid',350,1285,'Checks pass?','decision'),
('error',850,1285,'Report error; preserve work|Reconcile newer page if stale','step'),
('fail',850,1420,'RETURN — needs correction','end'),
('write',350,1555,'Execute next publication action','step'),
('success',350,1690,'Action successful?','decision'),
('partial',850,1690,'Show failure; retain remaining work|Check earlier committed actions','step'),
('fail2',850,1825,'RETURN — incomplete publication','end'),
('more',350,1960,'More actions?','decision'),
('complete',350,2095,'Complete approval if applicable;|record publication and|clear completed checkpoint','step'),
('release',350,2230,'Release publication protection|and refresh page','step'),
('end',350,2365,'RETURN — publication completed','end')], [
('start','inspect',''),('inspect','missing',''),('missing','warn','Yes'),('warn','anyway',''),('anyway','cancel','No'),
('anyway','confirm','Yes',[(995,610),(1045,610),(1045,880),(495,880)]),('missing','confirm','No'),
('confirm','ok',''),('ok','retry','No'),('retry','confirm','Retry',[(995,1015),(1060,1015),(1060,880),(495,880)]),
('ok','checks','Yes'),('checks','valid',''),('valid','error','No'),('error','fail',''),('valid','write','Yes'),('write','success',''),
('success','partial','No'),('partial','fail2',''),('success','more','Yes'),
('more','write','Yes',[(205,1960),(90,1960),(90,1555),(205,1555)]),('more','complete','No'),('complete','release',''),('release','end','')
],2470)]

sections=''.join(f'<section id="chart{i}"><h2>{escape(title)}</h2><p>{escape(subtitle)}</p><div class="tools"><button onclick="zoom(this,0.2)">Zoom +</button><button onclick="zoom(this,-0.2)">Zoom −</button><button onclick="reset(this)">Fit width</button><a href="{name}.svg" download>Download SVG</a></div><div class="canvas">{svg}</div></section>' for i,(name,title,subtitle,svg) in enumerate(charts,1))
html='''<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>GNCI Content Editor — visual flowcharts</title><style>
*{box-sizing:border-box}body{margin:0;background:#edf3ef;color:#173c2e;font:16px/1.6 "Segoe UI",Arial,sans-serif}header{background:#174e3b;color:white;padding:38px max(24px,calc((100vw - 1200px)/2))}h1{font-size:34px;line-height:1.2;margin:0 0 12px}header p{color:#d4e7dd;margin:0}nav{display:flex;gap:12px;flex-wrap:wrap;margin-top:22px}nav a{color:white;padding:7px 14px;border:1px solid #6d9786;border-radius:8px;text-decoration:none}main{max-width:1250px;margin:auto;padding:28px 20px}section{background:white;padding:24px;border:1px solid #d4e1d9;border-radius:16px;margin-bottom:30px}h2{margin:0;font-size:24px}p{margin:8px 0 16px}.tools{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px}.tools button,.tools a{font:inherit;font-size:14px;color:#174e3b;background:#edf6f0;border:1px solid #b6ccc3;border-radius:7px;padding:7px 13px;cursor:pointer;text-decoration:none}.canvas{overflow:auto;max-height:85vh;background:#f3f7f5;border-radius:12px}.canvas svg{display:block;width:100%;height:auto;min-width:650px}.legend{display:flex;gap:18px;flex-wrap:wrap;margin-bottom:20px}.legend span:before{content:"";display:inline-block;width:14px;height:14px;border:1px solid #aaa;border-radius:3px;margin-right:7px;background:var(--c)}footer{font-size:14px;color:#526c60;padding:10px 0 30px}@media print{header{padding:15px;color:#173c2e;background:white}header p{color:#173c2e}nav,.tools{display:none}section{break-before:page;border:0;padding:0}.canvas{max-height:none;overflow:visible}.canvas svg{width:100%!important;min-width:0}main{padding:0}.legend,footer{display:none}}
</style><header><h1>Content Editor module</h1><p>Detailed workflow visuals · GNCI · Based on the current repository implementation</p><nav><a href="#chart1">01 Access & editing</a><a href="#chart2">02 Drafts & conflicts</a><a href="#chart3">03 Publish & approval</a><a href="#chart4">04 Forms & managers</a></nav></header><main><div class="legend"><span style="--c:#fff">Process</span><span style="--c:#fff4d8">Decision</span><span style="--c:#e8f0fc">Stored state</span><span style="--c:#ffebe7">Error / revision</span><span style="--c:#dff3e8">Start / outcome</span></div>'''+sections+'''<footer>Visual documentation only. Private checkpoints, approval proposals, and published content are separate states. Multi-request publication can partially commit. No application code or database changes were made. Detailed source references: <a href="../content-editor-flowchart.md">content-editor-flowchart.md</a>.</footer></main><script>function zoom(b,d){let s=b.closest('section').querySelector('svg');let z=Math.max(.6,Math.min(3,(Number(s.dataset.zoom)||1)+d));s.dataset.zoom=z;s.style.width=(z*100)+'%'}function reset(b){let s=b.closest('section').querySelector('svg');s.dataset.zoom=1;s.style.width='100%'}</script></html>'''
html = html.replace('GNCI Content Editor — visual flowcharts', 'GNCI Content Editor — system flowchart')
html = html.replace('Detailed workflow visuals · GNCI · Based on the current repository implementation', 'System flowchart · GNCI · Actions, decisions, loops, and outcomes')
nav_start, nav_end = html.index('<nav>'), html.index('</nav>') + len('</nav>')
html = html[:nav_start] + '<nav><a href="#chart1">Main system flowchart</a><a href="#chart2">Publication subprocess</a></nav>' + html[nav_end:]
html = html.replace('<span style="--c:#e8f0fc">Stored state</span>', '')
html = html.replace('<span style="--c:#ffebe7">Error / revision</span>', '')
html = html.replace('Private checkpoints, approval proposals, and published content are separate states.', 'Scope: the visual page editor. Embedded News, Announcements, and Events forms retain their separate workflows. Invalid draft or submission actions return to editing. Missing draft/protection storage reports an unavailable action.')
(OUT/'index.html').write_text(html, encoding='utf-8')
print('Created standalone HTML and', len(charts), 'SVG diagrams in', OUT)
