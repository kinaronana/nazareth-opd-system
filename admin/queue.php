<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_middleware.php';
enforceRoleAccess(['Admin']);
if (empty($_SESSION['queue_csrf'])) $_SESSION['queue_csrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['queue_csrf'];
$doctors=$pdo->query('SELECT doctor_id,name FROM doctors ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="container my-5"><div class="mb-4"><h2 class="fw-bold text-primary">OPD Queue Control Centre</h2><p class="text-muted">Monitor daily queue activity by clinician. Only authorised staff should change clinical priority.</p></div><div class="card border-0 shadow-sm p-3 mb-3"><label for="doctorSelect" class="form-label fw-semibold">Select doctor</label><select id="doctorSelect" class="form-select" onchange="refreshAdminQueue()"><option value="">Choose a doctor</option><?php foreach($doctors as $d): ?><option value="<?php echo (int)$d['doctor_id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option><?php endforeach; ?></select></div><div id="adminQueue" class="card border-0 shadow-sm p-3">Select a doctor to view today's queue.</div></div>
<script>
const csrfToken=<?php echo json_encode($csrf); ?>,endpoint='/api/patient_queue.php';
async function refreshAdminQueue(){const id=document.getElementById('doctorSelect').value,box=document.getElementById('adminQueue');if(!id){box.textContent='Select a doctor to view today\'s queue.';return;}try{const r=await fetch(endpoint+'?doctor_id='+encodeURIComponent(id),{cache:'no-store',credentials:'same-origin'}),d=await r.json();if(!r.ok)throw new Error(d.error||'Queue unavailable');box.innerHTML='<div class="d-flex gap-4 mb-3"><div><div class="text-muted small">Waiting</div><strong class="fs-3">'+d.waiting+'</strong></div><div><div class="text-muted small">Estimated total wait</div><strong class="fs-3">'+d.estimated_wait_minutes+' min</strong></div></div>'+(d.queue.length?'<div class="table-responsive"><table class="table"><thead><tr><th>Number</th><th>Patient</th><th>Appointment</th><th>Priority</th><th>Status</th><th>Staff action</th></tr></thead><tbody>'+d.queue.map(q=>'<tr><td>#'+q.queue_number+'</td><td>'+esc(q.patient_name)+'</td><td>'+esc(q.appointment_time)+'</td><td>'+esc(q.priority)+'</td><td>'+esc(q.status)+'</td><td><select class="form-select form-select-sm" onchange="priority('+q.queue_id+',this.value)"><option '+(q.priority==='Normal'?'selected':'')+'>Normal</option><option '+(q.priority==='Urgent'?'selected':'')+'>Urgent</option></select></td></tr>').join('')+'</tbody></table></div>':'<p class="text-muted text-center">No check-ins yet today.</p>');}catch(e){box.textContent=e.message;}}
async function priority(id,value){const r=await fetch(endpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf:csrfToken,queue_id:id,action:'set_priority',priority:value})});const d=await r.json();if(!r.ok)alert(d.error||'Could not update priority');refreshAdminQueue();}
function esc(v){return String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));}
setInterval(refreshAdminQueue,10000);
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
