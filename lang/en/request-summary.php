<?php

return [
    'dashboard_paid' => 'Dashboard total withdrawals (exclude virtual users)',
    'paid_date' => 'Withdrawal statistics date (paid records only)',
    'paid_date_help' => 'Uses gateway completion time, or review time when absent, in the system timezone. These are current paid records, not a historical snapshot. Combine with the Dashboard total withdrawals filter to reconcile the dashboard.',
    'approval_date' => 'Approval date (approved records only)',
    'heading' => 'Filtered totals',
    'scope' => 'All matching records across all pages, within your viewing permissions. Totals follow the active filters and search.',
    'records' => 'Record count',
    'amount' => 'Total requested amount',
    'fee' => 'Total fees',
    'net' => 'Total net payout',
    'approval_hint' => 'To see approvals today, set both Approval date fields to today and apply filters. Dates use the system timezone. Approval does not mean payment has completed; filter payout status separately when needed.',
];
