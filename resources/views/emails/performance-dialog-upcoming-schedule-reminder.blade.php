<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reminder: Performance Dialog Tomorrow</title>
</head>
<body style="margin:0;padding:30px;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;color:#333;">
<table width="100%" cellspacing="0" cellpadding="0">
    <tr>
        <td align="center">
            <table width="650" cellspacing="0" cellpadding="0" style="background:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #e5e5e5;">
                <tr>
                    <td style="background:#0d6efd;padding:20px 30px;color:white;">
                        <h2 style="margin:0;">Reminder: Performance Dialog Tomorrow</h2>
                    </td>
                </tr>
                <tr>
                    <td style="padding:30px;">
                        <p style="margin-top:0;">
                            Dear Bapak/Ibu
                            @if ($is_manager)
                                <strong>{{ $employee_manager_name }}</strong>,
                            @else
                                <strong>{{ $employee_name }}</strong>,
                            @endif
                        </p>

                        <p>
                            Ini adalah pengingat bahwa Anda memiliki jadwal Performance Dialog besok.
                        </p>

                        <table width="100%" cellpadding="10" style="border-collapse:collapse;background:#f8f9fa;border:1px solid #e9ecef;margin:25px 0;">
                            @if ($is_manager)
                                <tr>
                                    <td><strong>Employee</strong></td>
                                    <td>{{ $employee_name }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Designation</strong></td>
                                    <td>{{ $employee_designation }}</td>
                                </tr>
                            @endif

                            <tr>
                                <td width="35%"><strong>Tanggal</strong></td>
                                <td>{{ $formatted_start_date }}</td>
                            </tr>
                            <tr style="background:white;">
                                <td><strong>Waktu</strong></td>
                                <td>{{ $formatted_start_time }}</td>
                            </tr>
                        </table>

                        @if ($is_manager)
                            <p>
                                Mohon pastikan Anda telah mempersiapkan sesi Performance Dialog.
                            </p>
                        @else
                            <p>
                                Pastikan Anda telah mempersiapkan diri untuk sesi tersebut.
                            </p>
                        @endif

                        @if(!empty($url))
                            <div style="margin:35px 0;text-align:center;">
                                <a href="{{ $url }}"
                                   style="background:#0d6efd;
                                          color:white;
                                          text-decoration:none;
                                          padding:12px 28px;
                                          border-radius:6px;
                                          display:inline-block;
                                          font-weight:bold;">
                                    View Performance Dialog
                                </a>
                            </div>
                        @endif

                        <p style="margin-top:40px;">
                            Terima kasih.
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="background:#f8f9fa;
                               text-align:center;
                               padding:18px;
                               color:#888;
                               font-size:12px;">
                        This is an automated email, please do not reply to this email.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
