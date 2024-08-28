<div>
    <h1>Daily Time Record</h1>
    <h2>{{ $employee->firstname . " " . $employee->lastname }}</h2>

    <h3>For the month of {{$month}} {{$year}}</h3>


    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Time In AM</th>
                <th>Time Out AM</th>
                <th>Time In PM</th>
                <th>Time Out PM</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($attendances as $att)

                <!-- if holiday put HOLIDAY on time in and time out cells -->
                <!-- if leave put ON LEAVE on time in and time out cells -->
                <!-- if absent put ABSENT on time in and time out cells -->
                <tr>
                    <td>{{ $att['date'] }}</td>
                    <td>
                        @if($att['status'] == 'HOLIDAY')
                            HOLIDAY
                        @elseif($att['status'] == 'LEAVE')
                            ON LEAVE
                        @elseif($att['status'] == 'ABSENT')
                            ABSENT
                        @elseif($att['status'] == 'PRESENT')
                            {{ $att['time_in_session1'] }}
                        @endif
                    </td>
                    <td>
                        @if($att['status'] == 'HOLIDAY')
                            HOLIDAY
                        @elseif($att['status'] == 'LEAVE')
                            ON LEAVE
                        @elseif($att['status'] == 'ABSENT')
                            ABSENT
                        @else
                            {{ $att['time_out_session1'] }}
                        @endif
                    </td>
                    <td>
                        @if($att['status'] == 'HOLIDAY')
                            HOLIDAY
                        @elseif($att['status'] == 'LEAVE')
                            ON LEAVE
                        @elseif($att['status'] == 'ABSENT')
                            ABSENT
                        @else
                            {{ $att['time_in_session2'] }}
                        @endif
                    </td>
                    <td>
                        @if($att['status'] == 'HOLIDAY')
                            HOLIDAY
                        @elseif($att['status'] == 'LEAVE')
                            ON LEAVE
                        @elseif($att['status'] == 'ABSENT')
                            ABSENT
                        @else
                            {{ $att['time_out_session2'] }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td>Total</td>
                <td>{{ $total_hours_worked }}</td>
            </tr>
        </tfoot>
    </table>

    <h4>Total Undertime: {{ $total_undertime_hours }}</h4>
    <h4>Total Overtime: {{ $total_overtime_hours }}</h4>

    <div>
        <p>Prepared by:</p>
        <p>________________________</p>
        <p>{{ $employee->firstname . " " . $employee->lastname }}</p>
    </div>


</div>