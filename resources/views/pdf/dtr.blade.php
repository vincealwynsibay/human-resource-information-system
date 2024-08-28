<style>
    @import url("https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap");

    body {
        font-family: "Roboto", sans-serif;
        /* height: 100%;
        overflow: hidden;
        font-size: 1rem; */
    }

    .container {
        margin: 0 auto;
        width: min-content;
    }

    h1 {
        text-transform: uppercase;
        font-size: 1.4rem;
        text-align: center;
        margin-block: 1rem;
    }


    .employee-name {
        display: flex;
        flex-direction: column;
    }

    .employee-name h2 {
        font-size: 1.2rem;
        width: 100%;
        text-align: center;
        border-bottom: 3px solid #000;
        margin: 0;
    }

    .employee-name span {
        text-align: center;
    }

    .date {
        display: grid;
        gap: 1rem;
        grid-template-columns: 2fr 1fr;
    }


    .sidebyside {
        margin-top: 0.5rem;
        display: flex;
        align-items: baseline;
    }

    .sidebyside p {
        margin-right: 8px;
        font-weight: bold;
        margin-block: 0;
    }

    .sidebyside span {
        border-bottom: 2px solid black;
        flex: 1;
        /* Take up remaining space */
        padding-bottom: 2px;
        white-space: nowrap;
        /* Prevent text from wrapping */
    }

    .signature {
        display: flex;
        flex-direction: column;
        margin-top: 3.5rem;
    }

    .signature span {
        border-bottom: 3px solid black;
        flex: 1;
        white-space: nowrap;
    }

    .signature p {
        margin: 0;
        text-align: center;
    }

    table {
        border-collapse: collapse;
        margin: 25px auto;
        font-size: 0.9em;
        font-family: sans-serif;
        min-width: 400px;
        box-shadow: 0 0 20px rgba(0, 0, 0, 0.15);
    }

    table thead tr {
        background-color: rgba(0, 0, 0, 0.8);
        color: #ffffff;
        text-align: left;
    }

    table th {
        border: thin solid #dddddd;
    }

    table th,
    table td {
        text-align: center;
        /* padding: 12px 15px; */
        font-size: 14px;
        text-wrap: nowrap;
    }

    table tbody tr {
        border-bottom: thin solid #dddddd;
    }

    table tbody tr:nth-of-type(even) {
        background-color: #f3f3f3;
    }

    table tbody tr:last-of-type {
        border-bottom: 2px solid rgba(0, 0, 0, 0.8);
    }

    table tfoot {
        font-weight: 600;
    }
</style>

<div class="container">
    <h1>Daily Time Record</h1>
    <div class="employee-name">
        <h2>{{ $employee->firstname . " " . $employee->lastname }}</h2>
        <span>Name</span>
    </div>

    <div class="date">
        <div class="sidebyside">
            <p>For the month of</p>
            <span class="underline">{{$month}}</span>
        </div>
        <div class="sidebyside">
            <p>Year</p>
            <span class="underline">{{$year}}</span>
        </div>
    </div>
    <div class="sidebyside">
        <p>Office Hours (regular days)</p>
        <span class="underline">8</span>
    </div>
    <div class="sidebyside">
        <p>Arrival and Departure</p>
        <span class="underline">8:00am and 16:00pm</span>
    </div>

    <table>
        <thead>
            <tr>
                <th></th>
                <th colspan="2">AM</th>
                <th colspan="2">PM</th>
                <th colspan="2">Undertime</th>
                <th></th>
            </tr>
            <tr>
                <th>Date</th>
                <th>Time In</th>
                <th>Time Out</th>
                <th>Time In</th>
                <th>Time Out</th>
                <th>Hours</th>
                <th>Minutes</th>
                <th>Total Hours</th>
            </tr>
        </thead>
        <tbody>
            @foreach($attendances as $att)

                <!-- if holiday put HOLIDAY on time in and time out cells -->
                <!-- if leave put On Leave on time in and time out cells -->
                <!-- if absent put ABSENT on time in and time out cells -->
                <tr>
                    <td>{{ $att['date'] }}</td>
                    <td>
                        @if($att['status'] == 'HOLIDAY')
                            Holiday
                        @elseif($att['status'] == 'NONE')
                            No Log
                        @elseif($att['status'] == 'LEAVE')
                            On Leave
                        @elseif($att['status'] == 'ABSENT')
                            Absent
                        @elseif($att['status'] == 'PRESENT')
                            {{ $att['time_in_session1'] }}
                        @endif
                    </td>
                    <td>
                        @if($att['status'] == 'HOLIDAY')
                            Holiday
                        @elseif($att['status'] == 'NONE')
                            No Log
                        @elseif($att['status'] == 'LEAVE')
                            On Leave
                        @elseif($att['status'] == 'ABSENT')
                            Absent
                        @else
                            {{ $att['time_out_session1'] }}
                        @endif
                    </td>
                    <td>
                        @if($att['status'] == 'HOLIDAY')
                            Holiday
                        @elseif($att['status'] == 'NONE')
                            No Log
                        @elseif($att['status'] == 'LEAVE')
                            On Leave
                        @elseif($att['status'] == 'ABSENT')
                            Absent
                        @else
                            {{ $att['time_in_session2'] }}
                        @endif
                    </td>
                    <td>
                        @if($att['status'] == 'HOLIDAY')
                            Holiday
                        @elseif($att['status'] == 'NONE')
                            No Log
                        @elseif($att['status'] == 'LEAVE')
                            On Leave
                        @elseif($att['status'] == 'ABSENT')
                            Absent
                        @else
                            {{ $att['time_out_session2'] }}
                        @endif
                    </td>

                    <td>
                        @if($att['status'] != '')
                            @php 
                                $hours = (int) $att['undertime_hours'];
                            @endphp

                            @if ($hours == 1)
                                {{$hours }} hour
                            @elseif($hours > 2) 
                                {{ $hours }} hours
                            @else 
                                -
                            @endif

                        @endif
                    </td>
                    <td>

                        @if($att['status'] != '')
                            @php 

                                $minutes = round($att['undertime_hours'] - ((int) $att['undertime_hours']) * 60);

                            @endphp
                            @if ($minutes == 1)
                                {{$minutes }} minute
                            @elseif($minutes > 2) 
                                {{ $minutes }} minutes
                            @else 
                                -
                            @endif
                        @endif
                    </td>


                    <td>
                        @if($att['status'] != '')
                            @if ($att['hours_worked'] == 1)
                                {{$att['hours_worked'] }} hour
                            @elseif($att['hours_worked'] > 2) 
                                {{ $att['hours_worked'] }} hours
                            @else 
                                -
                            @endif
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td>Total</td>
                <td colspan="4">{{ $total_hours_worked }} hours</td>
            </tr>
        </tfoot>
    </table>

    <p>I certify on my honor that the above is true and
        correct record of the hours of work performedv record of
        •which was made daily at the time of arrival and departure
        from the office.</p>
    <div class="signature">
        <span></span>
        <p>Signature</p>
    </div>


</div>