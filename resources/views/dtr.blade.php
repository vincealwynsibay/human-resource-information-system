<div>
    <h1>Daily Time Record</h1>
    <h2>{{ $employee->name }}</h2>

    <h3>For the month of {{$month}} {{$year}}</h3>


    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Time In</th>
                <th>Time Out</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($attendance as $att)
                <!-- if holiday put HOLIDAY on time in and time out cells -->
                <!-- if leave put ON LEAVE on time in and time out cells -->
                <!-- if absent put ABSENT on time in and time out cells -->
                <tr>
                    <td>{{ $att->date }}</td>
                    <td>
                        @if($att->status == 'holiday')
                            HOLIDAY
                        @elseif($att->status == 'leave')
                            ON LEAVE
                        @elseif($att->status == 'absent')
                            ABSENT
                        @else
                            {{ $att->time_in }}
                        @endif
                    </td>
                    <td>
                        @if($att->status == 'holiday')
                            HOLIDAY
                        @elseif($att->status == 'leave')
                            ON LEAVE
                        @elseif($att->status == 'absent')
                            ABSENT
                        @else
                            {{ $att->time_out }}
                        @endif
                    </td>
                    <td>
                        @if($att->status == 'holiday')
                            HOLIDAY
                        @elseif($att->status == 'leave')
                            ON LEAVE
                        @elseif($att->status == 'absent')
                            ABSENT
                        @else
                            {{ $att->status }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td>Total</td>
                <td>{{ $total['time_in'] }}</td>
                <td>{{ $total['time_out'] }}</td>
            </tr>
        </tfoot>
    </table>

    <h4>Total Undertime: {{ $total['undertime'] }}</h4>
    <h4>Total Overtime: {{ $total['overtime'] }}</h4>

    <div>
        <p>Prepared by:</p>
        <p>________________________</p>
        <p>{{ $employee->name }}</p>
    </div>


</div>