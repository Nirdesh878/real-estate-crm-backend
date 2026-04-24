<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Receipt</title>
    <style>
      :root {
        --green-900: #0b3d33;
        --green-800: #0f4f42;
        --gold-500: #c8a04a;
        --ink: #1f2937;
        --muted: #6b7280;
        --paper: #f7f2e9;
        --line: rgba(31, 41, 55, 0.16);
      }

      * { box-sizing: border-box; }
      body {
        margin: 0;
        font-family: ui-serif, Georgia, "Times New Roman", serif;
        color: var(--ink);
        background: #111827;
      }

      .page {
        width: 820px;
        margin: 24px auto;
        background: var(--paper);
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 20px 60px rgba(0,0,0,.35);
        border: 1px solid rgba(255,255,255,.06);
      }

      .header {
        background: linear-gradient(180deg, var(--green-900), #073127);
        padding: 26px 30px 18px;
        color: #f9fafb;
        border-bottom: 3px solid rgba(200,160,74,.55);
      }

      .brand {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        font-size: 20px;
      }

      .content {
        padding: 28px 34px 34px;
      }

      .titleRow {
        display: grid;
        grid-template-columns: 1fr auto 1fr;
        align-items: center;
        gap: 12px;
        margin: 8px 0 18px;
      }

      .rule {
        height: 1px;
        background: var(--line);
      }

      .receiptTitle {
        font-size: 34px;
        letter-spacing: .08em;
        text-transform: uppercase;
        text-align: center;
        font-weight: 800;
      }

      .meta {
        display: flex;
        justify-content: space-between;
        gap: 16px;
        font-size: 16px;
        margin-bottom: 18px;
        padding-bottom: 14px;
        border-bottom: 1px solid var(--line);
      }

      .meta b { font-weight: 700; }

      .section {
        margin-top: 18px;
      }

      .section h3 {
        margin: 0 0 10px;
        font-size: 18px;
        letter-spacing: .02em;
      }

      .kv {
        width: 100%;
        border-collapse: collapse;
        font-size: 16px;
      }

      .kv td {
        padding: 8px 0;
        border-bottom: 1px solid rgba(31,41,55,.08);
      }

      .kv td:first-child {
        width: 180px;
        color: var(--muted);
        font-weight: 600;
        padding-right: 10px;
      }

      .kv td:nth-child(2) {
        width: 12px;
        color: var(--muted);
      }

      .table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
        border: 1px solid rgba(15,79,66,.25);
      }

      .table thead th {
        background: rgba(15,79,66,.92);
        color: #fff;
        padding: 10px 12px;
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: .08em;
      }

      .table td {
        border-top: 1px solid rgba(15,79,66,.18);
        padding: 12px;
        font-size: 15px;
      }

      .amount {
        text-align: right;
        white-space: nowrap;
        font-weight: 700;
      }

      .notes {
        margin-top: 16px;
        color: var(--muted);
        font-size: 14px;
        line-height: 1.55;
      }

      .footer {
        margin-top: 24px;
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 24px;
      }

      .sign {
        flex: 1;
      }

      .sign .line {
        margin-top: 34px;
        height: 1px;
        background: var(--line);
      }

      .sign .label {
        margin-top: 10px;
        font-weight: 700;
      }

      .stamp {
        width: 170px;
        height: 170px;
        border-radius: 999px;
        border: 6px solid rgba(15,79,66,.65);
        background: radial-gradient(circle at 30% 30%, rgba(200,160,74,.24), rgba(15,79,66,.08));
        display: grid;
        place-items: center;
        color: rgba(15,79,66,.9);
        text-align: center;
        padding: 12px;
      }

      .stamp b {
        display: block;
        font-size: 16px;
        letter-spacing: .12em;
      }

      .stamp span {
        display: block;
        margin-top: 6px;
        font-size: 12px;
        letter-spacing: .12em;
        text-transform: uppercase;
      }

      .bottomBar {
        background: linear-gradient(180deg, #073127, var(--green-900));
        padding: 14px 18px;
        color: rgba(255,255,255,.7);
        font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
        font-size: 12px;
        letter-spacing: .06em;
      }

      @media print {
        body { background: #fff; }
        .page { box-shadow: none; margin: 0; border: none; border-radius: 0; width: auto; }
        .bottomBar { display: none; }
      }
    </style>
  </head>
  <body>
    @php
      /** @var \App\Models\Lead $lead */
      $receiptNo = $lead->receipt_no ?: str_pad((string) $lead->id, 2, '0', STR_PAD_LEFT);
      $receiptDate = $lead->receipt_date ? $lead->receipt_date->format('jS M, Y') : ($lead->created_at ? $lead->created_at->format('jS M, Y') : now()->format('jS M, Y'));
      $customerName = $lead->name ?: '—';
      $customerCode = $lead->customer_code ?: '—';
      $paymentAgainst = $lead->payment_against ?: ($lead->plot_size ? 'Booking of Unit ('.$lead->plot_size.')' : 'Booking');
      $chequeNo = $lead->cheque_no ?: '—';
      $bankName = $lead->bank_name ?: '—';
      $desc = $lead->transaction_description ?: ($lead->notes ?: '—');
      $amount = $lead->transaction_amount ?? $lead->budget;
      $amountText = $amount === null ? '—' : number_format((float) $amount, 2);
      $amountWords = $lead->amount_in_words ?: '—';
      $notes = $lead->receipt_notes ?: '';
    @endphp

    <div class="page">
      <div class="header">
        <div class="brand">
          {{ $companyName ?: 'JND Infrastructure Pvt. Ltd.' }}
        </div>
      </div>

      <div class="content">
        <div class="titleRow">
          <div class="rule"></div>
          <div class="receiptTitle">Receipt</div>
          <div class="rule"></div>
        </div>

        <div class="meta">
          <div><b>Receipt No.:</b> {{ $receiptNo }}</div>
          <div><b>Date:</b> {{ $receiptDate }}</div>
        </div>

        <div class="section">
          <h3>Customer Details</h3>
          <table class="kv">
            <tr>
              <td>Name</td><td>:</td><td>{{ $customerName }}</td>
            </tr>
            <tr>
              <td>Customer Code</td><td>:</td><td>{{ $customerCode }}</td>
            </tr>
            <tr>
              <td>Phone</td><td>:</td><td>{{ $lead->phone ?: '—' }}</td>
            </tr>
            <tr>
              <td>Email</td><td>:</td><td>{{ $lead->email ?: '—' }}</td>
            </tr>
          </table>
        </div>

        <div class="section">
          <h3>Payment Details</h3>
          <table class="kv">
            <tr>
              <td>Payment Against</td><td>:</td><td>{{ $paymentAgainst }}</td>
            </tr>
            <tr>
              <td>Cheque No.</td><td>:</td><td>{{ $chequeNo }}</td>
            </tr>
            <tr>
              <td>Bank Name</td><td>:</td><td>{{ $bankName }}</td>
            </tr>
          </table>
        </div>

        <div class="section">
          <h3>Transaction Summary</h3>
          <table class="table">
            <thead>
              <tr>
                <th style="width: 80px;">S. No.</th>
                <th>Description</th>
                <th style="width: 170px; text-align:right;">Amount (Rs.)</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td style="text-align:center; font-weight:700;">1</td>
                <td>{{ $desc }}</td>
                <td class="amount">{{ $amountText }}</td>
              </tr>
              <tr>
                <td></td>
                <td style="font-style: italic; color: rgba(31,41,55,.75);">( {{ $amountWords }} )</td>
                <td class="amount">{{ $amountText }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        @if($notes)
          <div class="notes">
            <b>Notes:</b>
            <div style="margin-top:6px; white-space: pre-wrap;">{{ $notes }}</div>
          </div>
        @endif

        <div class="footer">
          <div class="sign">
            <div class="line"></div>
            <div class="label">Authorized Signatory</div>
            <div class="notes">For {{ $companyName ?: 'JND Infrastructure Pvt. Ltd.' }}</div>
          </div>
          <div class="stamp">
            <div>
              <b>APPROVED</b>
              <span>{{ $companyName ?: 'JND Infra' }}</span>
            </div>
          </div>
        </div>
      </div>

      <div class="bottomBar">
        Tip: Open this file in a browser and use Print → “Save as PDF”.
      </div>
    </div>
  </body>
</html>

