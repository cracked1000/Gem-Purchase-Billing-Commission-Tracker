{{-- Flat ascending-serial table for PDF. Expects: $rows (Collection) --}}
<table class="lines">
    <thead>
        <tr>
            <th style="width:14%;" class="center">Serial</th>
            <th style="width:38%;">Name</th>
            <th style="width:26%;">Category</th>
            <th style="width:12%;" class="right">Weight (ct)</th>
            <th style="width:10%;" class="right">Pieces</th>
        </tr>
    </thead>
    <tbody>
    @foreach ($rows as $row)
        <tr>
            <td class="center">{{ $row['serial'] }}</td>
            <td>{{ $row['name'] }}</td>
            <td>{{ $row['category'] }}</td>
            <td class="right">{{ $row['weight'] === null ? '—' : rtrim(rtrim(number_format((float) $row['weight'], 2), '0'), '.') }}</td>
            <td class="right">{{ number_format((int) $row['pieces']) }}</td>
        </tr>
    @endforeach
        <tr class="section-total">
            <td colspan="3">GEM TOTAL</td>
            <td class="right">{{ rtrim(rtrim(number_format((float) $rows->sum('weight'), 2), '0'), '.') }}</td>
            <td class="right">{{ number_format((int) $rows->sum('pieces')) }}</td>
        </tr>
    </tbody>
</table>
