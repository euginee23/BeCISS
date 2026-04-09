<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Appointment Scheduled — BeCISS</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f4f4f5; color: #18181b; -webkit-font-smoothing: antialiased; }
        .wrapper { max-width: 560px; margin: 40px auto; padding: 0 16px 40px; }
        .card { background: #ffffff; border-radius: 20px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
        .header { background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%); padding: 40px 40px 32px; text-align: center; }
        .logo-row { display: inline-flex; align-items: center; gap: 10px; margin-bottom: 24px; }
        .logo-icon { width: 44px; height: 44px; background: rgba(255,255,255,0.2); border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; }
        .logo-text { font-size: 22px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px; }
        .header-icon { width: 64px; height: 64px; background: rgba(255,255,255,0.2); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; }
        .header-title { font-size: 24px; font-weight: 800; color: #ffffff; line-height: 1.2; margin-bottom: 6px; }
        .header-sub { font-size: 14px; color: rgba(255,255,255,0.85); }
        .body { padding: 36px 40px; }
        .greeting { font-size: 16px; color: #3f3f46; margin-bottom: 12px; }
        .message { font-size: 15px; color: #52525b; line-height: 1.7; margin-bottom: 28px; }
        .section-label { font-size: 11px; font-weight: 700; letter-spacing: 1.2px; text-transform: uppercase; color: #a1a1aa; margin-bottom: 12px; }
        .details-card { background: #fafafa; border-radius: 14px; border: 1px solid #e4e4e7; overflow: hidden; margin-bottom: 28px; }
        .detail-row { display: flex; align-items: flex-start; padding: 12px 16px; border-bottom: 1px solid #f4f4f5; }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { font-size: 12px; font-weight: 600; color: #a1a1aa; width: 130px; flex-shrink: 0; }
        .detail-value { font-size: 13px; color: #18181b; font-weight: 500; flex: 1; }
        .status-badge { display: inline-block; padding: 4px 12px; background: #dbeafe; color: #1e40af; font-size: 12px; font-weight: 700; border-radius: 999px; border: 1px solid #bfdbfe; }
        .security { background: #f9fafb; border-radius: 12px; padding: 16px 20px; font-size: 13px; color: #71717a; line-height: 1.6; border: 1px solid #e4e4e7; }
        .security strong { color: #3f3f46; }
        .footer { padding: 24px 40px; background: #fafafa; border-top: 1px solid #f4f4f5; text-align: center; }
        .footer-brand { font-size: 13px; font-weight: 700; color: #2563eb; margin-bottom: 6px; }
        .footer-text { font-size: 12px; color: #a1a1aa; line-height: 1.6; }
        @media only screen and (max-width: 480px) {
            .wrapper { margin: 0 auto; padding: 0 8px 24px; }
            .card { border-radius: 16px; }
            .header { padding: 28px 20px 24px; }
            .header-title { font-size: 22px; }
            .body { padding: 24px 20px; }
            .detail-row { flex-direction: column; gap: 2px; padding: 10px 14px; }
            .detail-label { width: auto; font-size: 11px; }
            .footer { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="card">
            <div class="header">
                <div class="logo-row">
                    <span class="logo-icon">
                        <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418" />
                        </svg>
                    </span>
                    <span class="logo-text">BeCISS</span>
                </div>
                <div class="header-icon">
                    <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <div class="header-title">Appointment Scheduled</div>
                <div class="header-sub">Your appointment has been booked by the barangay</div>
            </div>

            <div class="body">
                <p class="greeting">Hi, <strong>{{ $user->name }}</strong>!</p>
                <p class="message">
                    An appointment has been scheduled for you at the barangay hall. Please review the details below and appear at the specified date and time.
                </p>

                <div class="section-label">Appointment Details</div>
                <div class="details-card">
                    <div class="detail-row">
                        <span class="detail-label">Reference #</span>
                        <span class="detail-value">{{ $appointment->reference_number }}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Service Type</span>
                        <span class="detail-value">{{ $appointment->service_type_label }}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Date</span>
                        <span class="detail-value">{{ $appointment->appointment_date->format('l, F j, Y') }}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Time</span>
                        <span class="detail-value">{{ $appointment->appointment_time->format('g:i A') }}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Duration</span>
                        <span class="detail-value">{{ $appointment->duration_minutes }} minutes</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Status</span>
                        <span class="detail-value"><span class="status-badge">Scheduled</span></span>
                    </div>
                    @if ($appointment->notes)
                    <div class="detail-row">
                        <span class="detail-label">Notes</span>
                        <span class="detail-value">{{ $appointment->notes }}</span>
                    </div>
                    @endif
                </div>

                <div class="security">
                    <strong>Questions or changes?</strong> Please contact the barangay office directly if you have any questions
                    or need to reschedule this appointment.
                </div>
            </div>

            <div class="footer">
                <div class="footer-brand">BeCISS</div>
                <div class="footer-text">
                    Barangay Community Information &amp; Services System<br />
                    This is an automated message — please do not reply to this email.
                </div>
            </div>
        </div>

        <p style="text-align:center; font-size:12px; color:#a1a1aa; margin-top:20px;">
            &copy; {{ date('Y') }} BeCISS. All rights reserved.
        </p>
    </div>
</body>
</html>
