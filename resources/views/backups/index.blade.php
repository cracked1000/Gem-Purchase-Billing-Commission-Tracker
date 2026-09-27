@extends('layouts.app')

@section('title', 'Backups & Restore')

@section('content')
<div class="page-heading">
    <div>
        <h1 class="page-title">Backups & Restore</h1>
        <p class="page-subtitle">Protect sellers, bills, line items, payout status, and notes.</p>
    </div>
    <a href="{{ route('backups.download') }}" class="btn btn-gold">⬇ Download Backup</a>
</div>

<div class="grid-2" style="margin-bottom:20px;">
    <section class="card" style="padding:22px;">
        <h2 style="font-size:16px; margin-bottom:8px;">Create a safety copy</h2>
        <p style="font-size:13px; line-height:1.6; color:var(--text-secondary); margin-bottom:18px;">
            Download a verified JSON backup to a USB drive or cloud folder. Receipt PDFs can be regenerated from this business data after restoration.
        </p>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('backups.download') }}" class="btn btn-gold">Download to this computer</a>
            <form method="POST" action="{{ route('backups.snapshot') }}">
                @csrf
                <button class="btn btn-outline" type="submit">Save internal snapshot</button>
            </form>
        </div>
        <div class="safety-note">Automatic snapshots are also created during normal use and before risky changes.</div>
    </section>

    <section class="card" style="padding:22px; border-color:rgba(245,158,11,.3);">
        <h2 style="font-size:16px; margin-bottom:8px;">Restore a backup</h2>
        <p style="font-size:13px; line-height:1.6; color:var(--text-secondary); margin-bottom:16px;">
            The file is checksum-verified first. Existing matching bills are updated, missing records are added, and a pre-restore snapshot is created automatically.
        </p>
        <form method="POST" action="{{ route('backups.restore') }}" enctype="multipart/form-data" onsubmit="return confirm('Restore this backup now? A safety snapshot will be created first.');">
            @csrf
            <div class="form-group">
                <label class="form-label">GemDesk backup file</label>
                <input class="form-input" type="file" name="backup" accept="application/json,.json" required>
            </div>
            <div class="form-group">
                <label class="form-label">Type RESTORE to confirm</label>
                <input class="form-input" type="text" name="confirmation" required pattern="RESTORE" autocomplete="off" placeholder="RESTORE">
            </div>
            <button class="btn btn-outline" type="submit">Verify & Restore</button>
        </form>
    </section>
</div>

<section class="card" style="overflow:hidden;">
    <div class="card-header"><h2>Recent internal snapshots</h2></div>
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>File</th><th>Date</th><th class="right">Size</th></tr></thead>
            <tbody>
            @forelse ($files as $file)
                <tr>
                    <td class="font-mono">{{ $file['name'] }}</td>
                    <td class="muted">{{ \Carbon\Carbon::createFromTimestamp($file['modified'])->format('d M Y, h:i A') }}</td>
                    <td class="right">{{ number_format($file['size'] / 1024, 1) }} KB</td>
                </tr>
            @empty
                <tr><td colspan="3" style="padding:32px; text-align:center; color:var(--text-muted);">No internal snapshots yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
