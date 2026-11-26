<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_middleware.php';
enforceRoleAccess(['Patient']);
$activePage = 'queue';
if (empty($_SESSION['queue_csrf'])) $_SESSION['queue_csrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['queue_csrf'];
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="container my-5">
 <div class="d-flex flex-wrap justify-content-between align-items-center mb-4"><div><h2 class="fw-bold text-primary">My Patient Queue</h2><p class="text-muted mb-0">Check in for today's approved appointment and follow your place in line.</p></div><span class="badge text-bg-success p-2">Live updates every 15 seconds</span></div>
 <div id="queueMessage" class="alert d-none" role="status"></div><div id="patientQueue" class="row g-3"><div class="col-12"><div class="card p-4 text-center text-muted">Loading today's appointments…</div></div></div>
</div>
<script>
const queueCsrf=<?php echo json_encode($csrf); ?>;
const queueRoot='/api/patient_queue.php';
async function loadPatientQueue(){
 const box=document.getElementById('patientQueue');
 try{
  const res=await fetch(queueRoot,{credentials:'same-origin',cache:'no-store'}),data=await res.json();
  if(!res.ok)throw new Error(data.error||'Unable to load queue.');
  if(!data.appointments.length){box.innerHTML='<div class="col-12"><div class="card p-4 text-center"><h5>No approved appointments for today</h5><p class="text-muted mb-0">Your queue check-in will appear here when you have an approved appointment scheduled for today.</p></div></div>';return;}
  box.innerHTML=data.appointments.map(a=>'<div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="d-flex justify-content-between"><div><h5 class="fw-bold mb-1">Dr. '+escapeHtml(a.doctor_name)+'</h5><div class="text-muted small">'+escapeHtml(a.department_name||'General')+' · '+escapeHtml(a.appointment_time)+'</div></div><span class="badge '+(a.queue_id?'text-bg-success':'text-bg-secondary')+'">'+(a.queue_id?'Checked in':'Not checked in')+'</span></div>'+(a.queue_id?'<div class="row text-center my-4"><div class="col-4"><div class="text-muted small">Queue number</div><div class="fs-2 fw-bold text-primary">#'+a.queue_number+'</div></div><div class="col-4"><div class="text-muted small">Position</div><div class="fs-2 fw-bold">'+(a.position||'—')+'</div></div><div class="col-4"><div class="text-muted small">Est. wait</div><div class="fs-2 fw-bold">'+(a.estimated_wait_minutes||0)+'m</div></div></div><p class="mb-0 small text-muted">Status: '+escapeHtml(a.status)+' · Estimates are approximate and may change.</p>':'<p class="text-muted mt-3">Check in to receive your queue number.</p><button class="btn btn-primary" onclick="checkIn('+a.appointment_id+')">Check in now</button>')+'</div></div></div>').join('');
 }catch(e){box.innerHTML='<div class="col-12"><div class="alert alert-warning">'+escapeHtml(e.message)+'</div></div>';}
}
async function checkIn(id){
 const msg=document.getElementById('queueMessage');msg.className='alert alert-info';msg.textContent='Checking you in…';
 try{const r=await fetch(queueRoot,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'check_in',appointment_id:id,csrf:queueCsrf})}),d=await r.json();if(!r.ok)throw new Error(d.error||'Check-in failed.');msg.className='alert alert-success';msg.textContent=d.message+(d.queue_number?' Your queue number is #'+d.queue_number+'.':'');await loadPatientQueue();}catch(e){msg.className='alert alert-danger';msg.textContent=e.message;}
}
function escapeHtml(v){return String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));}
loadPatientQueue();setInterval(loadPatientQueue,15000);
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
