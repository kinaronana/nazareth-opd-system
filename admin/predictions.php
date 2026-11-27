<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_middleware.php';
enforceRoleAccess(['Admin']);
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="container my-5">
 <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
  <div><h2 class="fw-bold text-primary"><i class="fa-solid fa-chart-line me-2"></i>AI Appointment &amp; Queue Predictions</h2><p class="text-secondary mb-0">History-based operational forecasts for appointment planning, staffing and queue capacity.</p></div>
  <label class="small fw-semibold">Forecast range<select id="forecastDays" class="form-select mt-1"><option value="7">Next 7 days</option><option value="14">Next 14 days</option></select></label>
 </div>
 <div class="alert alert-info"><strong>Forecast notice:</strong> These are statistical estimates based on historical records, not guaranteed outcomes. They do not determine clinical urgency or treatment priority.</div>
 <div id="predictionStatus" class="text-muted mb-3" role="status">Loading predictions…</div>
 <div class="row g-3 mb-4">
  <div class="col-md-4"><div class="card p-3 h-100"><div class="text-muted small">Expected appointments</div><div class="display-6 fw-bold" id="expectedAppointments">—</div><div class="small text-muted">Selected forecast period</div></div></div>
  <div class="col-md-4"><div class="card p-3 h-100"><div class="text-muted small">Estimated cancellations</div><div class="display-6 fw-bold" id="estimatedCancellations">—</div><div class="small text-muted" id="cancellationRate">Historical cancellation rate: —</div></div></div>
  <div class="col-md-4"><div class="card p-3 h-100"><div class="text-muted small">Forecast confidence</div><div class="display-6 fw-bold" id="forecastConfidence">—</div><div class="small text-muted" id="observedDays">Historical observations: —</div></div></div>
 </div>
 <div class="row g-4">
  <div class="col-lg-7"><div class="card p-3 h-100"><h5 class="fw-bold">Daily appointment forecast</h5><div id="dailyForecast" class="table-responsive"></div></div></div>
  <div class="col-lg-5"><div class="card p-3 h-100"><h5 class="fw-bold">Busiest time periods</h5><div id="busyPeriods"></div></div></div>
  <div class="col-lg-6"><div class="card p-3 h-100"><h5 class="fw-bold">Doctor workload forecast</h5><div id="doctorWorkloads" class="table-responsive"></div></div></div>
  <div class="col-lg-6"><div class="card p-3 h-100"><h5 class="fw-bold">Queue waiting-time estimates</h5><div id="queueEstimates" class="table-responsive"></div></div></div>
 </div>
 <p class="small text-muted mt-3" id="forecastMethod"></p>
</div>
<script>
(function(){
 const $=id=>document.getElementById(id), status=$('predictionStatus');
 const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
 async function load(){
  status.textContent='Updating estimates…';
  try{
   const response=await fetch('/api/appointment_predictions.php?days='+$('forecastDays').value,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});
   const data=await response.json(); if(!response.ok)throw new Error(data.error||'Predictions could not be loaded.');
   $('expectedAppointments').textContent=data.summary.expected_appointments;
   $('estimatedCancellations').textContent=data.summary.estimated_cancellations;
   $('cancellationRate').textContent='Historical cancellation rate: '+data.summary.historical_cancellation_rate+'%';
   $('forecastConfidence').textContent=data.data_quality.confidence;
   $('observedDays').textContent='Historical dates with bookings: '+data.data_quality.observed_days;
   $('dailyForecast').innerHTML='<table class="table table-sm"><thead><tr><th>Day</th><th>Date</th><th class="text-end">Expected</th></tr></thead><tbody>'+data.days.map(d=>'<tr><td>'+esc(d.day)+'</td><td>'+esc(d.date)+'</td><td class="text-end fw-semibold">'+esc(d.expected_appointments)+'</td></tr>').join('')+'</tbody></table>';
   $('busyPeriods').innerHTML=data.busy_periods.length?data.busy_periods.map(p=>'<p class="d-flex justify-content-between border-bottom pb-2"><span>'+esc(p.time_slot)+'</span><strong>'+esc(p.average_appointments_per_observed_day)+'/day</strong></p>').join(''):'<p class="text-muted">No historical time-slot data yet.</p>';
   $('doctorWorkloads').innerHTML=data.doctor_workloads.length?'<table class="table table-sm"><thead><tr><th>Doctor</th><th class="text-end">Expected</th></tr></thead><tbody>'+data.doctor_workloads.map(d=>'<tr><td>'+esc(d.doctor_name)+'</td><td class="text-end">'+esc(d.expected_appointments)+'</td></tr>').join('')+'</tbody></table>':'<p class="text-muted">No doctors found.</p>';
   $('queueEstimates').innerHTML=data.queue_estimates.length?'<table class="table table-sm"><thead><tr><th>Doctor / basis</th><th>Waiting</th><th>Avg. min</th><th>Total min</th></tr></thead><tbody>'+data.queue_estimates.map(q=>'<tr><td>'+esc(q.doctor_name)+'<div class="small text-muted">'+esc(q.estimate_basis)+'</div></td><td>'+esc(q.waiting_count)+'</td><td>'+esc(q.average_consultation_minutes)+'</td><td>'+esc(q.estimated_total_wait_minutes)+'</td></tr>').join('')+'</tbody></table>':'<p class="text-muted">No doctors found.</p>';
   $('forecastMethod').textContent=data.data_quality.message+' Method: weekday-weighted history from the previous 56 days; queue averages use valid completed consultations from the previous 30 days. Generated '+new Date(data.generated_at).toLocaleString()+'.';
   status.textContent='Predictions updated. All figures are estimates.';
  }catch(e){status.textContent=e.message;}
 }
 $('forecastDays').addEventListener('change',load); load();
})();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
