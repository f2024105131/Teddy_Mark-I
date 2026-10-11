<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\AuditLog;

class AuditLogController extends Controller
{
    public function index(): void
    {
        $filters = [
            'module'    => $this->input('module'),
            'user_type' => $this->input('user_type'),
            'from_date' => $this->input('from_date'),
            'to_date'   => $this->input('to_date'),
            'search'    => $this->input('search'),
        ];

        $page = max(1, (int) $this->input('page', 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $model = new AuditLog();
        $logs = $model->filtered($filters, $perPage, $offset);
        $total = $model->count($filters);

        $this->view('admin/audit_log', [
            'logs'       => $logs,
            'filters'    => $filters,
            'page'       => $page,
            'totalPages' => (int) ceil($total / $perPage),
        ], layout: 'admin');
    }

    public function export(): void
    {
        $model = new AuditLog();
        $logs = $model->filtered($this->all(), 10000, 0);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="audit_log_export.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Module', 'Action', 'User ID', 'User Type', 'IP Address', 'Timestamp']);

        foreach ($logs as $log) {
            fputcsv($output, [
                $log['audit_id'],
                $log['module'],
                $log['action'],
                $log['user_id'],
                $log['user_type'],
                $log['ip_address'],
                $log['action_timestamp'],
            ]);
        }

        fclose($output);
        exit;
    }
}