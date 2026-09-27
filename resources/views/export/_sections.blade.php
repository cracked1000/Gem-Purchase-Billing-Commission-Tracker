{{-- Renders one set of category sections. Expects: $sections (Collection of objects) --}}
@php
    use App\Support\Lkr;
    use App\Services\GemExportService;
    $isAssignable = fn (string $key) => in_array($key, ['review', 'moonstone_assign'], true);
@endphp

@foreach ($sections as $section)
    @if ($section->rows->isNotEmpty())
    <div class="card" style="margin-bottom:18px; overflow:hidden;">
        <div class="card-header">
            <h2>{{ $section->title }}</h2>
            <span style="font-size:12px; color:var(--text-muted); display:flex; align-items:center; gap:10px;">
                {{ number_format($section->count) }} row(s) ·
                {{ number_format($section->pieces) }} pc ·
                {{ rtrim(rtrim(number_format($section->weight, 2), '0'), '.') }} ct
                <a href="{{ route('export.pdf', ['mode' => 'all', 'layout' => 'category', 'category' => $section->key]) }}"
                   class="btn btn-outline btn-sm" style="margin-left:4px;" title="Download just this category">PDF</a>
            </span>
        </div>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Serial</th>
                        <th style="width:46%;">Name</th>
                        <th class="right">Weight (ct)</th>
                        <th class="right">Pieces</th>
                        @if ($isAssignable($section->key))
                            <th style="width:34%;">Assign category</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($section->rows as $row)
                        <tr>
                            <td>{{ $row['serial'] }}</td>
                            <td>
                                {{ $row['name'] }}
                                @if (! empty($row['override']))
                                    <span class="badge badge-gold" style="font-size:10px; margin-left:6px;">manual</span>
                                @endif
                            </td>
                            <td class="right">{{ $row['weight'] === null ? '—' : rtrim(rtrim(number_format((float) $row['weight'], 2), '0'), '.') }}</td>
                            <td class="right">{{ number_format((int) $row['pieces']) }}</td>
                            @if ($isAssignable($section->key))
                                <td>
                                    <form method="POST" action="{{ route('export.assign') }}" style="display:flex; gap:6px; align-items:center; margin:0;">
                                        @csrf
                                        <input type="hidden" name="item_id" value="{{ $row['id'] }}">
                                        <select name="category" class="form-select" style="padding:5px 8px; font-size:12px; flex:1;">
                                            <option value="auto">Auto (leave)</option>
                                            @foreach (GemExportService::assignOptionsFor($section->key) as $bkey => $btitle)
                                                <option value="{{ $bkey }}" @selected($row['override'] === $bkey)>{{ $btitle }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="btn btn-gold btn-sm">Assign</button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td style="font-weight:700;">Section total</td>
                        <td></td>
                        <td class="right" style="font-weight:700;">{{ rtrim(rtrim(number_format($section->weight, 2), '0'), '.') }}</td>
                        <td class="right" style="font-weight:700;">{{ number_format($section->pieces) }}</td>
                        @if ($isAssignable($section->key))
                            <td></td>
                        @endif
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @endif
@endforeach
