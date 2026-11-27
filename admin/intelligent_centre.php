<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_middleware.php';
enforceRoleAccess(['Admin']);
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="container my-5">
 <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4"><div><h2 class="fw-bold text-primary"><i class="fa-solid fa-brain me-2"></i>AI Intelligent Centre</h2><p class="text-secondary mb-0">A consolidated view of outpatient operations, queue activity and management insights.</p></div><button class="btn btn-outline-primary" id="refreshIntelligence" type="button"><i class="fa-solid fa-rotate me-1"></i>Refresh insights</button></div>
 <div class="alert alert-info"><strong>Operational estimates only.</strong> AI-generated management summaries support administrative review; they are not clinical advice and do not make patient-care decisions. This is a school-project demonstration using fictional records.</div>
 <div id="intelligenceStatus" class="small text-muted mb-3" role="status" aria-live="polite">Loading operational intelligence…</div>
 <div class="row g-3 mb-4">
  <div class="col-6 col-lg-3"><div class="card p-3 h-100"><div class="small text-muted">Appointments today</div><div class="display-6 fw-bold" id="totalToday">—</div></div></div>
  <div class="col-6 col-lg-3"><div class="card p-3 h-100"><div class="small text-muted">Pending bookings</div><div class="display-6 fw-bold" id="pendingToday">—</div></div></div>
  <div class="col-6 col-lg-3"><div class="card p-3 h-100"><div class="small text-muted">Completed visits</div><div class="display-6 fw-bold" id="completedToday">—</div></div></div>
  <div class="col-6 col-lg-3"><div class="card p-3 h-100"><div class="small text-muted">Active waiting queue</div><div class="display-6 fw-bold" id="waitingNow">—</div></div></div>
 </div>
 <div class="row g-4">
  <div class="col-lg-7"><section class="card p-4 h-100"><h5 class="fw-bold">AI-generated management summary</h5><p class="small text-muted mb-2" id="summarySource">Summary source: —</p><p id="managementSummary" class="mb-0">Loading summary…</p></section></div>
  <div class="col-lg-5"><section class="card p-4 h-100"><h5 class="fw-bold">Operational alerts</h5><div id="operationalAlerts" aria-live="polite"></div></section></div>
  <div class="col-md-6"><section class="card p-4 h-100"><h5 class="fw-bold">Queue intelligence</h5><div class="row g-2"><div class="col-4"><div class="small text-muted">Waiting</div><strong id="queueWaiting">—</strong></div><div class="col-4"><div class="small text-muted">Called</div><strong id="queueCalled">—</strong></div><div class="col-4"><div class="small text-muted">In consultation</div><strong id="queueConsulting">—</strong></div></div><p class="small text-muted mt-3 mb-0" id="queueDataNote"></p></section></div>
  <div class="col-md-6"><section class="card p-4 h-100"><h5 class="fw-bold">Workload and patient activity</h5><p class="mb-2">Registered doctors: <strong id="doctorCount">—</strong></p><p class="mb-2">Highest appointments for one doctor today: <strong id="highestLoad">—</strong></p><p class="mb-0">Distinct patients with appointment activity in 7 days: <strong id="patientActivity">—</strong></p></section></div>
  <div class="col-12"><section class="card p-4"><h5 class="fw-bold">Appointment trends and demand forecast</h5><p class="mb-2">Expected appointments in the next 7 days: <strong id="forecastAppointments">—</strong></p><p class="mb-2">Estimated cancellations: <strong id="forecastCancellations">—</strong></p><p class="small text-muted mb-2" id="forecastQuality"></p><div id="busyPeriods" class="d-flex flex-wrap gap-2"></div></section></div>
 </div>
</div>
<script>
(function(){
 const $=id=>document.getElementById(id);
 const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
 async function load(){
  const status=$('intelligenceStatus'); status.textContent='Refreshing operational insights…'; $('refreshIntelligence').disabled=true;
  try{
   const r=await fetch('/api/admin_intelligent_centre.php',{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});
   const d=await r.json(); if(!r.ok)throw new Error(d.error||'Could not load insights.');
   $('totalToday').textContent=d.appointments.total_today; $('pendingToday').textContent=d.appointments.pending_today; $('completedToday').textContent=d.appointments.completed_today; $('waitingNow').textContent=d.queue.waiting;
   $('queueWaiting').textContent=d.queue.waiting; $('queueCalled').textContent=d.queue.called; $('queueConsulting').textContent=d.queue.in_consultation;
   $('queueDataNote').textContent=d.data_quality.queue_available?'Counts reflect today’s queue records.':'Queue schema unavailable; queue figures are shown as zero and must not be relied upon.';
   $('doctorCount').textContent=d.workload.doctors_total; $('highestLoad').textContent=d.workload.highest_doctor_appointments_today; $('patientActivity').textContent=d.patient_activity.unique_patients_7d;
   $('managementSummary').textContent=d.management_summary; $('summarySource').textContent='Summary source: '+(d.summary_provider==='gemini'?'Gemini (aggregate operational figures only)':'local rules-based fallback');
   $('forecastAppointments').textContent=d.forecast.expected_appointments; $('forecastCancellations').textContent=d.forecast.estimated_cancellations;
   $('forecastQuality').textContent='Confidence: '+d.forecast.confidence+'; '+d.forecast.observed_days+' observed historical dates. Forecasts are estimates only.';
   $('busyPeriods').innerHTML=d.forecast.busy_periods.length?d.forecast.busy_periods.map(p=>'<span class="badge text-bg-light border">'+esc(p.time_slot)+': '+esc(p.average_per_day)+'/day</span>').join(''):'<span class="small text-muted">No historical time-slot data available.</span>';
   $('operationalAlerts').innerHTML=d.alerts.map(a=>'<div class="alert alert-'+(a.level==='warning'?'warning':a.level==='success'?'success':'info')+' py-2"><strong>'+esc(a.title)+'</strong><div class="small">'+esc(a.detail)+'</div></div>').join('');
   status.textContent='Updated '+new Date(d.generated_at).toLocaleString()+'. Figures are operational summaries and estimates.';
  }catch(e){status.textContent=e.message;}finally{$('refreshIntelligence').disabled=false;}
 }
 $('refreshIntelligence').addEventListener('click',load); load();
})();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
