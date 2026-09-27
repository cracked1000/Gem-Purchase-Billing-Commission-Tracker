<!DOCTYPE html>
<html lang="en" id="htmlRoot">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') · GemDesk</title>
    <meta name="description" content="GemDesk — Professional gem purchase billing and seller management for Sri Lankan gem traders.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ── DARK MODE (default) ── */
        :root {
            --bg-primary:    #080808;
            --bg-secondary:  #101010;
            --bg-card:       rgba(255,255,255,0.04);
            --bg-card-hover: rgba(255,255,255,0.07);
            --border:        rgba(255,255,255,0.07);
            --border-strong: rgba(255,255,255,0.13);
            /* monochromatic accent — white family */
            --gold:          #c8c8c8;
            --gold-light:    #f0f0f0;
            --gold-dark:     #888888;
            --text-primary:  #f2f2f2;
            --text-secondary:#909090;
            --text-muted:    #484848;
            --green:         #22c55e;
            --red:           #ef4444;
            --amber:         #aaaaaa;
            --nav-width:     240px;
            --radius:        12px;
        }

        /* ── LIGHT MODE ── */
        html.light {
            --bg-primary:    #ffffff;
            --bg-secondary:  #f5f5f5;
            --bg-card:       #ffffff;
            --bg-card-hover: #fafafa;
            --border:        rgba(0,0,0,0.07);
            --border-strong: rgba(0,0,0,0.14);
            /* monochromatic accent — black family */
            --gold:          #333333;
            --gold-light:    #0a0a0a;
            --gold-dark:     #555555;
            --text-primary:  #0a0a0a;
            --text-secondary:#505050;
            --text-muted:    #999999;
            --green:         #15803d;
            --red:           #dc2626;
            --amber:         #555555;
        }

        html.light body { background: var(--bg-primary); color: var(--text-primary); }
        html.light .topbar { background: rgba(255,255,255,0.88); border-bottom-color: var(--border); }
        html.light .sidebar { background: var(--bg-secondary); border-right-color: var(--border); }
        html.light .card { background: #fff; border-color: var(--border); box-shadow: 0 1px 8px rgba(0,0,0,0.06); }
        html.light .stat-card { background: #fff; border-color: var(--border); box-shadow: 0 1px 8px rgba(0,0,0,0.06); }
        html.light .form-input, html.light .form-select, html.light .form-textarea {
            background: #fff; border-color: var(--border-strong); color: var(--text-primary);
        }
        html.light .form-select option { background: #fff; color: var(--text-primary); }
        html.light .gem-input { background: #fff; border-color: rgba(0,0,0,0.14); color: var(--text-primary); }
        html.light .data-table thead tr { background: #f9f9f9; }
        html.light .data-table tbody tr:hover { background: #f5f5f5; }
        html.light .data-table tbody tr { border-bottom-color: rgba(0,0,0,0.05); }
        html.light .alert-success { background: rgba(21,128,61,0.08); border-color: rgba(21,128,61,0.25); color: #15803d; }
        html.light .alert-error { background: rgba(220,38,38,0.08); border-color: rgba(220,38,38,0.25); color: #dc2626; }
        html.light .badge-paid { background: rgba(21,128,61,0.10); color: #15803d; border-color: rgba(21,128,61,0.25); }
        html.light .badge-pending { background: rgba(220,38,38,0.08); color: #dc2626; border-color: rgba(220,38,38,0.2); }
        html.light .btn-gold { background: #0a0a0a; color: #ffffff; box-shadow: 0 2px 8px rgba(0,0,0,0.25); }
        html.light .btn-gold:hover { background: #1a1a1a; box-shadow: 0 4px 16px rgba(0,0,0,0.35); }
        html.light .btn-outline { color: var(--text-secondary); border-color: var(--border-strong); }
        html.light .btn-outline:hover { background: #f0f0f0; color: var(--text-primary); }
        html.light .btn-danger { background: rgba(220,38,38,0.07); border-color: rgba(220,38,38,0.2); color: #dc2626; }
        html.light .totals-summary { background: rgba(0,0,0,0.02); border-color: rgba(0,0,0,0.09); }
        html.light .totals-row { border-bottom-color: rgba(0,0,0,0.06); }
        html.light .totals-row.grand { color: #0a0a0a; border-top-color: rgba(0,0,0,0.12); }
        html.light .new-bill-btn { background: #0a0a0a; color: #ffffff; box-shadow: 0 4px 16px rgba(0,0,0,0.2); }
        html.light .new-bill-btn:hover { background: #1a1a1a; box-shadow: 0 6px 20px rgba(0,0,0,0.3); }
        html.light .nav-link { color: var(--text-secondary); }
        html.light .nav-link:hover { background: rgba(0,0,0,0.04); color: var(--text-primary); }
        html.light .nav-link.active { background: #0a0a0a; color: #ffffff; border-color: transparent; }
        html.light .gem-table thead tr { background: #f5f5f5; }
        html.light .gem-table th { color: var(--text-muted); }
        html.light a.link-gold { color: var(--text-primary); }
        html.light a.link-gold:hover { color: var(--text-muted); }
        html.light .stat-card.gold .stat-value { color: #0a0a0a; }
        html.light .stat-card.amber .stat-value { color: #505050; }
        html.light .text-gold { color: #0a0a0a; }
        html.light .text-amber { color: #505050; }

        /* Theme toggle */
        #themeToggle {
            display: flex; align-items: center; justify-content: center;
            width: 36px; height: 36px; border-radius: 50%;
            border: 1px solid var(--border-strong);
            background: var(--bg-card);
            cursor: pointer; font-size: 15px;
            transition: all 0.2s ease; flex-shrink: 0;
        }
        #themeToggle:hover { background: var(--bg-card-hover); transform: scale(1.1); }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            -webkit-font-smoothing: antialiased;
        }

        /* ── SIDEBAR ── */
        .sidebar {
            width: var(--nav-width);
            min-height: 100vh;
            background: var(--bg-secondary);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0; bottom: 0;
            z-index: 100;
            transition: transform 0.3s ease;
        }

        .sidebar-logo {
            padding: 24px 20px 20px;
            border-bottom: 1px solid var(--border);
        }

        .sidebar-logo a {
            display: flex; align-items: center; gap: 10px;
            text-decoration: none; color: var(--text-primary);
        }

        .logo-gem {
            width: 36px; height: 36px;
            background: var(--text-primary);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px;
            box-shadow: 0 0 16px rgba(255,255,255,0.08);
            filter: invert(0);
        }

        .logo-text { font-size: 18px; font-weight: 700; letter-spacing: -0.5px; }
        .logo-sub  { font-size: 10px; color: var(--text-muted); letter-spacing: 1px; text-transform: uppercase; }

        .sidebar-nav { padding: 16px 12px; flex: 1; overflow-y: auto; }

        .nav-section-label {
            font-size: 10px; font-weight: 600;
            color: var(--text-muted); text-transform: uppercase; letter-spacing: 1.5px;
            padding: 12px 8px 6px;
        }

        .nav-link {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 12px; border-radius: 8px;
            text-decoration: none; color: var(--text-secondary);
            font-size: 13.5px; font-weight: 500;
            transition: all 0.18s ease;
            margin-bottom: 2px;
        }

        .nav-link:hover { background: var(--bg-card-hover); color: var(--text-primary); }

        .nav-link.active {
            background: rgba(255,255,255,0.10);
            color: var(--text-primary);
            border: 1px solid rgba(255,255,255,0.12);
        }

        .nav-link .nav-icon { font-size: 16px; width: 20px; text-align: center; flex-shrink: 0; }

        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid var(--border);
        }

        .new-bill-btn {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%; padding: 10px 16px; border-radius: 10px;
            background: #ffffff;
            color: #080808; font-weight: 700; font-size: 13.5px;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: 0 2px 12px rgba(255,255,255,0.08);
        }

        .new-bill-btn:hover {
            transform: translateY(-1px);
            background: #f0f0f0;
            box-shadow: 0 4px 20px rgba(255,255,255,0.14);
        }

        /* ── MAIN CONTENT ── */
        .main-wrap {
            margin-left: var(--nav-width);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .topbar {
            height: 60px;
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 24px;
            background: rgba(8,8,8,0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            position: sticky; top: 0; z-index: 50;
        }

        .topbar-title {
            font-size: 14px; font-weight: 500; color: var(--text-secondary);
        }

        .topbar-time {
            font-size: 12px; color: var(--text-muted);
            display: flex; align-items: center; gap: 6px;
        }

        .mobile-menu-btn {
            display: none;
            background: none; border: none; cursor: pointer;
            color: var(--text-primary); font-size: 20px; padding: 4px;
        }

        .page-content { padding: 28px 28px 40px; flex: 1; }

        /* ── ALERTS ── */
        .alert {
            display: flex; align-items: flex-start; gap: 10px;
            padding: 12px 16px; border-radius: var(--radius);
            margin-bottom: 16px; font-size: 13.5px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .alert-success {
            background: rgba(34,197,94,0.1);
            border: 1px solid rgba(34,197,94,0.25);
            color: #86efac;
        }

        .alert-error {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.25);
            color: #fca5a5;
        }

        /* ── CARDS ── */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
        }

        .card-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
        }

        .card-header h2 { font-size: 14px; font-weight: 600; color: var(--text-primary); }

        /* ── STAT CARDS ── */
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 18px 20px;
            transition: border-color 0.2s, transform 0.2s;
        }

        .stat-card:hover {
            border-color: var(--border-strong);
            transform: translateY(-1px);
        }

        .stat-label { font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; }
        .stat-value { font-size: 24px; font-weight: 700; color: var(--text-primary); margin: 6px 0 4px; line-height: 1; }
        .stat-sub   { font-size: 11.5px; color: var(--text-muted); }

        .stat-card.gold .stat-value  { color: var(--text-primary); }
        .stat-card.green .stat-value { color: #4ade80; }
        .stat-card.red .stat-value   { color: #f87171; }
        .stat-card.amber .stat-value { color: var(--text-secondary); }

        /* ── TABLES ── */
        .data-table { width: 100%; border-collapse: collapse; }

        .data-table thead tr {
            background: rgba(255,255,255,0.03);
            border-bottom: 1px solid var(--border);
        }

        .data-table th {
            padding: 10px 16px;
            font-size: 10.5px; font-weight: 600;
            color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px;
            text-align: left; white-space: nowrap;
        }

        .data-table th.right { text-align: right; }
        .data-table th.center { text-align: center; }

        .data-table tbody tr {
            border-bottom: 1px solid rgba(255,255,255,0.04);
            transition: background 0.15s;
        }

        .data-table tbody tr:hover { background: var(--bg-card-hover); }
        .data-table tbody tr:last-child { border-bottom: none; }

        .data-table td {
            padding: 12px 16px;
            font-size: 13px; color: var(--text-primary);
            vertical-align: middle;
        }

        .data-table td.right  { text-align: right; }
        .data-table td.center { text-align: center; }
        .data-table td.muted  { color: var(--text-secondary); }

        /* ── BADGES ── */
        .badge {
            display: inline-block; padding: 3px 10px;
            border-radius: 20px; font-size: 11px; font-weight: 600;
        }

        .badge-paid    { background: rgba(34,197,94,0.12);  color: #4ade80; border: 1px solid rgba(34,197,94,0.25); }
        .badge-pending { background: rgba(239,68,68,0.10);  color: #f87171; border: 1px solid rgba(239,68,68,0.2); }
        .badge-gold    { background: rgba(255,255,255,0.08); color: var(--gold-light); border: 1px solid rgba(255,255,255,0.14); }

        /* ── PAGINATION ── */
        .pagination {
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px; flex-wrap: wrap;
        }

        .pagination-info { font-size: 11.5px; color: var(--text-muted); }

        .pagination-controls { display: flex; align-items: center; gap: 6px; }

        .page-item {
            min-width: 30px; height: 30px; padding: 0 8px;
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: 8px;
            border: 1px solid var(--border-strong);
            background: var(--bg-card);
            color: var(--text-secondary);
            font-size: 12.5px; font-weight: 600;
            text-decoration: none;
            transition: all 0.15s ease;
        }

        a.page-item:hover { background: var(--bg-card-hover); color: var(--text-primary); }

        .page-item.active { background: var(--text-primary); color: var(--bg-primary); border-color: transparent; }
        .page-item.disabled { opacity: 0.35; pointer-events: none; }
        .page-dots { color: var(--text-muted); font-size: 12px; padding: 0 2px; }
        html.light .page-item.active { background: #0a0a0a; color: #ffffff; }

        /* ── BUTTONS ── */
        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600;
            text-decoration: none; border: none; cursor: pointer;
            transition: all 0.18s ease;
        }

        .btn-gold {
            background: #f2f2f2;
            color: #080808;
            box-shadow: 0 2px 10px rgba(0,0,0,0.4);
        }

        .btn-gold:hover {
            background: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 4px 18px rgba(0,0,0,0.5);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--border-strong);
            color: var(--text-secondary);
        }

        .btn-outline:hover {
            background: var(--bg-card-hover);
            color: var(--text-primary);
        }

        .btn-ghost {
            background: transparent;
            color: var(--text-secondary);
        }
        .btn-ghost:hover { color: var(--text-primary); background: var(--bg-card); }

        .btn-danger {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.3);
            color: #fca5a5;
        }
        .btn-danger:hover { background: rgba(239,68,68,0.2); }

        .btn-whatsapp {
            background: #25D366; color: #fff;
        }
        .btn-whatsapp:hover { background: #1fb457; transform: translateY(-1px); }

        .btn-wa-internal {
            background: rgba(37,211,102,0.15);
            border: 1px solid rgba(37,211,102,0.3);
            color: #86efac;
        }
        .btn-wa-internal:hover { background: rgba(37,211,102,0.25); }

        .btn-sm { padding: 5px 10px; font-size: 12px; }

        /* ── FORMS ── */
        .form-label {
            display: block; font-size: 12px; font-weight: 600;
            color: var(--text-secondary); margin-bottom: 6px;
            text-transform: uppercase; letter-spacing: 0.5px;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border-strong);
            border-radius: 8px;
            color: var(--text-primary);
            padding: 9px 12px;
            font-size: 13.5px;
            font-family: 'Inter', sans-serif;
            transition: border-color 0.18s, box-shadow 0.18s;
            -webkit-appearance: none;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--text-secondary);
            box-shadow: 0 0 0 3px rgba(255,255,255,0.07);
        }

        .form-input::placeholder { color: var(--text-muted); }
        [data-uppercase] { text-transform: uppercase; }

        .form-select option { background: #101010; color: var(--text-primary); }

        .form-group { margin-bottom: 16px; }

        /* ── PAGE TITLE ── */
        .page-title {
            font-size: 22px; font-weight: 700; color: var(--text-primary);
            letter-spacing: -0.5px;
        }

        .page-subtitle { font-size: 13px; color: var(--text-muted); margin-top: 3px; }
        .page-heading { display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:24px; }
        .safety-note { margin-top:16px; padding:10px 12px; border-radius:8px; background:rgba(34,197,94,.08); border:1px solid rgba(34,197,94,.18); color:var(--text-secondary); font-size:12px; line-height:1.5; }

        /* ── LINKS ── */
        a.link-gold { color: var(--text-primary); text-decoration: none; font-weight: 600; }
        a.link-gold:hover { color: var(--text-secondary); text-decoration: none; }

        /* ── DIVIDER ── */
        .divider { border: none; border-top: 1px solid var(--border); margin: 20px 0; }

        /* ── PRINT ── */
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; color: #000 !important; }
            .main-wrap { margin-left: 0 !important; }
            .sidebar { display: none !important; }
            .topbar { display: none !important; }
            .page-content { padding: 0 !important; }
        }

        /* ── MOBILE ── */
        @media (max-width: 768px) {
            :root { --nav-width: 0px; }

            .sidebar {
                transform: translateX(-240px);
                width: 240px;
            }

            .sidebar.open { transform: translateX(0); box-shadow: 4px 0 40px rgba(0,0,0,0.5); }

            .main-wrap { margin-left: 0; }

            .mobile-menu-btn { display: flex; }

            .sidebar-overlay {
                display: none;
                position: fixed; inset: 0;
                background: rgba(0,0,0,0.6);
                z-index: 99;
            }

            .sidebar-overlay.active { display: block; }

            .page-content { padding: 16px; }
            .topbar { padding: 0 14px; }
            .topbar-title { display:none; }
            .topbar-time span[style] { display:none; }
            [style*="grid-template-columns"] { grid-template-columns: 1fr !important; }
            .card-header { padding:14px 16px; }
            .data-table th, .data-table td { padding:10px 12px; }
            .btn { min-height:38px; }
        }

        /* ── MISC UTILITIES ── */
        .text-gold    { color: var(--text-primary); }
        .text-muted   { color: var(--text-muted); }
        .text-red     { color: #f87171; }
        .text-green   { color: #4ade80; }
        .text-amber   { color: var(--text-secondary); }
        .font-mono    { font-family: 'SF Mono', 'Fira Code', monospace; }
        .font-bold    { font-weight: 700; }
        .font-semibold{ font-weight: 600; }
        .text-sm      { font-size: 12.5px; }
        .text-xs      { font-size: 11px; }
        .gap-2        { gap: 8px; }
        .gap-3        { gap: 12px; }
        .flex         { display: flex; }
        .items-center { align-items: center; }
        .flex-wrap    { flex-wrap: wrap; }
        .justify-between { justify-content: space-between; }
        .w-full       { width: 100%; }

        .grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
        .grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
        .grid-5 { display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; }

        @media (max-width: 900px) {
            .grid-4 { grid-template-columns: repeat(2, 1fr); }
            .grid-5 { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 640px) {
            .grid-2, .grid-3, .grid-4, .grid-5 { grid-template-columns: 1fr; }
        }

        /* Gem table in form */
        .gem-table { width: 100%; border-collapse: collapse; }
        .gem-table thead tr { background: rgba(255,255,255,0.03); border-bottom: 1px solid var(--border); }
        .gem-table th {
            padding: 10px 12px;
            font-size: 10.5px; font-weight: 600;
            color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px;
            text-align: left;
        }
        .gem-table th.right { text-align: right; }
        .gem-table td { padding: 8px 12px; border-bottom: 1px solid rgba(255,255,255,0.03); vertical-align: middle; }
        .gem-table tbody tr:hover { background: rgba(255,255,255,0.02); }

        .gem-input {
            width: 100%;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.09);
            border-radius: 6px;
            color: var(--text-primary);
            padding: 7px 10px;
            font-size: 13px;
            font-family: 'Inter', sans-serif;
            transition: border-color 0.15s, box-shadow 0.15s;
        }

        .gem-input:focus {
            outline: none;
            border-color: var(--text-secondary);
            box-shadow: 0 0 0 2px rgba(255,255,255,0.05);
        }

        .gem-input.right { text-align: right; }

        /* Summary row */
        .totals-summary {
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 16px;
        }

        .totals-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 5px 0;
            font-size: 13.5px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .totals-row:last-child { border-bottom: none; }
        .totals-row.grand {
            font-size: 16px; font-weight: 700;
            color: var(--text-primary);
            border-top: 1px solid rgba(255,255,255,0.10);
            margin-top: 6px; padding-top: 10px;
        }
    </style>
</head>
<body>

{{-- Sidebar Overlay (mobile) --}}
<div class="sidebar-overlay no-print" id="sidebarOverlay" onclick="closeSidebar()"></div>

{{-- Sidebar --}}
<aside class="sidebar no-print" id="sidebar">
    <div class="sidebar-logo">
        <a href="{{ route('dashboard') }}">
            <div class="logo-gem">💎</div>
            <div>
                <div class="logo-text">GemDesk</div>
                <div class="logo-sub">Sri Lanka</div>
            </div>
        </a>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-label">Overview</div>
        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <span class="nav-icon">📊</span> Dashboard
        </a>

        <div class="nav-section-label" style="margin-top:8px;">Bills</div>
        <a href="{{ route('invoices.create') }}" class="nav-link {{ request()->routeIs('invoices.create') ? 'active' : '' }}">
            <span class="nav-icon">✏️</span> New Bill
        </a>
        <a href="{{ route('invoices.index') }}" class="nav-link {{ request()->routeIs('invoices.index') || request()->routeIs('invoices.show') ? 'active' : '' }}">
            <span class="nav-icon">📋</span> Transactions
        </a>
        <a href="{{ route('finalizing.index') }}" class="nav-link {{ request()->routeIs('finalizing.*') ? 'active' : '' }}">
            <span class="nav-icon">🧾</span> Finalizing
        </a>
        <a href="{{ route('verification.index') }}" class="nav-link {{ request()->routeIs('verification.*') ? 'active' : '' }}">
            <span class="nav-icon">🔎</span> Further Verification
        </a>
        <a href="{{ route('gems.index') }}" class="nav-link {{ request()->routeIs('gems.*') ? 'active' : '' }}">
            <span class="nav-icon">💠</span> Gem Register
        </a>
        <a href="{{ route('export.index') }}" class="nav-link {{ request()->routeIs('export.*') ? 'active' : '' }}">
            <span class="nav-icon">📦</span> Gem Export
        </a>

        <div class="nav-section-label" style="margin-top:8px;">People</div>
        <a href="{{ route('sellers.index') }}" class="nav-link {{ request()->routeIs('sellers.index', 'sellers.show', 'sellers.create', 'sellers.edit') ? 'active' : '' }}">
            <span class="nav-icon">👤</span> Sellers
        </a>
        <a href="{{ route('sellers.duplicates') }}" class="nav-link {{ request()->routeIs('sellers.duplicates') ? 'active' : '' }}">
            <span class="nav-icon">🧩</span> Seller Duplicates
        </a>

        <div class="nav-section-label" style="margin-top:8px;">Finance</div>
        <a href="{{ route('finance.index') }}" class="nav-link {{ request()->routeIs('finance.*') ? 'active' : '' }}">
            <span class="nav-icon">🏦</span> Finance Department
        </a>
        <a href="{{ route('payin.index') }}" class="nav-link {{ request()->routeIs('payin.*') ? 'active' : '' }}">
            <span class="nav-icon">💵</span> Payin
        </a>

        <div class="nav-section-label" style="margin-top:8px;">Analytics</div>
        <a href="{{ route('reports.index') }}" class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
            <span class="nav-icon">📈</span> Reports
        </a>

        <div class="nav-section-label" style="margin-top:8px;">Safety</div>
        @if (\Illuminate\Support\Facades\Route::has('settings.index'))
            <a href="{{ route('settings.index') }}" class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                <span class="nav-icon">⚙️</span> Settings
            </a>
        @endif
        <a href="{{ route('backups.index') }}" class="nav-link {{ request()->routeIs('backups.*') ? 'active' : '' }}">
            <span class="nav-icon">🛡️</span> Backups & Restore
        </a>
        <a href="{{ route('invoices.trash') }}" class="nav-link {{ request()->routeIs('invoices.trash') ? 'active' : '' }}">
            <span class="nav-icon">🗃️</span> Archived Bills
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="{{ route('invoices.create') }}" class="new-bill-btn">
            + New Bill
        </a>
    </div>
</aside>

{{-- Main --}}
<div class="main-wrap">
    <header class="topbar no-print">
        <button class="mobile-menu-btn" onclick="openSidebar()">☰</button>
        <div class="topbar-title">@yield('title', 'Dashboard') · GemDesk</div>
        <div style="display:flex; align-items:center; gap:12px;">
            @if ($wipeMode ?? false)
                <span style="font-size:10px; font-weight:700; letter-spacing:1px; color:#86efac; border:1px solid rgba(34,197,94,.3); background:rgba(34,197,94,.08); padding:4px 8px; border-radius:20px;">WIPE ON</span>
            @endif
            <div class="topbar-time" id="sltClock">
                🕐 <span id="sltTime">--:-- --</span> <span style="color:var(--text-muted)">Sri Lanka</span>
            </div>
            <button id="themeToggle" onclick="toggleTheme()" title="Toggle light / dark mode">🌙</button>
        </div>
    </header>

    <main class="page-content">
        @if (session('success'))
            <div class="alert alert-success no-print">
                <span>✅</span><span>{{ session('success') }}</span>
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-error no-print">
                <span>⚠️</span><span>{{ session('error') }}</span>
            </div>
        @endif
        @if ($errors->any())
            <div class="alert alert-error no-print">
                <span>⚠️</span>
                <div>
                    <strong>Please fix the following:</strong>
                    <ul style="margin-top:6px; padding-left:16px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="no-print" style="padding: 16px 28px; border-top: 1px solid var(--border); text-align:center; font-size:11px; color:var(--text-muted);">
        GemDesk · {{ config('gemdesk.company_name') }} · Sri Lanka
    </footer>
</div>

<script>
    // ── Theme (light / dark) ──
    const html = document.getElementById('htmlRoot');
    const toggleBtn = document.getElementById('themeToggle');

    function applyTheme(mode) {
        if (mode === 'light') {
            html.classList.add('light');
            if (toggleBtn) toggleBtn.textContent = '☀️';
        } else {
            html.classList.remove('light');
            if (toggleBtn) toggleBtn.textContent = '🌙';
        }
    }

    function toggleTheme() {
        const isLight = html.classList.contains('light');
        const next = isLight ? 'dark' : 'light';
        localStorage.setItem('gemdesk-theme', next);
        applyTheme(next);
    }

    // Restore saved preference immediately
    applyTheme(localStorage.getItem('gemdesk-theme') || 'dark');

    // Sri Lanka live clock
    function updateSLTClock() {
        const el = document.getElementById('sltTime');
        if (!el) return;
        const now = new Date();
        const slt = new Date(now.toLocaleString('en-US', { timeZone: 'Asia/Colombo' }));
        const h = slt.getHours(), m = slt.getMinutes(), s = slt.getSeconds();
        const ampm = h >= 12 ? 'PM' : 'AM';
        const hh = (h % 12 || 12).toString().padStart(2, '0');
        const mm = m.toString().padStart(2, '0');
        const ss = s.toString().padStart(2, '0');
        el.textContent = `${hh}:${mm}:${ss} ${ampm}`;
    }
    updateSLTClock();
    setInterval(updateSLTClock, 1000);

    // Mobile sidebar
    function openSidebar() {
        document.getElementById('sidebar').classList.add('open');
        document.getElementById('sidebarOverlay').classList.add('active');
    }
    function closeSidebar() {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sidebarOverlay').classList.remove('active');
    }

    // Auto-dismiss alerts after 5s
    document.querySelectorAll('.alert').forEach(el => {
        setTimeout(() => {
            el.style.transition = 'opacity 0.5s';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 500);
        }, 5000);
    });
</script>

@stack('scripts')
</body>
</html>
