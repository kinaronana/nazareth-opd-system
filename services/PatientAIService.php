<?php
declare(strict_types=1);
require_once __DIR__ . '/AISafety.php';
// Stage 1 is a transparent rules-based prototype; no external AI provider is configured.
function generatePatientSupportResponse(string $message): array { return patientSupportReply($message); }
