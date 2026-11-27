<?php
declare(strict_types=1);

/** Transparent, history-based operational estimates; not guaranteed outcomes. */
function buildAppointmentForecast(array $historyRows, array $doctors, string $today, int $horizon = 7): array
{
    $horizon = max(1, min(14, $horizon));
    $todayDate = new DateTimeImmutable($today);
    $cutoff = $todayDate->modify('-56 days');
    $dateCounts = [];
    $dateDoctorCounts = [];
    $dateSlotCounts = [];
    $dateWeights = [];
    $outcomeTotal = 0.0;
    $cancelledTotal = 0.0;

    foreach ($historyRows as $row) {
        try { $date = new DateTimeImmutable((string)($row['appointment_date'] ?? '')); }
        catch (Throwable $e) { continue; }
        if ($date >= $todayDate || $date < $cutoff) continue;
        $key = $date->format('Y-m-d');
        $weekday = (int)$date->format('N');
        $doctorId = (int)($row['doctor_id'] ?? 0);
        $status = (string)($row['status'] ?? '');
        $hour = (int)substr((string)($row['appointment_time'] ?? '12:00:00'),0,2);
        $slotStart = intdiv(max(0,min(23,$hour)),2)*2;
        $slot = sprintf('%02d:00–%02d:59',$slotStart,$slotStart+1);
        $weight = ((int)$todayDate->diff($date)->days <= 28) ? 1.35 : 1.0;
        $dateWeights[$key] = $weight;
        $dateCounts[$key][$weekday] = ($dateCounts[$key][$weekday] ?? 0) + 1;
        $dateDoctorCounts[$key][$doctorId] = ($dateDoctorCounts[$key][$doctorId] ?? 0) + 1;
        $dateSlotCounts[$key][$slot] = ($dateSlotCounts[$key][$slot] ?? 0) + 1;
        // Rejected bookings are not cancellations and are excluded from the outcome denominator.
        if (in_array($status,['Pending','Approved','Completed','Cancelled'],true)) {
            $outcomeTotal += $weight;
            if ($status === 'Cancelled') $cancelledTotal += $weight;
        }
    }

    $weekdayStats = $doctorWeekdayStats = $slotStats = [];
    foreach ($dateCounts as $key=>$byDay) {
        $weight = $dateWeights[$key];
        foreach ($byDay as $day=>$count) {
            if (!isset($weekdayStats[$day])) $weekdayStats[$day] = ['sum'=>0.0,'weight'=>0.0];
            $weekdayStats[$day]['sum'] += $count*$weight;
            $weekdayStats[$day]['weight'] += $weight;
        }
        foreach (($dateDoctorCounts[$key] ?? []) as $doctorId=>$count) {
            $day = (int)(new DateTimeImmutable($key))->format('N');
            if (!isset($doctorWeekdayStats[$doctorId][$day])) $doctorWeekdayStats[$doctorId][$day] = ['sum'=>0.0,'weight'=>0.0];
            $doctorWeekdayStats[$doctorId][$day]['sum'] += $count*$weight;
            $doctorWeekdayStats[$doctorId][$day]['weight'] += $weight;
        }
        foreach (($dateSlotCounts[$key] ?? []) as $slot=>$count) {
            if (!isset($slotStats[$slot])) $slotStats[$slot] = ['sum'=>0.0,'weight'=>0.0];
            $slotStats[$slot]['sum'] += $count*$weight;
            $slotStats[$slot]['weight'] += $weight;
        }
    }

    $allSum = $allWeight = 0.0;
    foreach ($weekdayStats as $stats) { $allSum += $stats['sum']; $allWeight += $stats['weight']; }
    $overallAverage = $allWeight > 0 ? $allSum/$allWeight : 0.0;
    $days = [];
    $totalExpected = 0.0;
    for ($i=0; $i<$horizon; $i++) {
        $date = $todayDate->modify('+'.$i.' days');
        $day = (int)$date->format('N');
        $stats = $weekdayStats[$day] ?? null;
        $expected = $stats && $stats['weight'] > 0 ? $stats['sum']/$stats['weight'] : $overallAverage;
        $expected = round(max(0.0,$expected),1);
        $totalExpected += $expected;
        $days[] = ['date'=>$date->format('Y-m-d'),'day'=>$date->format('D'),'expected_appointments'=>$expected];
    }

    $workloads = [];
    foreach ($doctors as $doctor) {
        $id = (int)$doctor['doctor_id'];
        $sum = 0.0;
        foreach ($days as $forecastDay) {
            $day = (int)(new DateTimeImmutable($forecastDay['date']))->format('N');
            $stats = $doctorWeekdayStats[$id][$day] ?? null;
            if ($stats && $stats['weight'] > 0) $sum += $stats['sum']/$stats['weight'];
        }
        $workloads[] = ['doctor_id'=>$id,'doctor_name'=>(string)$doctor['name'],'expected_appointments'=>round($sum,1)];
    }
    usort($workloads,static fn($a,$b)=>$b['expected_appointments']<=>$a['expected_appointments']);

    $busy = [];
    foreach ($slotStats as $slot=>$stats) {
        if ($stats['weight'] > 0) $busy[] = ['time_slot'=>$slot,'average_appointments_per_observed_day'=>round($stats['sum']/$stats['weight'],2)];
    }
    usort($busy,static fn($a,$b)=>$b['average_appointments_per_observed_day']<=>$a['average_appointments_per_observed_day']);
    $cancelRate = $outcomeTotal > 0 ? $cancelledTotal/$outcomeTotal : 0.0;
    $observed = count($dateCounts);
    $confidence = $observed >= 28 ? 'Moderate' : ($observed >= 14 ? 'Low' : 'Insufficient data');

    return [
        'generated_at'=>date(DATE_ATOM),
        'horizon_days'=>$horizon,
        'days'=>$days,
        'summary'=>[
            'expected_appointments'=>round($totalExpected,1),
            'estimated_cancellations'=>round($totalExpected*$cancelRate,1),
            'historical_cancellation_rate'=>round($cancelRate*100,1)
        ],
        'busy_periods'=>array_slice($busy,0,4),
        'doctor_workloads'=>$workloads,
        'data_quality'=>[
            'confidence'=>$confidence,
            'observed_days'=>$observed,
            'history_window_days'=>56,
            'message'=>$observed < 14
                ? 'There is not enough historical activity for a reliable forecast. Treat these figures as preliminary.'
                : 'Estimates use recent appointment patterns and may differ from actual demand.'
        ]
    ];
}

