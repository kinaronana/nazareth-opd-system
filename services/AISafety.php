<?php
declare(strict_types=1);
function aiEmergencyWarning(string $message): bool {
 $text = strtolower($message);
 foreach ([
  '/\b(chest pain|chest pressure|cannot breathe|can.t breathe|difficulty breathing|shortness of breath)\b/',
  '/\b(unconscious|not responding|seizure|convulsion|stroke|face drooping|slurred speech)\b/',
  '/\b(severe bleeding|bleeding heavily|overdose|poisoning|suicidal|kill myself|want to die)\b/',
  '/\b(severe allergic reaction|throat closing|swollen tongue)\b/'
 ] as $pattern) if (preg_match($pattern, $text)) return true;
 return false;
}
function patientSupportReply(string $message): array {
 $text = strtolower(trim($message));
 if (aiEmergencyWarning($text)) return ['text'=>'Your message may describe an emergency. Please seek emergency medical help now or contact your local emergency number. Do not wait for a chat response. If possible, ask someone nearby to help you.','safety_flag'=>'emergency_warning'];
 if (preg_match('/\b(book|booking|appointment|schedule|visit)\b/',$text)) return ['text'=>'To book an appointment, sign in and choose “Book Appointment” from the patient menu. Select an available department or doctor, choose a date and time, enter a brief reason, and submit. Review the request under “My Appointments”. Hospital staff handle approval.','safety_flag'=>null];
 if (preg_match('/\b(register|sign ?up|create an account)\b/',$text)) return ['text'=>'Choose “Register” on the sign-in page and complete the form. After creating your account, sign in to request an appointment. This is a school-project demonstration; use fictional details only.','safety_flag'=>null];
 if (preg_match('/\b(cancel|reschedule|change.*appointment)\b/',$text)) return ['text'=>'Open “My Appointments” to review your bookings. If cancellation or rescheduling is unavailable, contact reception. This assistant does not change appointments.','safety_flag'=>null];
 if (preg_match('/\b(queue|check.?in|waiting|turn|wait time)\b/',$text)) return ['text'=>'For an approved appointment scheduled today, open “My Patient Queue” and select “Check in now”. Your position and approximate waiting time appear after check-in. Estimates can change.','safety_flag'=>null];
 if (preg_match('/\b(department|specialist|doctor|clinic)\b/',$text)) return ['text'=>'Browse available doctors and departments using “Find a Doctor” in your patient dashboard. The portal is the source of truth; this assistant cannot assign a clinician.','safety_flag'=>null];
 if (preg_match('/\b(symptom|pain|feel|sick|dizzy|fever|headache|cough|nausea)\b/',$text)) return ['text'=>'I’m sorry you are feeling unwell. I can help organise what to discuss with a healthcare professional, but cannot diagnose or recommend treatment. When did this start, and is it improving, worsening, or unchanged? If symptoms are severe or rapidly worsen, seek urgent medical care.','safety_flag'=>'symptom_guidance'];
 if (preg_match('/\b(prescription|medicine|medication|test result|lab result|medical record)\b/',$text)) return ['text'=>'Review information available to your account in Prescriptions, Test Results, and Medical Records. Ask your clinician to interpret results or advise on medication. I cannot prescribe or change medication.','safety_flag'=>null];
 if (preg_match('/\b(hello|hi|hey|good morning|good afternoon)\b/',$text)) return ['text'=>'Hello! I can help with registration, appointments, departments, queue check-in, and navigating the patient portal.','safety_flag'=>null];
 return ['text'=>'I can help with registration, appointments, departments, queue check-in, and portal information. For symptoms, I can help organise questions for a clinician, but cannot diagnose or recommend treatment. What would you like help with?','safety_flag'=>null];
}
