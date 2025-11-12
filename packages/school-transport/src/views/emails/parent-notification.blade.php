<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $data['subject'] ?? 'School Transport Notification' }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background-color: #3b82f6;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }

        .content {
            background-color: #f9fafb;
            padding: 20px;
            border: 1px solid #e5e7eb;
        }

        .footer {
            background-color: #1f2937;
            color: #9ca3af;
            padding: 15px;
            text-align: center;
            font-size: 12px;
            border-radius: 0 0 8px 8px;
        }

        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #3b82f6;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin: 10px 0;
        }

        .info-box {
            background-color: white;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            margin: 15px 0;
        }

        .alert-box {
            background-color: #fef2f2;
            border-left: 4px solid #dc2626;
            padding: 15px;
            margin: 15px 0;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>School Transport Notification</h1>
    </div>

    <div class="content">
        <p>{{ $message }}</p>

        @if(isset($data['student_name']))
        <div class="info-box">
            <strong>Student:</strong> {{ $data['student_name'] }}<br>
            @if(isset($data['route_name']))
            <strong>Route:</strong> {{ $data['route_name'] }}<br>
            @endif
            @if(isset($data['bus_number']))
            <strong>Bus:</strong> {{ $data['bus_number'] }}<br>
            @endif
            @if(isset($data['time']))
            <strong>Time:</strong> {{ $data['time'] }}<br>
            @endif
        </div>
        @endif

        @if(isset($data['portal_url']))
        <p style="text-align: center;">
            <a href="{{ $data['portal_url'] }}" class="button">View Parent Portal</a>
        </p>
        @endif
    </div>

    <div class="footer">
        <p>This is an automated notification from your school's transportation system.</p>
        <p>If you have any questions, please contact your school office.</p>
    </div>
</body>

</html>