{{-- Flat, ascending-serial table. Expects: $rows (Collection), $buckets --}}
@php
    use App\Support\Lkr;
    use App\Services\GemExportService;
@endphp

<div class="card" style="overflow:hidden; margin-bottom:18px;">
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th class="right" style="width:14%;">Serial</th>
                    <th style="width:34%;">Name</th>
                    <th style="width:22%;">Category</th>
                    <th class="right">Weight (ct)</th>
                    <th class="right">Pieces</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td class="right font-semibold">{{ $row['serial'] }}</td>
                        <td>
                            {{ $row['name'] }}
                            @if (! empty($row['override']))
                                <span class="badge badge-gold" style="font-size:10px; margin-left:6px;">manual</span>
                            @endif
                        </td>
                        <td>
                            {{ $row['category'] }}
                            @if (in_array($row['category_key'] ?? '', ['review', 'moonstone_assign'], true))
                                <form method="POST" action="{{ route('export.assign') }}" style="display:inline-flex; gap:4px; align-items:center; margin-left:6px; vertical-align:middle;">
                                    @csrf
                                    <input type="hidden" name="item_id" value="{{ $row['id'] }}">
                                    <select name="category" class="form-select" style="padding:3px 6px; font-size:11px; width:auto; max-width:150px;">
                                        <option value="auto">Auto</option>
                                        @foreach (GemExportService::assignOptionsFor($row['category_key']) as $bkey => $btitle)
                                            <option value="{{ $bkey }}" @selected($row['override'] === $bkey)>{{ $btitle }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-gold btn-sm">Assign</button>
                                </form>
                            @endif
                        </td>
                        <td class="right">{{ $row['weight'] === null ? '—' : rtrim(rtrim(number_format((float) $row['weight'], 2), '0'), '.') }}</td>
                        <td class="right">{{ number_format((int) $row['pieces']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            @if ($rows->isNotEmpty())
                <tfoot>
                    <tr>
                        <td class="right" style="font-weight:700;">Total</td>
                        <td colspan="2"></td>
                        <td class="right" style="font-weight:700;">{{ rtrim(rtrim(number_format((float) $rows->sum('weight'), 2), '0'), '.') }}</td>
                        <td class="right" style="font-weight:700;">{{ number_format((int) $rows->sum('pieces')) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
