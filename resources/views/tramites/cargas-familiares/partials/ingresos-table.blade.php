<div class="table-responsive">
    <table class="table table-sm table-bordered align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th style="min-width: 110px">Mes</th>
                @foreach ($incomeColumns as $key => $label)
                    <th style="min-width: 130px">{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($meses as $mesKey => $mesLabel)
                <tr>
                    <td class="fw-semibold">{{ $mesLabel }}</td>
                    @foreach ($incomeColumns as $colKey => $label)
                        <td>
                            <input type="number" min="0" step="1" name="{{ $fieldPrefix }}[{{ $mesKey }}][{{ $colKey }}]" class="form-control form-control-sm" value="{{ old($oldPrefix . '.' . $mesKey . '.' . $colKey, 0) }}">
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
