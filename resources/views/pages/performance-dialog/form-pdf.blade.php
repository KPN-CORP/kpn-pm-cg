<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <style>
            @page {
                margin: 15px;
                size: A4 portrait;
            }

            body {
                font-family: DejaVu Sans, sans-serif;
                font-size: 11px;
                color: #000;
            }

            table {
                width: 100%;
                border-collapse: collapse;
            }

            td {
                vertical-align: top;
            }

            .title {
                font-size: 18px;
                font-weight: bold;
                text-align: center;
                line-height: 1.3;
            }

            .subtitle {
                font-size: 16px;
                font-style: italic;
                font-weight: bold;
            }

            .section-title {
                background: #980000;
                color: white;
                font-weight: bold;
                padding: 4px 8px;
                font-size: 12px;
            }

            .gray-title {
                background: #ececec;
                padding: 3px 6px;
                font-weight: bold;
            }

            .gray-title i {
                font-weight: normal;
            }

            .label-table td {
                padding: 2px 4px;
            }

            .checkbox {
                display: inline-block;
                width: 15px;
                height: 15px;
                border: 1px solid #000;
                margin-right: 5px;
                vertical-align: middle;
            }

            .checked {
                text-align: center;
                font-size: 12px;
                font-weight: bold;
            }

            .space {
                height: 10px;
            }

            img.logo-left {
                height: 48px;
            }

            img.logo-right {
                height: 45px;
            }
        </style>
    </head>
    <body>
        <table>
            <tr>
                <td width="10%">
                    <img class="logo-left" src="{{ public_path('images/logo-text.png') }}">
                </td>
                <td width="80%" class="title"> Form Dokumentasi Performance Review (Dialog) <br>
                    <span class="subtitle"> Performance Review (Dialogue) Form </span>
                </td>
                <td width="10%" align="right">
                    <img class="logo-right" src="{{ public_path('images/logo-cg.png') }}">
                </td>
            </tr>
        </table>
        <br>
        <table class="label-table">
            <tr>
                <td width="100%">
                    <table>
                        <tr>
                            <td width="40%">
                                <b>Hari/Tanggal Diskusi</b>
                            </td>
                            <td width="3%">:</td>
                            <td>{{ $formatted_discussion_date }}</td>
                        </tr>
                        <tr>
                            <td>
                                <b>Nama Atasan</b>
                            </td>
                            <td>:</td>
                            <td>{{ $employee_manager_name }}</td>
                        </tr>
                        <tr>
                            <td>
                                <b>NIK Atasan</b>
                            </td>
                            <td>:</td>
                            <td>{{ $employee_manager_id }}</td>
                        </tr>
                        <tr>
                            <td>
                                <b>Jabatan Atasan</b>
                            </td>
                            <td>:</td>
                            <td>{{ $employee_manager_designation }}</td>
                        </tr>
                    </table>
                </td>
                <td width="4%"></td>
                <td width="100%">
                    <table>
                        <tr>
                            <td width="55%">
                                <b>Direktorat/Divisi/Departemen</b>
                            </td>
                            <td width="3%">:</td>
                            <td>
                                {{ $employee_unit }}
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <b>Nama Karyawan</b>
                            </td>
                            <td>:</td>
                            <td>{{ $employee_name }}</td>
                        </tr>
                        <tr>
                            <td>
                                <b>NIK Karyawan</b>
                            </td>
                            <td>:</td>
                            <td>{{ $employee_id }}</td>
                        </tr>
                        <tr>
                            <td>
                                <b>Jabatan Karyawan</b>
                            </td>
                            <td>:</td>
                            <td>{{ $employee_designation }}</td>
                        </tr>
                    </table>
                </td>
                <td></td>
            </tr>
        </table>
        <div class="space"></div>
        <div class="section-title"> TUJUAN PELAKSANAAN DIALOG / <i>DIALOGUE OBJECTIVES</i>
        </div>
        <table style="margin-top:10px;margin-bottom:10px">
            @php
                $isAlreadyRenderOthers = false;
            @endphp
            @foreach (collect($master_dialog_types)->chunk(2) as $types)
                <tr>
                    @foreach ($types as $master_dialog_type)
                        @php
                            $isSelected = false;

                            if ($dialog_types) {
                                foreach ($dialog_types as $dialog_type) {
                                    if ($master_dialog_type->name == $dialog_type["name"]) {
                                        $isSelected = true;
                                        break;
                                    }
                                }
                            }
                        @endphp
                        <td width="50%" style="padding-top:10px;">
                            <span class="checkbox">
                                @if ($isSelected)
                                    <div class="checked">✓</div>
                                @endif
                            </span>

                            {{ $master_dialog_type->name }}
                        </td>
                    @endforeach
                    @if ($types->count() == 1)
                        @if (!$isAlreadyRenderOthers)
                            @php
                                $isAlreadyRenderOthers = true;
                            @endphp
                            <td width="50%" style="padding-top:10px;">
                                <span class="checkbox">
                                    @if (!empty($others_type_name))
                                        <div class="checked">✓</div>
                                    @endif
                                </span>

                                {{ __("Others") }}

                                : {{ $others_type_name }}
                            </td>
                        @else
                            <td width="50%"></td>
                        @endif
                    @endif
                </tr>
            @endforeach
            @if (!$isAlreadyRenderOthers)
                @php
                    $isAlreadyRenderOthers = true;
                @endphp
                <td width="50%" style="padding-top:10px;">
                    <span class="checkbox">
                        @if (!empty($others_type_name))
                            <div class="checked">✓</div>
                        @endif
                    </span>

                    {{ __("Others") }}

                    : {{ $others_type_name }}
                </td>
            @endif
        </table>
        <div style="height:8px"></div>
        <div class="section-title"> DOKUMENTASI HASIL DIALOG / <i>DIALOGUE RESULT DOCUMENTATION</i>
        </div>
        <table style="margin-top:0;border-collapse:collapse;">
            <tr>
                <td class="gray-title" style="border:1px solid #bdbdbd;"> Ringkasan Dialog/Percakapan <br>
                    <i>Summary Dialogue/Conversation</i>
                </td>
            </tr>
            <tr>
                <td style="height:70px;border:1px solid #000;padding:8px;vertical-align:top;"> {!! nl2br(e($summary)) !!} </td>
            </tr>
        </table>
        <div style="height:10px;"></div>
        <table style="border-collapse:collapse;">
            <tr>
                <td width="72%" style="padding-right:12px;vertical-align:top;">
                    <table>
                        <tr>
                            <td class="gray-title" style="border:1px solid #bdbdbd;"> Rencana Perubahan/Pengembangan <br>
                                <i>Change/Development Plan</i>
                            </td>
                        </tr>
                        <tr>
                            <td style="height:70px;border:1px solid #000;padding:8px;vertical-align:top;"> {!! nl2br(e($development_plan)) !!} </td>
                        </tr>
                    </table>
                </td>
                <td width="28%" style="vertical-align:top;">
                    <table>
                        <tr>
                            <td style="background:#707070;color:#fff;font-weight:bold;padding:4px 6px;border:1px solid #707070;"> Tenggat Waktu / <i>Deadlines</i>
                            </td>
                        </tr>
                        <tr>
                            <td style="height:70px;border:1px solid #000;padding:8px;vertical-align:top;text-align:center;font-size:12px;"> @if(!empty($formatted_due_date)) {{ $formatted_due_date }} @endif </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        <div style="height:10px;"></div>
        <table style="width:100%;border-collapse:collapse;">
            <tr>
                <td class="gray-title" style="width:48%;border:1px solid #bdbdbd;"> Catatan Tambahan / <i>Additional Notes</i>
            </tr>
            <tr>
                <td style="height:60px;border:1px solid #000;padding:8px;vertical-align:top;"> {!! nl2br(e($additional_notes)) !!} </td>
            </tr>
            <tr>
                <td style="border:none;"></td>
            </tr>
            <tr>
                <td style="border:none;"></td>
            </tr>
            <tr>
                <td style="border:none;"></td>
            </tr>
            <tr>
                <td style="border:none;"></td>
            </tr>
        </table>
        <table style="width:100%;border-collapse:collapse;">
            <tr>
                <td class="gray-title" style="width:25%;background:#707070;color:white;border:1px solid #707070;"> Disiapkan Oleh, / Prepared by, </td>
                <td class="gray-title" style="width:25%;background:#707070;color:white;border:1px solid #707070;"> Diketahui Oleh, / Acknowledge by, </td>
            </tr>
            <tr>
                <td style="height:95px;border:1px solid #000;"> &nbsp; </td>
                <td style="height:95px;border:1px solid #000;"> &nbsp; </td>
            </tr>
            <tr>
                <td style="border:1px solid #000;padding:3px 5px">
                    <span style="font-weight:bold;"> Superior Name:</span> {{ $employee_manager_name }}
                </td>
                <td style="border:1px solid #000;padding:3px 5px">
                    <span style="font-weight:bold;"> Employee Name:</span> {{ $employee_name }}
                </td>
            </tr>
            <tr>
                <td style="border:1px solid #000;padding:3px 5px">
                    <span style="font-weight:bold;"> Date:</span> {{ $formatted_initiate_date }}
                </td>
                <td style="border:1px solid #000;padding:3px 5px">
                    <span style="font-weight:bold;"> Date:</span> {{ $formatted_acknowledge_date }}
                </td>
            </tr>
        </table>
    </body>
</html>
