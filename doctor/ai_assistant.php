<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_middleware.php';
enforceRoleAccess(['Doctor']);
$stmt=$pdo->prepare('SELECT doctor_id FROM doctors WHERE user_id=?'); $stmt->execute([$_SESSION['user_id']]); $doctorId=(int)$stmt->fetchColumn();
if (!$doctorId) { http_response_code(403); exit('Doctor profile not linked.'); }
$stmt=$pdo->prepare("SELECT a.appointment_id,a.appointment_date,a.appointment_time,p.full_name FROM appointments a JOIN patients p ON p.patient_id=a.patient_id WHERE a.doctor_id=? ORDER BY a.appointment_date DESC,a.appointment_time DESC LIMIT 100");
$stmt->execute([$doctorId]); $appointments=$stmt->fetchAll();
require_once __DIR__ . '/../includes/header.php'; require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="container my-5">
 <div class="d-flex flex-wrap justify-content-between align-items-center mb-4"><div><h2 class="fw-bold text-primary">Doctor AI Documentation Assistant</h2><p class="text-muted mb-0">Prepare structured drafts from clinician-entered notes and assigned appointment history.</p></div><span class="badge text-bg-warning">Doctor review required</span></div>
 <div class="alert alert-info">School-project demonstration using fictional records only. Do not enter real patient information. AI output is an unverified draft, not a diagnosis or treatment recommendation. Review and edit every draft before approval.</div>
 <div class="card border-0 shadow-sm p-4 mb-4">
  <label class="form-label fw-semibold" for="appointmentSelect">Assigned appointment</label>
  <select id="appointmentSelect" class="form-select"><option value="">Choose a patient appointment</option><?php foreach($appointments as $a): ?><option value="<?php echo (int)$a['appointment_id']; ?>"><?php echo htmlspecialchars($a['full_name'].' — '.$a['appointment_date'].' '.$a['appointment_time']); ?></option><?php endforeach; ?></select>
  <div id="patientHistory" class="mt-3 text-muted">Choose an appointment to view its available history.</div>
 </div>
 <div class="card border-0 shadow-sm p-4">
  <label class="form-label fw-semibold" for="draftType">Draft type</label><select id="draftType" class="form-select mb-3"><option>History Summary</option><option>Consultation Note</option><option>Follow-up Summary</option></select>
  <label class="form-label fw-semibold" for="sourceNotes">Clinician-entered notes</label><textarea id="sourceNotes" class="form-control mb-3" rows="6" maxlength="12000" placeholder="Enter fictional clinician notes. Include only information you want organised."></textarea>
  <button id="generateDraft" class="btn btn-primary">Generate draft</button><div id="draftStatus" class="small mt-2" role="status"></div>
 </div>
 <div id="draftPanel" class="card border-0 shadow-sm p-4 mt-4" hidden>
  <h5 class="fw-bold">Draft for clinician review</h5><textarea id="draftText" class="form-control mb-3" rows="10" maxlength="10000"></textarea>
  <div class="d-flex flex-wrap gap-2"><button id="saveReview" class="btn btn-outline-primary">Save reviewed edits</button><button id="approveDraft" class="btn btn-success">Approve draft</button></div>
  <p class="small text-muted mt-3 mb-0">Approval records your review in the audit log. This prototype does not create prescriptions or treatment orders.</p>
 </div>
</div>
<script>
const api='/api/doctor_ai_assistant.php';let csrf='',currentDraft=0;
const $=id=>document.getElementById(id);
async function request(url,options={}){const r=await fetch(url,{credentials:'same-origin',cache:'no-store',...options});const d=await r.json();if(!r.ok)throw new Error(d.error||'Request failed');return d;}
$('appointmentSelect').addEventListener('change',async()=>{const id=$('appointmentSelect').value;$('patientHistory').textContent='Loading assigned appointment history…';$('draftPanel').hidden=true;if(!id){$('patientHistory').textContent='Choose an appointment to view its available history.';return;}try{const d=await request(api+'?appointment_id='+encodeURIComponent(id));csrf=d.csrf;const a=d.appointment;let html='<strong>'+esc(a.full_name)+'</strong> · '+esc(a.status)+' · '+esc(a.reason||'No appointment reason recorded');html+='<hr><strong>Previous appointments with you</strong>';html+=d.history.length?'<ul>'+d.history.map(h=>'<li>'+esc(h.appointment_date)+' — '+esc(h.status)+' — '+esc(h.reason||'No reason recorded')+'</li>').join('')+'</ul>':'<p>No previous appointments with this doctor.</p>';if(d.drafts.length){html+='<hr><strong>Saved drafts</strong><ul>'+d.drafts.map(x=>'<li>'+esc(x.draft_type)+' · '+esc(x.status)+' · '+esc(x.created_at)+'</li>').join('')+'</ul>';} $('patientHistory').innerHTML=html;}catch(e){$('patientHistory').textContent=e.message;}});
$('generateDraft').addEventListener('click',async()=>{try{const id=$('appointmentSelect').value;if(!id)throw new Error('Choose an assigned appointment first.');if(!csrf)throw new Error('Reload the selected appointment.');$('generateDraft').disabled=true;$('draftStatus').textContent='Preparing draft…';const d=await request(api,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'generate',appointment_id:id,draft_type:$('draftType').value,source_text:$('sourceNotes').value,csrf})});currentDraft=d.draft_id;$('draftText').value=d.draft_text;$('draftPanel').hidden=false;$('draftStatus').textContent='Draft created. Review and edit it before approval.';}catch(e){$('draftStatus').textContent=e.message;}finally{$('generateDraft').disabled=false;}});
$('saveReview').addEventListener('click',()=>draftAction('review'));$('approveDraft').addEventListener('click',()=>draftAction('approve'));
async function draftAction(action){try{if(!currentDraft)throw new Error('Generate a draft first.');if(action==='approve'&&!confirm('Have you reviewed this draft and confirmed its accuracy?'))return;const d=await request(api,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action,draft_id:currentDraft,draft_text:$('draftText').value,csrf})});$('draftStatus').textContent=d.message;if(action==='approve'){$('approveDraft').disabled=true;$('saveReview').disabled=true;}}catch(e){$('draftStatus').textContent=e.message;}}
function esc(v){return String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
