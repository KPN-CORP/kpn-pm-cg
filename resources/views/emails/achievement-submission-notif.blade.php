<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Notifikasi Achievement Telah Diinput</title>
</head>
<body style="margin:0;padding:30px;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;color:#333;">
<table width="100%" cellspacing="0" cellpadding="0">
    <tr>
        <td align="center">
            <table width="650" cellspacing="0" cellpadding="0" style="background:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #e5e5e5;">
                <tr>
                    <td style="background:#dc3545;padding:20px 30px;color:white;">
                        <h2 style="margin:0;">Notifikasi Achievement Telah Diinput</h2>
                    </td>
                </tr>
                <tr>
                    <td style="padding:30px;">
                        <p style="margin-top:0;">
                            Dear Bapak/Ibu
                            <strong>{{ $employee_manager_name }}</strong>,
                        </p>

                        <p>
                            Melalui email ini, kami ingin menginformasikan bahwa <strong>{{ $employee_name }}</strong>
                            telah selesai mengisi <i>Achievement</i> (Pencapaian) dari Target Setting
                            yang sudah dibuat.
                        </p>

                        <div style="
                            background:#fff3cd;
                            border:1px solid #ffe69c;
                            color:#664d03;
                            padding:18px;
                            border-radius:6px;
                            margin:25px 0;">
                            Terkait hal tersebut, mohon kesediaannya untuk dapat segera menjadwalkan dan melaksanakan
                            sesi Performance Dialog dengan karyawan yang bersangkutan.
                        </div>

                        @if(!empty($url))
                            <div style="margin:35px 0;text-align:center;">
                                <a href="{{ $url }}"
                                   style="background:#dc3545;
                                          color:white;
                                          text-decoration:none;
                                          padding:12px 28px;
                                          border-radius:6px;
                                          display:inline-block;
                                          font-weight:bold;">
                                    Set Performance Dialog Schedule
                                </a>
                            </div>
                        @endif

                        <p style="margin-top:35px;">
                            Salam,
                            <br/>
                            <strong>HC System</strong>
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
