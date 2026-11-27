<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_middleware.php';
enforceRoleAccess(['Doctor']);
if (empty($_SESSION['queue_csrf'])) $_SESSION['queue_csrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['queue_csrf'];
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="container my-5"><div class="d-flex justify-content-between flex-wrap align-items-center mb-4"><div><h2 class="fw-bold text-primary">Today's Patient Queue</h2><p class="text-muted mb-0">Manage checked-in patients. Clinical priority remains a staff decision.</p></div><span class="badge text-bg-success p-2">Live updates every 10 seconds</span></div><div id="queueSummary" class="row g-3 mb-3"></div><div id="queueRows" class="card border-0 shadow-sm p-3">Loading queue…</div></div>
<script>
const csrfToken=<?php echo json_encode($csrf); ?>, endpoint='/api/patient_queue.php';
async function refreshQueue(){try{const r=await fetch(endpoint,{cache:'no-store',credentials:'same-origin'}),d=await r.json();if(!r.ok)throw new Error(d.error||'Queue unavailable');document.getElementById('queueSummary').innerHTML='<div class="col-md-4"><div class="card p-3"><div class="text-muted small">Waiting patients</div><div class="fs-2 fw-bold">'+d.waiting+'</div></div></div><div class="col-md-4"><div class="card p-3"><div class="text-muted small">Approx. wait for last patient</div><div class="fs-2 fw-bold">'+d.estimated_wait_minutes+' min</div></div></div>';document.getElementById('queueRows').innerHTML=d.queue.length?'<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Queue</th><th>Patient</th><th>Appointment</th><th>Priority</th><th>Status</th><th>Actions</th></tr></thead><tbody>'+d.queue.map(q=>'<tr><td class="fw-bold">#'+q.queue_number+'</td><td>'+esc(q.patient_name)+'</td><td>'+esc(q.appointment_time)+'</td><td><select class="form-select form-select-sm" onchange="act('+q.queue_id+',\'set_priority\',this.value)"><option '+(q.priority==='Normal'?'selected':'')+'>Normal</option><option '+(q.priority==='Urgent'?'selected':'')+'>Urgent</option></select></td><td>'+esc(q.status)+'</td><td class="d-flex gap-1">'+(q.status==='Waiting'?'<button class="btn btn-sm btn-outline-primary" onclick="act('+q.queue_id+',\'call\')">Call</button>':'')+(q.status==='Called'?'<button class="btn btn-sm btn-primary" onclick="act('+q.queue_id+',\'start\')">Start</button>':'')+(q.status==='In Consultation'?'<button class="btn btn-sm btn-success" onclick="act('+q.queue_id+',\'complete\')">Complete</button>':'')+'</td></tr>').join('')+'</tbody></table></div>':'<p class="text-center text-muted p-4 mb-0">No patients have checked in today.</p>';}catch(e){document.getElementById('queueRows').textContent=e.message;}}
async function act(id,action,priority){try{const r=await fetch(endpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf:csrfToken,queue_id:id,action,priority})}),d=await r.json();if(!r.ok)throw new Error(d.error||'Update failed');refreshQueue();}catch(e){alert(e.message);refreshQueue();}}
function esc(v){return String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));}
refreshQueue();setInterval(refreshQueue,10000);
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