function getAppointmentPredictions(PDO $pdo, int $horizon = 7): array
{
    $history = $pdo->query("SELECT a.appointment_date,a.appointment_time,a.status,a.doctor_id,d.name AS doctor_name
        FROM appointments a INNER JOIN doctors d ON d.doctor_id=a.doctor_id
        WHERE a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 56 DAY)
          AND a.appointment_date < CURDATE() ORDER BY a.appointment_date")->fetchAll(PDO::FETCH_ASSOC);
    $doctors = $pdo->query("SELECT doctor_id,name FROM doctors ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $result = buildAppointmentForecast($history,$doctors,date('Y-m-d'),$horizon);
    $queueRows = $pdo->query("SELECT d.doctor_id,d.name AS doctor_name,
        SUM(CASE WHEN q.status IN ('Waiting','Called') THEN 1 ELSE 0 END) AS waiting_count,
        COUNT(CASE WHEN q.status='Completed' AND q.consultation_started_at IS NOT NULL AND q.completed_at IS NOT NULL
          AND TIMESTAMPDIFF(MINUTE,q.consultation_started_at,q.completed_at) BETWEEN 1 AND 240 THEN 1 END) AS duration_samples,
        AVG(CASE WHEN q.status='Completed' AND q.consultation_started_at IS NOT NULL AND q.completed_at IS NOT NULL
          AND TIMESTAMPDIFF(MINUTE,q.consultation_started_at,q.completed_at) BETWEEN 1 AND 240
          THEN TIMESTAMPDIFF(MINUTE,q.consultation_started_at,q.completed_at) END) AS average_minutes
        FROM doctors d LEFT JOIN patient_queue q ON q.doctor_id=d.doctor_id
          AND q.queue_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY d.doctor_id,d.name ORDER BY d.name")->fetchAll(PDO::FETCH_ASSOC);
    $result['queue_estimates'] = [];
    foreach ($queueRows as $row) {
        $samples = (int)$row['duration_samples'];
        $average = $row['average_minutes'] !== null ? max(1,(int)round((float)$row['average_minutes'])) : 15;
        $waiting = (int)$row['waiting_count'];
        $result['queue_estimates'][] = [
            'doctor_id'=>(int)$row['doctor_id'],
            'doctor_name'=>(string)$row['doctor_name'],
            'waiting_count'=>$waiting,
            'average_consultation_minutes'=>$average,
            'estimated_total_wait_minutes'=>$waiting*$average,
            'sample_count'=>$samples,
            'estimate_basis'=>$samples > 0 ? 'Recent completed queue activity' : '15-minute fallback; no recent completed samples'
        ];
    }
    return $result;
}
