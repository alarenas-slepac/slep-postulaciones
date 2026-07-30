<div class="fw-semibold mb-2">{{ $title }}</div>
<div class="table-responsive">
    <table class="table table-sm table-bordered align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th>Mes</th>
                @foreach ($incomeColumns as $label)
                    <th>{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $mes => $row)
                <tr>
                    <td class="fw-semibold">{{ ucfirst(str_replace('_', ' ', $mes)) }}</td>
                    @foreach ($incomeColumns as $key => $label)
                        <td class="text-end">{{ number_format((float) ($row[$key] ?? 0), 0, ',', '.') }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
