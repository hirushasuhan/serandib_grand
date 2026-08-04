<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Contracts\Reportable;

final class ReportService
{
    public function occupancyByDay(string $from, string $to): array
    {
        $sql = "
            SELECT b.check_in AS date, COUNT(b.id) AS rooms_sold,
                   (SELECT COUNT(*) FROM rooms WHERE is_active = 1) AS total_rooms,
                   ROUND(COUNT(b.id) * 100.0 / (SELECT COUNT(*) FROM rooms WHERE is_active = 1), 2) AS occupancy_pct
            FROM bookings b
            WHERE b.status IN ('confirmed','checked_in','checked_out')
              AND b.check_in BETWEEN :from AND :to
            GROUP BY b.check_in
            ORDER BY b.check_in ASC";

        return Database::getInstance()->query($sql, [':from' => $from, ':to' => $to])->fetchAll();
    }

    public function revenueByMonth(int $year = 2026): array
    {
        $sql = "
            SELECT DATE_FORMAT(paid_at, '%Y-%m') AS month,
                   SUM(amount) AS total_revenue,
                   COUNT(id) AS transaction_count
            FROM payments
            WHERE YEAR(paid_at) = :year
            GROUP BY DATE_FORMAT(paid_at, '%Y-%m')
            ORDER BY month ASC";

        return Database::getInstance()->query($sql, [':year' => $year])->fetchAll();
    }

    public function revenueByRoomType(string $from, string $to): array
    {
        $sql = "
            SELECT rt.name, COUNT(b.id) AS bookings_count,
                   SUM(b.total_amount) AS revenue,
                   ROUND(AVG(b.room_rate), 2) AS avg_rate
            FROM bookings b
            JOIN rooms r ON r.id = b.room_id
            JOIN room_types rt ON rt.id = r.room_type_id
            WHERE b.status IN ('confirmed', 'checked_in', 'checked_out')
              AND b.check_in BETWEEN :from AND :to
            GROUP BY rt.id, rt.name
            ORDER BY revenue DESC";

        return Database::getInstance()->query($sql, [':from' => $from, ':to' => $to])->fetchAll();
    }

    public function bookingsByStatus(): array
    {
        $sql = "SELECT status, COUNT(*) AS count FROM bookings GROUP BY status";
        return Database::getInstance()->query($sql)->fetchAll();
    }

    public function kpiSummary(): object
    {
        $db = Database::getInstance();

        $today = date('Y-m-d');
        $totalRooms = (int)$db->query("SELECT COUNT(*) FROM rooms WHERE is_active = 1")->fetchColumn();
        
        $inHouse = (int)$db->query("SELECT COUNT(*) FROM bookings WHERE status = 'checked_in'")->fetchColumn();
        $arrivals = (int)$db->query("SELECT COUNT(*) FROM bookings WHERE check_in = :today AND status IN ('confirmed','pending')", [':today' => $today])->fetchColumn();
        $departures = (int)$db->query("SELECT COUNT(*) FROM bookings WHERE check_out = :today AND status = 'checked_in'", [':today' => $today])->fetchColumn();
        
        $todayRev = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE DATE(paid_at) = :today", [':today' => $today])->fetchColumn();
        $monthRev = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE MONTH(paid_at) = MONTH(NOW()) AND YEAR(paid_at) = YEAR(NOW())")->fetchColumn();

        $occupancyPct = $totalRooms > 0 ? round(($inHouse / $totalRooms) * 100, 1) : 0;

        $pendingApprovals = (int)$db->query("SELECT COUNT(*) FROM approvals WHERE status = 'pending'")->fetchColumn();
        $pendingReviews   = (int)$db->query("SELECT COUNT(*) FROM reviews WHERE status = 'pending'")->fetchColumn();

        return (object)[
            'total_rooms'       => $totalRooms,
            'in_house'          => $inHouse,
            'today_arrivals'    => $arrivals,
            'today_departures'  => $departures,
            'today_revenue'     => $todayRev,
            'month_revenue'     => $monthRev,
            'occupancy_pct'     => $occupancyPct,
            'pending_approvals' => $pendingApprovals,
            'pending_reviews'   => $pendingReviews,
        ];
    }

    public function exportCsv(array $reportableItems, string $filename = 'report.csv'): void
    {
        if (empty($reportableItems)) return;

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        
        /** @var Reportable $first */
        $first = $reportableItems[0];
        fputcsv($output, $first->reportHeadings());

        foreach ($reportableItems as $item) {
            if ($item instanceof Reportable) {
                fputcsv($output, $item->toReportRow());
            }
        }

        fclose($output);
        exit;
    }
}
